<?php

declare(strict_types=1);

namespace Erp\Material;

final class MaterialService
{
    public function __construct(private readonly MaterialRepository $materials)
    {
    }

    /**
     * @return array<int, array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}>
     */
    public function list(): array
    {
        return $this->materials->list();
    }

    /**
     * @return array<int, array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}>
     */
    public function search(string $query): array
    {
        return $this->materials->search(trim($query));
    }

    /**
     * @return null|array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    public function find(int $id): ?array
    {
        return $this->materials->find($id);
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    public function create(array $data): array
    {
        return $this->materials->create($this->normalize($data));
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('物料不存在。');
        }

        return $this->materials->update($id, $this->normalize($data));
    }

    /**
     * @return array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    public function setActive(int $id, bool $active): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('物料不存在。');
        }

        return $this->materials->setActive($id, $active);
    }

    /**
     * @param array<string, string> $data
     * @return array{code:string,name:string,specification:string,base_unit:string,material_type:string}
     */
    private function normalize(array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $specification = trim($data['specification'] ?? '');
        $baseUnit = trim($data['base_unit'] ?? '');
        $materialType = trim($data['material_type'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $code)) {
            throw new \InvalidArgumentException('物料编码只能使用字母、数字、点、下划线和横线，长度 2-64 位。');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('物料名称不能为空。');
        }

        if ($baseUnit === '') {
            throw new \InvalidArgumentException('基本单位不能为空。');
        }

        if (!in_array($materialType, ['purchased', 'manufactured', 'outsourced'], true)) {
            throw new \InvalidArgumentException('物料属性必须是采购件、自制件或委外件。');
        }

        return [
            'code' => $code,
            'name' => $name,
            'specification' => $specification,
            'base_unit' => $baseUnit,
            'material_type' => $materialType,
        ];
    }
}
