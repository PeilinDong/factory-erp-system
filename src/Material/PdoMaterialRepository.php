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

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'specification' => (string) ($row['specification'] ?? ''),
            'base_unit' => (string) $row['base_unit'],
            'material_type' => (string) $row['material_type'],
            'is_active' => (int) $row['is_active'],
        ], $statement->fetchAll());
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
}

