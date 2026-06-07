<?php

declare(strict_types=1);

namespace Erp\WorkOrder;

use Erp\Bom\BomService;

final class WorkOrderService
{
    public function __construct(
        private readonly WorkOrderRepository $orders,
        private readonly BomService $boms,
    ) {
    }

    /**
     * @return array<int, array{id:int,order_no:string,bom_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}>
     */
    public function list(): array
    {
        return array_map($this->enrich(...), $this->orders->list());
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,order_no:string,bom_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}
     */
    public function create(array $data): array
    {
        $orderNo = strtoupper(trim($data['order_no'] ?? ''));
        $bomId = (int) ($data['bom_id'] ?? 0);
        $plannedQuantity = trim($data['planned_quantity'] ?? '');
        $dueDate = trim($data['due_date'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $orderNo)) {
            throw new \InvalidArgumentException('work order number is invalid');
        }

        if (!preg_match('/^\d+(\.\d{1,6})?$/', $plannedQuantity) || (float) $plannedQuantity <= 0.0) {
            throw new \InvalidArgumentException('planned quantity must be greater than zero');
        }

        if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new \InvalidArgumentException('due date must use YYYY-MM-DD');
        }

        if ($this->boms->find($bomId) === null) {
            throw new \InvalidArgumentException('bom must exist');
        }

        return $this->enrich($this->orders->create([
            'order_no' => $orderNo,
            'bom_id' => $bomId,
            'planned_quantity' => $this->normalizeQuantity($plannedQuantity),
            'due_date' => $dueDate,
            'status' => 'planned',
        ]));
    }

    /**
     * @param array{id:int,order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string} $order
     * @return array{id:int,order_no:string,bom_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}
     */
    private function enrich(array $order): array
    {
        $bom = $this->boms->find($order['bom_id']);
        $requirements = $bom === null ? [] : $this->boms->requirements($order['bom_id'], $order['planned_quantity']);

        return [
            'id' => $order['id'],
            'order_no' => $order['order_no'],
            'bom_id' => $order['bom_id'],
            'parent_material_code' => (string) ($bom['parent_material_code'] ?? $order['bom_id']),
            'parent_material_name' => (string) ($bom['parent_material_name'] ?? ''),
            'planned_quantity' => $order['planned_quantity'],
            'due_date' => $order['due_date'],
            'status' => $order['status'],
            'requirements' => $requirements,
        ];
    }

    private function normalizeQuantity(string $quantity): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $quantity), '0'), '.');
    }
}
