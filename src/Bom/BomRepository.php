<?php

declare(strict_types=1);

namespace Erp\Bom;

interface BomRepository
{
    /**
     * @return array<int, array{id:int,project_code:string,project_name:string,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>}>
     */
    public function list(): array;

    /**
     * @return null|array{id:int,project_code:string,project_name:string,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>}
     */
    public function find(int $id): ?array;

    /**
     * @param array{project_code:string,project_name:string,parent_material_id:int,version:string,items:array<int, array{component_material_id:int,quantity:string,scrap_rate:string}>} $data
     * @return array{id:int,project_code:string,project_name:string,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>}
     */
    public function create(array $data): array;
}
