<?php

declare(strict_types=1);

namespace Erp\Warehouse;

final class InMemoryWarehouseRepository implements WarehouseRepository
{
    /**
     * @var array<int, array{id:int,code:string,name:string,is_active:int}>
     */
    private array $warehouses = [];

    public function list(): array
    {
        return array_values($this->warehouses);
    }

    public function create(array $data): array
    {
        $id = count($this->warehouses) + 1;
        $warehouse = [
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => 1,
        ];
        $this->warehouses[$id] = $warehouse;

        return $warehouse;
    }
}
