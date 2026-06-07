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

    public function search(string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return $this->list();
        }

        return array_values(array_filter($this->warehouses, static function (array $warehouse) use ($query): bool {
            return str_contains(strtolower($warehouse['code']), $query)
                || str_contains(strtolower($warehouse['name']), $query);
        }));
    }

    public function find(int $id): ?array
    {
        return $this->warehouses[$id] ?? null;
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

    public function update(int $id, array $data): array
    {
        $warehouse = $this->find($id);
        if ($warehouse === null) {
            throw new \RuntimeException('warehouse not found');
        }

        $warehouse['code'] = $data['code'];
        $warehouse['name'] = $data['name'];
        $this->warehouses[$id] = $warehouse;

        return $warehouse;
    }

    public function setActive(int $id, bool $active): array
    {
        $warehouse = $this->find($id);
        if ($warehouse === null) {
            throw new \RuntimeException('warehouse not found');
        }

        $warehouse['is_active'] = $active ? 1 : 0;
        $this->warehouses[$id] = $warehouse;

        return $warehouse;
    }
}
