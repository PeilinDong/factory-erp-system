<?php

declare(strict_types=1);

namespace Erp\Supplier;

use PDO;

final class PdoSupplierRepository implements SupplierRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, code, name, contact_name, phone, is_active
             FROM suppliers
             ORDER BY id DESC
             LIMIT 100'
        );

        return array_map($this->mapRow(...), $statement->fetchAll());
    }

    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->list();
        }

        $statement = $this->pdo->prepare(
            'SELECT id, code, name, contact_name, phone, is_active
             FROM suppliers
             WHERE code LIKE :query OR name LIKE :query OR contact_name LIKE :query OR phone LIKE :query
             ORDER BY id DESC
             LIMIT 100'
        );
        $statement->execute(['query' => '%' . $query . '%']);

        return array_map($this->mapRow(...), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, name, contact_name, phone, is_active
             FROM suppliers
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function create(array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO suppliers (code, name, contact_name, phone, is_active, created_at)
             VALUES (:code, :name, :contact_name, :phone, 1, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'code' => $data['code'],
            'name' => $data['name'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
        ]);

        return $this->find((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('supplier not found');
    }

    public function update(int $id, array $data): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE suppliers
             SET code = :code,
                 name = :name,
                 contact_name = :contact_name,
                 phone = :phone
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
        ]);

        return $this->find($id) ?? throw new \RuntimeException('supplier not found');
    }

    public function setActive(int $id, bool $active): array
    {
        $statement = $this->pdo->prepare('UPDATE suppliers SET is_active = :is_active WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('supplier not found');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'contact_name' => (string) ($row['contact_name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'is_active' => (int) $row['is_active'],
        ];
    }
}
