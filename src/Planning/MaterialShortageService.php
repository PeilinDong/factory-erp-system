<?php

declare(strict_types=1);

namespace Erp\Planning;

use Erp\Inventory\InventoryService;
use Erp\WorkOrder\WorkOrderService;

final class MaterialShortageService
{
    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly InventoryService $inventory,
    ) {
    }

    /**
     * @return array<int, array{material_id:int,material_code:string,material_name:string,required_quantity:string,stock_quantity:string,shortage_quantity:string,source_orders:string}>
     */
    public function analyze(): array
    {
        $stockByMaterial = $this->stockByMaterial();
        $demand = [];

        foreach ($this->workOrders->list() as $order) {
            if ($order['status'] !== 'planned') {
                continue;
            }

            foreach ($order['requirements'] as $requirement) {
                $materialId = (int) $requirement['component_material_id'];
                $demand[$materialId] ??= [
                    'material_id' => $materialId,
                    'material_code' => $requirement['component_material_code'],
                    'material_name' => $requirement['component_material_name'],
                    'required_quantity' => 0.0,
                    'source_orders' => [],
                ];

                $demand[$materialId]['required_quantity'] += (float) $requirement['required_quantity'];
                $demand[$materialId]['source_orders'][$order['order_no']] = $order['order_no'];
            }
        }

        $rows = [];
        foreach ($demand as $materialId => $row) {
            $required = (float) $row['required_quantity'];
            $stock = $stockByMaterial[$materialId] ?? 0.0;
            $shortage = $required - $stock;

            if ($shortage <= 0.000001) {
                continue;
            }

            $rows[] = [
                'material_id' => $materialId,
                'material_code' => (string) $row['material_code'],
                'material_name' => (string) $row['material_name'],
                'required_quantity' => $this->normalizeQuantity((string) $required),
                'stock_quantity' => $this->normalizeQuantity((string) $stock),
                'shortage_quantity' => $this->normalizeQuantity((string) $shortage),
                'source_orders' => implode(', ', array_values($row['source_orders'])),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $left['material_code'] <=> $right['material_code']);

        return $rows;
    }

    /**
     * @return array<int, float>
     */
    private function stockByMaterial(): array
    {
        $stock = [];
        foreach ($this->inventory->stockBalances() as $balance) {
            $materialId = (int) $balance['material_id'];
            $stock[$materialId] ??= 0.0;
            $stock[$materialId] += (float) $balance['quantity'];
        }

        return $stock;
    }

    private function normalizeQuantity(string $quantity): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $quantity), '0'), '.');
    }
}
