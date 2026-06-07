<?php

declare(strict_types=1);

namespace Erp\Purchase;

final class InMemoryPurchaseOrderRepository implements PurchaseOrderRepository
{
    /**
     * @var array<int, array{id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>}>
     */
    private array $orders = [];

    private int $nextItemId = 1;

    public function list(): array
    {
        return array_values($this->orders);
    }

    public function create(array $data): array
    {
        $orderId = count($this->orders) + 1;
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = [
                'id' => $this->nextItemId++,
                'purchase_order_id' => $orderId,
                'material_id' => $item['material_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ];
        }

        $order = [
            'id' => $orderId,
            'order_no' => $data['order_no'],
            'supplier_name' => $data['supplier_name'],
            'expected_date' => $data['expected_date'],
            'status' => $data['status'],
            'items' => $items,
        ];
        $this->orders[$orderId] = $order;

        return $order;
    }
}
