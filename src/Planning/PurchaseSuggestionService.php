<?php

declare(strict_types=1);

namespace Erp\Planning;

use Erp\Purchase\PurchaseOrderService;

final class PurchaseSuggestionService
{
    public function __construct(private readonly MaterialShortageService $shortages)
    {
    }

    /**
     * @return array<int, array{material_id:int,material_code:string,material_name:string,shortage_quantity:string,suggested_quantity:string,source_orders:string}>
     */
    public function list(): array
    {
        return array_map(static fn (array $row): array => [
            'material_id' => $row['material_id'],
            'material_code' => $row['material_code'],
            'material_name' => $row['material_name'],
            'shortage_quantity' => $row['shortage_quantity'],
            'suggested_quantity' => $row['shortage_quantity'],
            'source_orders' => $row['source_orders'],
        ], $this->shortages->analyze());
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}
     */
    public function convertToPurchaseOrder(array $data, PurchaseOrderService $purchases): array
    {
        $materialId = (int) ($data['material_id'] ?? 0);
        $suggestion = $this->findSuggestion($materialId);
        if ($suggestion === null) {
            throw new \InvalidArgumentException('purchase suggestion must exist');
        }

        return $purchases->create([
            'order_no' => (string) ($data['order_no'] ?? ''),
            'supplier_name' => (string) ($data['supplier_name'] ?? ''),
            'expected_date' => (string) ($data['expected_date'] ?? ''),
            'material_id' => (string) $materialId,
            'quantity' => $suggestion['suggested_quantity'],
            'unit_price' => (string) ($data['unit_price'] ?? '0'),
        ]);
    }

    /**
     * @return null|array{material_id:int,material_code:string,material_name:string,shortage_quantity:string,suggested_quantity:string,source_orders:string}
     */
    private function findSuggestion(int $materialId): ?array
    {
        foreach ($this->list() as $suggestion) {
            if ((int) $suggestion['material_id'] === $materialId) {
                return $suggestion;
            }
        }

        return null;
    }
}
