<?php

declare(strict_types=1);

namespace Erp\Purchase;

interface PurchaseOrderRepository
{
    /**
     * @return array<int, array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>}>
     */
    public function list(): array;

    /**
     * @return null|array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>}
     */
    public function find(int $id): ?array;

    /**
     * @param array{supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{material_id:int,quantity:string,unit_price:string}>} $data
     * @return array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>}
     */
    public function create(array $data): array;

    /**
     * @return array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>}
     */
    public function setStatus(int $id, string $status): array;
}
