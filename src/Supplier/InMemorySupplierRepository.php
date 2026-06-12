<?php

declare(strict_types=1);

namespace Erp\Supplier;

final class InMemorySupplierRepository implements SupplierRepository
{
    /**
     * @var array<int, array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}>
     */
    private array $suppliers = [];

    public function list(): array
    {
        return array_values($this->suppliers);
    }

    public function search(string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return $this->list();
        }

        return array_values(array_filter($this->suppliers, static function (array $supplier) use ($query): bool {
            return str_contains(strtolower($supplier['code']), $query)
                || str_contains(strtolower($supplier['name']), $query)
                || str_contains(strtolower($supplier['contact_name']), $query)
                || str_contains(strtolower($supplier['phone']), $query);
        }));
    }

    public function find(int $id): ?array
    {
        return $this->suppliers[$id] ?? null;
    }

    public function create(array $data): array
    {
        $id = count($this->suppliers) + 1;
        $supplier = [
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
            'is_active' => 1,
        ];
        $this->suppliers[$id] = $supplier;

        return $supplier;
    }

    public function update(int $id, array $data): array
    {
        $supplier = $this->find($id);
        if ($supplier === null) {
            throw new \RuntimeException('supplier not found');
        }

        $supplier['code'] = $data['code'];
        $supplier['name'] = $data['name'];
        $supplier['contact_name'] = $data['contact_name'];
        $supplier['phone'] = $data['phone'];
        $this->suppliers[$id] = $supplier;

        return $supplier;
    }

    public function setActive(int $id, bool $active): array
    {
        $supplier = $this->find($id);
        if ($supplier === null) {
            throw new \RuntimeException('supplier not found');
        }

        $supplier['is_active'] = $active ? 1 : 0;
        $this->suppliers[$id] = $supplier;

        return $supplier;
    }
}
