<?php

declare(strict_types=1);

namespace Erp\Material;

interface MaterialRepository
{
    /**
     * @return array<int, array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}>
     */
    public function list(): array;

    /**
     * @param array{code:string,name:string,specification:string,base_unit:string,material_type:string} $data
     * @return array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}
     */
    public function create(array $data): array;
}

