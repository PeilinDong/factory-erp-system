<?php

declare(strict_types=1);

namespace Erp\WorkOrder;

interface WorkOrderRepository
{
    /**
     * @return array<int, array{id:int,order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string}>
     */
    public function list(): array;

    /**
     * @param array{order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string} $data
     * @return array{id:int,order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string}
     */
    public function create(array $data): array;
}
