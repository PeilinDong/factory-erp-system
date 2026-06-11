<?php

declare(strict_types=1);

namespace Erp\WorkOrder;

use Erp\Bom\BomService;
use Erp\Inventory\InventoryService;

final class WorkOrderService
{
    public function __construct(
        private readonly WorkOrderRepository $orders,
        private readonly BomService $boms,
    ) {
    }

    /**
     * @return array<int, array{id:int,order_no:string,bom_id:int,project_code:string,project_name:string,parent_material_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}>
     */
    public function list(): array
    {
        return array_map($this->enrich(...), $this->orders->list());
    }

    /**
     * @return null|array{id:int,order_no:string,bom_id:int,project_code:string,project_name:string,parent_material_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_id:int,component_material_code:string,component_material_name:string,required_quantity:string}>}
     */
    public function find(int $id): ?array
    {
        $order = $this->orders->find($id);

        return $order === null ? null : $this->enrich($order);
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,order_no:string,bom_id:int,project_code:string,project_name:string,parent_material_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}
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
     * @return array<int, array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,occurred_at:string}>
     */
    public function issueMaterials(int $id, int $warehouseId, InventoryService $inventory): array
    {
        $order = $this->find($id);
        if ($order === null) {
            throw new \InvalidArgumentException('work order must exist');
        }

        if ($order['status'] === 'issued' || $order['status'] === 'completed') {
            throw new \InvalidArgumentException('work order is already issued');
        }

        if ($warehouseId <= 0) {
            throw new \InvalidArgumentException('warehouse must exist');
        }

        $transactions = [];
        foreach ($order['requirements'] as $requirement) {
            $transactions[] = $inventory->record([
                'material_id' => (string) $requirement['component_material_id'],
                'warehouse_id' => (string) $warehouseId,
                'transaction_type' => 'outbound',
                'quantity' => $requirement['required_quantity'],
                'reference_no' => $order['order_no'],
            ]);
        }

        $this->orders->setStatus($id, 'issued');

        return $transactions;
    }

    /**
     * @return array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,occurred_at:string}
     */
    public function complete(int $id, int $warehouseId, InventoryService $inventory): array
    {
        $order = $this->find($id);
        if ($order === null) {
            throw new \InvalidArgumentException('work order must exist');
        }

        if ($order['status'] === 'completed') {
            throw new \InvalidArgumentException('work order is already completed');
        }

        if ($warehouseId <= 0) {
            throw new \InvalidArgumentException('warehouse must exist');
        }

        $transaction = $inventory->record([
            'material_id' => (string) $order['parent_material_id'],
            'warehouse_id' => (string) $warehouseId,
            'transaction_type' => 'inbound',
            'quantity' => $order['planned_quantity'],
            'reference_no' => $order['order_no'],
        ]);

        $this->orders->setStatus($id, 'completed');

        return $transaction;
    }

    /**
     * @param array{id:int,order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string} $order
     * @return array{id:int,order_no:string,bom_id:int,project_code:string,project_name:string,parent_material_id:int,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_id:int,component_material_code:string,component_material_name:string,required_quantity:string}>}
     */
    private function enrich(array $order): array
    {
        $bom = $this->boms->find($order['bom_id']);
        $requirements = $bom === null ? [] : $this->boms->requirements($order['bom_id'], $order['planned_quantity']);

        return [
            'id' => $order['id'],
            'order_no' => $order['order_no'],
            'bom_id' => $order['bom_id'],
            'project_code' => (string) ($bom['project_code'] ?? 'STANDARD'),
            'project_name' => (string) ($bom['project_name'] ?? '标准项目'),
            'parent_material_id' => (int) ($bom['parent_material_id'] ?? 0),
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
