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
     * @param array<string, string> $data
     * @return array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    public function create(array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $name = trim($data['name'] ?? '');
        $specification = trim($data['specification'] ?? '');
        $baseUnit = trim($data['base_unit'] ?? '');
        $materialType = trim($data['material_type'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $code)) {
            throw new \InvalidArgumentException('物料编码只能使用字母、数字、点、下划线和短横线，长度 2-64 位。');
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

        return $this->materials->create([
            'code' => $code,
            'name' => $name,
            'specification' => $specification,
            'base_unit' => $baseUnit,
            'material_type' => $materialType,
        ]);
    }
}

