<?php

declare(strict_types=1);

namespace Erp\Warehouse;

interface WarehouseRepository
{
    /**
     * @return array<int, array{id:int,code:string,name:string,is_active:int}>
     */
    public function list(): array;

    /**
     * @return array<int, array{id:int,code:string,name:string,is_active:int}>
     */
    public function search(string $query): array;

    /**
     * @param array{code:string,name:string} $data
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function create(array $data): array;
}
