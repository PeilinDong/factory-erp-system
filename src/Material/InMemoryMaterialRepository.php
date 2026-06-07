<?php

declare(strict_types=1);

namespace Erp\Material;

final class InMemoryMaterialRepository implements MaterialRepository
{
    /**
     * @var array<int, array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}>
     */
    private array $materials = [];

    public function list(): array
    {
        return array_values($this->materials);
    }

    public function search(string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return $this->list();
        }

        return array_values(array_filter($this->materials, static function (array $material) use ($query): bool {
            return str_contains(strtolower($material['code']), $query)
                || str_contains(strtolower($material['name']), $query)
                || str_contains(strtolower($material['specification']), $query);
        }));
    }

    public function find(int $id): ?array
    {
        return $this->materials[$id] ?? null;
    }

    public function create(array $data): array
    {
        $id = count($this->materials) + 1;
        $material = [
            'id' => $id,
            'code' => $data['code'],
            'name' => $data['name'],
            'specification' => $data['specification'] ?? '',
            'base_unit' => $data['base_unit'],
            'material_type' => $data['material_type'],
            'is_active' => 1,
        ];
        $this->materials[$id] = $material;

        return $material;
    }

    public function update(int $id, array $data): array
    {
        $material = $this->find($id);
        if ($material === null) {
            throw new \RuntimeException('material not found');
        }

        $material['code'] = $data['code'];
        $material['name'] = $data['name'];
        $material['specification'] = $data['specification'] ?? '';
        $material['base_unit'] = $data['base_unit'];
        $material['material_type'] = $data['material_type'];
        $this->materials[$id] = $material;

        return $material;
    }

    public function setActive(int $id, bool $active): array
    {
        $material = $this->find($id);
        if ($material === null) {
            throw new \RuntimeException('material not found');
        }

        $material['is_active'] = $active ? 1 : 0;
        $this->materials[$id] = $material;

        return $material;
    }
}
