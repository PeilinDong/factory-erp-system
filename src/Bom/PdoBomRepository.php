<?php

declare(strict_types=1);

namespace Erp\Bom;

use PDO;

final class PdoBomRepository implements BomRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            "SELECT id,
                    COALESCE(project_code, 'STANDARD') AS project_code,
                    COALESCE(project_name, '标准项目') AS project_name,
                    parent_material_id,
                    version,
                    is_active
             FROM boms
             ORDER BY id DESC
             LIMIT 100"
        );

        $boms = array_map($this->mapBomRow(...), $statement->fetchAll());
        foreach ($boms as $index => $bom) {
            $boms[$index]['items'] = $this->itemsFor((int) $bom['id']);
        }

        return $boms;
    }

    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->list();
        }

        $statement = $this->pdo->prepare(
            "SELECT id,
                    COALESCE(project_code, 'STANDARD') AS project_code,
                    COALESCE(project_name, '标准项目') AS project_name,
                    parent_material_id,
                    version,
                    is_active
             FROM boms
             WHERE project_code LIKE :query OR project_name LIKE :query OR version LIKE :query
             ORDER BY id DESC
             LIMIT 100"
        );
        $statement->execute(['query' => '%' . $query . '%']);

        $boms = array_map($this->mapBomRow(...), $statement->fetchAll());
        foreach ($boms as $index => $bom) {
            $boms[$index]['items'] = $this->itemsFor((int) $bom['id']);
        }

        return $boms;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id,
                    COALESCE(project_code, 'STANDARD') AS project_code,
                    COALESCE(project_name, '标准项目') AS project_name,
                    parent_material_id,
                    version,
                    is_active
             FROM boms
             WHERE id = :id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        $bom = $this->mapBomRow($row);
        $bom['items'] = $this->itemsFor((int) $bom['id']);

        return $bom;
    }

    public function create(array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO boms (project_code, project_name, parent_material_id, version, is_active, created_at)
                 VALUES (:project_code, :project_name, :parent_material_id, :version, 1, CURRENT_TIMESTAMP)'
            );
            $statement->execute([
                'project_code' => $data['project_code'],
                'project_name' => $data['project_name'],
                'parent_material_id' => $data['parent_material_id'],
                'version' => $data['version'],
            ]);
            $bomId = (int) $this->pdo->lastInsertId();

            $itemStatement = $this->pdo->prepare(
                'INSERT INTO bom_items (bom_id, component_material_id, quantity, scrap_rate)
                 VALUES (:bom_id, :component_material_id, :quantity, :scrap_rate)'
            );
            foreach ($data['items'] as $item) {
                $itemStatement->execute([
                    'bom_id' => $bomId,
                    'component_material_id' => $item['component_material_id'],
                    'quantity' => $item['quantity'],
                    'scrap_rate' => $item['scrap_rate'],
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->find($bomId) ?? throw new \RuntimeException('bom not found');
    }

    public function setActive(int $id, bool $active): array
    {
        $statement = $this->pdo->prepare('UPDATE boms SET is_active = :is_active WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('bom not found');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,project_code:string,project_name:string,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>}
     */
    private function mapBomRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'project_code' => (string) ($row['project_code'] ?? 'STANDARD'),
            'project_name' => (string) ($row['project_name'] ?? '标准项目'),
            'parent_material_id' => (int) $row['parent_material_id'],
            'version' => (string) $row['version'],
            'is_active' => (int) $row['is_active'],
            'items' => [],
        ];
    }

    /**
     * @return array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>
     */
    private function itemsFor(int $bomId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, bom_id, component_material_id, quantity, scrap_rate
             FROM bom_items
             WHERE bom_id = :bom_id
             ORDER BY id ASC'
        );
        $statement->execute(['bom_id' => $bomId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'bom_id' => (int) $row['bom_id'],
            'component_material_id' => (int) $row['component_material_id'],
            'quantity' => rtrim(rtrim((string) $row['quantity'], '0'), '.'),
            'scrap_rate' => rtrim(rtrim((string) $row['scrap_rate'], '0'), '.'),
        ], $statement->fetchAll());
    }
}
