<?php

declare(strict_types=1);

namespace Erp\Bom;

final class InMemoryBomRepository implements BomRepository
{
    /**
     * @var array<int, array{id:int,project_code:string,project_name:string,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>}>
     */
    private array $boms = [];

    private int $nextItemId = 1;

    public function list(): array
    {
        return array_values($this->boms);
    }

    public function search(string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return $this->list();
        }

        return array_values(array_filter($this->boms, static function (array $bom) use ($query): bool {
            return str_contains(strtolower($bom['project_code']), $query)
                || str_contains(strtolower($bom['project_name']), $query)
                || str_contains(strtolower($bom['version']), $query);
        }));
    }

    public function find(int $id): ?array
    {
        return $this->boms[$id] ?? null;
    }

    public function create(array $data): array
    {
        $bomId = count($this->boms) + 1;
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = [
                'id' => $this->nextItemId++,
                'bom_id' => $bomId,
                'component_material_id' => $item['component_material_id'],
                'quantity' => $item['quantity'],
                'scrap_rate' => $item['scrap_rate'],
            ];
        }

        $bom = [
            'id' => $bomId,
            'project_code' => $data['project_code'],
            'project_name' => $data['project_name'],
            'parent_material_id' => $data['parent_material_id'],
            'version' => $data['version'],
            'is_active' => 1,
            'items' => $items,
        ];
        $this->boms[$bomId] = $bom;

        return $bom;
    }

    public function setActive(int $id, bool $active): array
    {
        if (!isset($this->boms[$id])) {
            throw new \RuntimeException('bom not found');
        }

        $this->boms[$id]['is_active'] = $active ? 1 : 0;

        return $this->boms[$id];
    }
}
