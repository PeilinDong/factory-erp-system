<?php

declare(strict_types=1);

namespace Erp\WorkOrder;

final class InMemoryWorkOrderRepository implements WorkOrderRepository
{
    /**
     * @var array<int, array{id:int,order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string}>
     */
    private array $orders = [];

    public function list(): array
    {
        return array_values($this->orders);
    }

    public function find(int $id): ?array
    {
        return $this->orders[$id] ?? null;
    }

    public function create(array $data): array
    {
        $id = count($this->orders) + 1;
        $order = [
            'id' => $id,
            'order_no' => $data['order_no'],
            'bom_id' => $data['bom_id'],
            'planned_quantity' => $data['planned_quantity'],
            'due_date' => $data['due_date'],
            'status' => $data['status'],
        ];
        $this->orders[$id] = $order;

        return $order;
    }

    public function setStatus(int $id, string $status): array
    {
        $order = $this->find($id);
        if ($order === null) {
            throw new \RuntimeException('work order not found');
        }

        $order['status'] = $status;
        $this->orders[$id] = $order;

        return $order;
    }
}
