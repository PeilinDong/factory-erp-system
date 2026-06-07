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
}

