<?php

declare(strict_types=1);

namespace Erp\Sales;

interface SalesOrderRepository
{
    /**
     * @return array<int, array{id:int,order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string}>
     */
    public function list(): array;

    /**
     * @return null|array{id:int,order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string}
     */
    public function find(int $id): ?array;

    /**
     * @param array{order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string} $data
     * @return array{id:int,order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string}
     */
    public function create(array $data): array;

    /**
     * @return array{id:int,order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string}
     */
    public function setStatus(int $id, string $status): array;
}
