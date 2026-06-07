<?php

declare(strict_types=1);

namespace Erp\Bom;

use Erp\Material\MaterialRepository;

final class BomService
{
    public function __construct(
        private readonly BomRepository $boms,
        private readonly MaterialRepository $materials,
    ) {
    }

    /**
     * @return array<int, array{id:int,parent_material_id:int,parent_material_code:string,parent_material_name:string,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,component_material_code:string,component_material_name:string,quantity:string,scrap_rate:string}>}>
     */
    public function list(): array
    {
        return array_map($this->enrich(...), $this->boms->list());
    }

    /**
     * @return null|array{id:int,parent_material_id:int,parent_material_code:string,parent_material_name:string,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,component_material_code:string,component_material_name:string,quantity:string,scrap_rate:string}>}
     */
    public function find(int $id): ?array
    {
        $bom = $this->boms->find($id);

        return $bom === null ? null : $this->enrich($bom);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,parent_material_id:int,parent_material_code:string,parent_material_name:string,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,component_material_code:string,component_material_name:string,quantity:string,scrap_rate:string}>}
     */
    public function create(array $data): array
    {
        $parentId = (int) ($data['parent_material_id'] ?? 0);
        $version = trim((string) ($data['version'] ?? ''));
        $items = $this->normalizeItems($data);

        if (!$this->materialExists($parentId)) {
            throw new \InvalidArgumentException('parent material must exist');
        }

        if ($version === '') {
            throw new \InvalidArgumentException('bom version must not be empty');
        }

        if ($items === []) {
            throw new \InvalidArgumentException('bom must contain at least one component');
        }

        foreach ($items as $item) {
            if ($item['component_material_id'] === $parentId) {
                throw new \InvalidArgumentException('component material cannot be the parent material');
            }
            if (!$this->materialExists($item['component_material_id'])) {
                throw new \InvalidArgumentException('component material must exist');
            }
        }

        return $this->enrich($this->boms->create([
            'parent_material_id' => $parentId,
            'version' => $version,
            'items' => $items,
        ]));
    }

    /**
     * @return array<int, array{component_material_id:int,component_material_code:string,component_material_name:string,required_quantity:string}>
     */
    public function requirements(int $bomId, string $planQuantity): array
    {
        $bom = $this->find($bomId);
        if ($bom === null) {
            throw new \InvalidArgumentException('bom must exist');
        }

        if (!preg_match('/^\d+(\.\d{1,6})?$/', $planQuantity) || (float) $planQuantity <= 0.0) {
            throw new \InvalidArgumentException('plan quantity must be greater than zero');
        }

        $requirements = [];
        foreach ($bom['items'] as $item) {
            $quantity = (float) $planQuantity * (float) $item['quantity'] * (1 + ((float) $item['scrap_rate'] / 100));
            $requirements[] = [
                'component_material_id' => $item['component_material_id'],
                'component_material_code' => $item['component_material_code'],
                'component_material_name' => $item['component_material_name'],
                'required_quantity' => $this->normalizeQuantity((string) $quantity),
            ];
        }

        return $requirements;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{component_material_id:int,quantity:string,scrap_rate:string}>
     */
    private function normalizeItems(array $data): array
    {
        $rawItems = $data['items'] ?? null;
        if (!is_array($rawItems)) {
            $rawItems = [[
                'component_material_id' => $data['component_material_id'] ?? '',
                'quantity' => $data['quantity'] ?? '',
                'scrap_rate' => $data['scrap_rate'] ?? '0',
            ]];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $componentId = (int) ($rawItem['component_material_id'] ?? 0);
            $quantity = trim((string) ($rawItem['quantity'] ?? ''));
            $scrapRate = trim((string) ($rawItem['scrap_rate'] ?? '0'));

            if (!preg_match('/^\d+(\.\d{1,6})?$/', $quantity) || (float) $quantity <= 0.0) {
                throw new \InvalidArgumentException('component quantity must be greater than zero');
            }

            if (!preg_match('/^\d+(\.\d{1,4})?$/', $scrapRate) || (float) $scrapRate < 0.0 || (float) $scrapRate > 100.0) {
                throw new \InvalidArgumentException('scrap rate must be between 0 and 100');
            }

            $items[] = [
                'component_material_id' => $componentId,
                'quantity' => $this->normalizeQuantity($quantity),
                'scrap_rate' => $this->normalizeQuantity($scrapRate),
            ];
        }

        return $items;
    }

    private function materialExists(int $id): bool
    {
        return $this->materialById($id) !== null;
    }

    /**
     * @return null|array{id:int,code:string,name:string}
     */
    private function materialById(int $id): ?array
    {
        foreach ($this->materials->list() as $material) {
            if ((int) $material['id'] === $id) {
                return [
                    'id' => (int) $material['id'],
                    'code' => (string) $material['code'],
                    'name' => (string) $material['name'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{id:int,parent_material_id:int,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,quantity:string,scrap_rate:string}>} $bom
     * @return array{id:int,parent_material_id:int,parent_material_code:string,parent_material_name:string,version:string,is_active:int,items:array<int, array{id:int,bom_id:int,component_material_id:int,component_material_code:string,component_material_name:string,quantity:string,scrap_rate:string}>}
     */
    private function enrich(array $bom): array
    {
        $parent = $this->materialById($bom['parent_material_id']);
        $items = [];
        foreach ($bom['items'] as $item) {
            $component = $this->materialById($item['component_material_id']);
            $items[] = [
                'id' => $item['id'],
                'bom_id' => $item['bom_id'],
                'component_material_id' => $item['component_material_id'],
                'component_material_code' => (string) ($component['code'] ?? $item['component_material_id']),
                'component_material_name' => (string) ($component['name'] ?? ''),
                'quantity' => $item['quantity'],
                'scrap_rate' => $item['scrap_rate'],
            ];
        }

        return [
            'id' => $bom['id'],
            'parent_material_id' => $bom['parent_material_id'],
            'parent_material_code' => (string) ($parent['code'] ?? $bom['parent_material_id']),
            'parent_material_name' => (string) ($parent['name'] ?? ''),
            'version' => $bom['version'],
            'is_active' => $bom['is_active'],
            'items' => $items,
        ];
    }

    private function normalizeQuantity(string $quantity): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $quantity), '0'), '.');
    }
}
