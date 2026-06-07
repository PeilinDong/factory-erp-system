<?php

declare(strict_types=1);

namespace Erp\Warehouse;

use PDO;

final class PdoWarehouseRepository implements WarehouseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, code, name, is_active
             FROM warehouses
             ORDER BY id DESC
             LIMIT 100'
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'is_active' => (int) $row['is_active'],
        ], $statement->fetchAll());
    }

    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->list();
        }

        $statement = $this->pdo->prepare(
            'SELECT id, code, name, is_active
             FROM warehouses
             WHERE code LIKE :query OR name LIKE :query
             ORDER BY id DESC
             LIMIT 100'
        );
        $statement->execute(['query' => '%' . $query . '%']);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'is_active' => (int) $row['is_active'],
        ], $statement->fetchAll());
    }

    public function create(array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO warehouses (code, name, is_active, created_at)
             VALUES (:code, :name, 1, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'code' => $data['code'],
            'name' => $data['name'],
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => 1,
        ];
    }
}
