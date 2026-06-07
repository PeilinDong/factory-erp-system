<?php

declare(strict_types=1);

namespace Erp\Material;

use PDO;

final class PdoMaterialRepository implements MaterialRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, code, name, specification, base_unit, material_type, is_active
             FROM materials
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
            'SELECT id, code, name, specification, base_unit, material_type, is_active
             FROM materials
             WHERE code LIKE :query OR name LIKE :query OR specification LIKE :query
             ORDER BY id DESC
             LIMIT 100'
        );
        $statement->execute(['query' => '%' . $query . '%']);

        return array_map($this->mapRow(...), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, name, specification, base_unit, material_type, is_active
             FROM materials
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
            'INSERT INTO materials (code, name, specification, base_unit, material_type, is_active, created_at)
             VALUES (:code, :name, :specification, :base_unit, :material_type, 1, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'code' => $data['code'],
            'name' => $data['name'],
            'specification' => $data['specification'] ?? '',
            'base_unit' => $data['base_unit'],
            'material_type' => $data['material_type'],
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'code' => $data['code'],
            'name' => $data['name'],
            'specification' => $data['specification'] ?? '',
            'base_unit' => $data['base_unit'],
            'material_type' => $data['material_type'],
            'is_active' => 1,
        ];
    }

    public function update(int $id, array $data): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE materials
             SET code = :code,
                 name = :name,
                 specification = :specification,
                 base_unit = :base_unit,
                 material_type = :material_type
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
            'specification' => $data['specification'] ?? '',
            'base_unit' => $data['base_unit'],
            'material_type' => $data['material_type'],
        ]);

        return $this->find($id) ?? throw new \RuntimeException('material not found');
    }

    public function setActive(int $id, bool $active): array
    {
        $statement = $this->pdo->prepare('UPDATE materials SET is_active = :is_active WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('material not found');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'specification' => (string) ($row['specification'] ?? ''),
            'base_unit' => (string) $row['base_unit'],
            'material_type' => (string) $row['material_type'],
            'is_active' => (int) $row['is_active'],
        ];
    }
}
