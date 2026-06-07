<?php

declare(strict_types=1);

namespace Erp\Inventory;

interface InventoryTransactionRepository
{
    /**
     * @return array<int, array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,occurred_at:string}>
     */
    public function list(): array;

    /**
     * @param array{material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string} $data
     * @return array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,occurred_at:string}
     */
    public function create(array $data): array;
}
