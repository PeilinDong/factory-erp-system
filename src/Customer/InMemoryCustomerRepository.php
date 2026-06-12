<?php

declare(strict_types=1);

namespace Erp\Customer;

final class InMemoryCustomerRepository implements CustomerRepository
{
    /**
     * @var array<int, array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}>
     */
    private array $customers = [];

    public function list(): array
    {
        return array_values($this->customers);
    }

    public function search(string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return $this->list();
        }

        return array_values(array_filter($this->customers, static function (array $customer) use ($query): bool {
            return str_contains(strtolower($customer['code']), $query)
                || str_contains(strtolower($customer['name']), $query)
                || str_contains(strtolower($customer['contact_name']), $query)
                || str_contains(strtolower($customer['phone']), $query);
        }));
    }

    public function find(int $id): ?array
    {
        return $this->customers[$id] ?? null;
    }

    public function create(array $data): array
    {
        $id = count($this->customers) + 1;
        $customer = [
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
            'is_active' => 1,
        ];
        $this->customers[$id] = $customer;

        return $customer;
    }

    public function update(int $id, array $data): array
    {
        $customer = $this->find($id);
        if ($customer === null) {
            throw new \RuntimeException('customer not found');
        }

        $customer['code'] = $data['code'];
        $customer['name'] = $data['name'];
        $customer['contact_name'] = $data['contact_name'];
        $customer['phone'] = $data['phone'];
        $this->customers[$id] = $customer;

        return $customer;
    }

    public function setActive(int $id, bool $active): array
    {
        $customer = $this->find($id);
        if ($customer === null) {
            throw new \RuntimeException('customer not found');
        }

        $customer['is_active'] = $active ? 1 : 0;
        $this->customers[$id] = $customer;

        return $customer;
    }
}
