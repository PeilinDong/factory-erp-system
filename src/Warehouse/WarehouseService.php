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
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    public function create(array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $code)) {
            throw new \InvalidArgumentException('warehouse code must be 2-64 chars and use letters, numbers, dot, underscore, or dash');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('warehouse name must not be empty');
        }

        return $this->warehouses->create([
            'code' => $code,
            'name' => $name,
        ]);
    }
}
