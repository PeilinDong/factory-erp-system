<?php

declare(strict_types=1);

namespace Erp\Inventory;

final class InMemoryInventoryTransactionRepository implements InventoryTransactionRepository
{
    /**
     * @var array<int, array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,batch_no:string,occurred_at:string}>
     */
    private array $transactions = [];

    public function list(): array
    {
        return array_reverse(array_values($this->transactions));
    }

    public function create(array $data): array
    {
        $id = count($this->transactions) + 1;
        $transaction = [
            'id' => $id,
            'material_id' => $data['material_id'],
            'warehouse_id' => $data['warehouse_id'],
            'transaction_type' => $data['transaction_type'],
            'quantity' => $data['quantity'],
            'reference_no' => $data['reference_no'],
            'batch_no' => $data['batch_no'],
            'occurred_at' => date('Y-m-d H:i:s'),
        ];
        $this->transactions[$id] = $transaction;

        return $transaction;
    }
}
