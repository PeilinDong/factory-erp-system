<?php

declare(strict_types=1);

namespace Erp\Warehouse;

final class WarehouseService
{
    public function __construct(private readonly WarehouseRepository $warehouses)
    {
    }

    /**
     * @return array<int, array{id:int,code:string,name:string,is_active:int}>
     */
    public function list(): array
    {
        return $this->warehouses->list();
    }

    /**
     * @return array<int, array{id:int,code:string,name:string,is_active:int}>
     */
    public function search(string $query): array
    {
        return $this->warehouses->search(trim($query));
    }

    /**
     * @return null|array{id:int,code:string,name:string,is_active:int}
     */
    public function find(int $id): ?array
    {
        return $this->warehouses->find($id);
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function create(array $data): array
    {
        return $this->warehouses->create($this->normalize($data));
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('warehouse not found');
        }

        return $this->warehouses->update($id, $this->normalize($data));
    }

    /**
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function setActive(int $id, bool $active): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('warehouse not found');
        }

        return $this->warehouses->setActive($id, $active);
    }

    /**
     * @param array<string, string> $data
     * @return array{code:string,name:string}
     */
    private function normalize(array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $code)) {
            throw new \InvalidArgumentException('warehouse code must be 2-64 chars and use letters, numbers, dot, underscore, or dash');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('warehouse name must not be empty');
        }

        return [
            'code' => $code,
            'name' => $name,
        ];
    }
}
