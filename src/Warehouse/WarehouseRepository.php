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
     * @return null|array{id:int,code:string,name:string,is_active:int}
     */
    public function find(int $id): ?array;

    /**
     * @param array{code:string,name:string} $data
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function create(array $data): array;

    /**
     * @param array{code:string,name:string} $data
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function update(int $id, array $data): array;

    /**
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function setActive(int $id, bool $active): array;
}
