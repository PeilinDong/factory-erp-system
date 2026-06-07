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

        return array_map($this->mapRow(...), $statement->fetchAll());
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

        return array_map($this->mapRow(...), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, name, is_active
             FROM warehouses
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

    public function update(int $id, array $data): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE warehouses
             SET code = :code,
                 name = :name
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
        ]);

        return $this->find($id) ?? throw new \RuntimeException('warehouse not found');
    }

    public function setActive(int $id, bool $active): array
    {
        $statement = $this->pdo->prepare('UPDATE warehouses SET is_active = :is_active WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('warehouse not found');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,code:string,name:string,is_active:int}
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'is_active' => (int) $row['is_active'],
        ];
    }
}
