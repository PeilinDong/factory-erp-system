<?php

declare(strict_types=1);

namespace Erp\Customer;

final class CustomerService
{
    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    /**
     * @return array<int, array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}>
     */
    public function list(): array
    {
        return $this->customers->list();
    }

    /**
     * @return array<int, array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}>
     */
    public function search(string $query): array
    {
        return $this->customers->search(trim($query));
    }

    /**
     * @return null|array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}
     */
    public function find(int $id): ?array
    {
        return $this->customers->find($id);
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}
     */
    public function create(array $data): array
    {
        return $this->customers->create($this->normalize($data));
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}
     */
    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('customer not found');
        }

        return $this->customers->update($id, $this->normalize($data));
    }

    /**
     * @return array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}
     */
    public function setActive(int $id, bool $active): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('customer not found');
        }

        return $this->customers->setActive($id, $active);
    }

    /**
     * @param array<string, string> $data
     * @return array{code:string,name:string,contact_name:string,phone:string}
     */
    private function normalize(array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $contactName = trim($data['contact_name'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $code)) {
            throw new \InvalidArgumentException('customer code must be 2-64 chars and use letters, numbers, dot, underscore, or dash');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('customer name must not be empty');
        }

        return [
            'code' => $code,
            'name' => $name,
            'contact_name' => $contactName,
            'phone' => $phone,
        ];
    }
}
