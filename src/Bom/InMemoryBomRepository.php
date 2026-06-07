<?php

declare(strict_types=1);

namespace Erp\Bom;

final class InMemoryBomRepository implements BomRepository
{
    /**
     * @var array<int, array{id:int,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>}>
     */
    private array $boms = [];

    private int $nextItemId = 1;

    public function list(): array
    {
        return array_values($this->boms);
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
            'parent_material_id' => $data['parent_material_id'],
            'version' => $data['version'],
            'is_active' => 1,
            'items' => $items,
        ];
        $this->boms[$bomId] = $bom;

        return $bom;
    }
}
