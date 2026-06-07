<?php

declare(strict_types=1);

namespace Erp\Inventory;

use Erp\Material\MaterialRepository;
use Erp\Warehouse\WarehouseRepository;

final class InventoryService
{
    private const TYPES = ['inbound', 'outbound', 'adjustment'];

    public function __construct(
        private readonly InventoryTransactionRepository $transactions,
        private readonly MaterialRepository $materials,
        private readonly WarehouseRepository $warehouses,
    ) {
    }

    /**
     * @return array<int, array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,occurred_at:string}>
     */
    public function list(): array
    {
        return $this->transactions->list();
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,occurred_at:string}
     */
    public function record(array $data): array
    {
        $materialId = (int) ($data['material_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $type = trim($data['transaction_type'] ?? '');
        $quantity = trim($data['quantity'] ?? '');
        $referenceNo = trim($data['reference_no'] ?? '');

        if (!$this->existsIn($materialId, $this->materials->list())) {
            throw new \InvalidArgumentException('material must exist');
        }

        if (!$this->existsIn($warehouseId, $this->warehouses->list())) {
            throw new \InvalidArgumentException('warehouse must exist');
        }

        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('transaction type is invalid');
        }

        if (!preg_match('/^\d+(\.\d{1,6})?$/', $quantity) || (float) $quantity <= 0.0) {
            throw new \InvalidArgumentException('quantity must be greater than zero');
        }

        return $this->transactions->create([
            'material_id' => $materialId,
            'warehouse_id' => $warehouseId,
            'transaction_type' => $type,
            'quantity' => $this->normalizeQuantity($quantity),
            'reference_no' => $referenceNo,
        ]);
    }

    public function stockBalance(int $materialId, int $warehouseId): string
    {
        $balance = 0.0;
        foreach ($this->transactions->list() as $transaction) {
            if ($transaction['material_id'] !== $materialId || $transaction['warehouse_id'] !== $warehouseId) {
                continue;
            }

            $quantity = (float) $transaction['quantity'];
            if ($transaction['transaction_type'] === 'outbound') {
                $balance -= $quantity;
                continue;
            }
            $balance += $quantity;
        }

        return $this->normalizeQuantity((string) $balance);
    }

    /**
     * @return array<int, array{material_id:int,material_code:string,material_name:string,warehouse_id:int,warehouse_code:string,warehouse_name:string,quantity:string}>
     */
    public function stockBalances(): array
    {
        $materials = $this->rowsById($this->materials->list());
        $warehouses = $this->rowsById($this->warehouses->list());
        $balances = [];

        foreach ($this->transactions->list() as $transaction) {
            $key = $transaction['material_id'] . ':' . $transaction['warehouse_id'];
            $balances[$key] ??= [
                'material_id' => $transaction['material_id'],
                'warehouse_id' => $transaction['warehouse_id'],
                'quantity' => 0.0,
            ];

            $quantity = (float) $transaction['quantity'];
            $balances[$key]['quantity'] += $transaction['transaction_type'] === 'outbound'
                ? -$quantity
                : $quantity;
        }

        $rows = [];
        foreach ($balances as $balance) {
            if (abs($balance['quantity']) < 0.000001) {
                continue;
            }

            $material = $materials[$balance['material_id']] ?? null;
            $warehouse = $warehouses[$balance['warehouse_id']] ?? null;
            $rows[] = [
                'material_id' => $balance['material_id'],
                'material_code' => (string) ($material['code'] ?? $balance['material_id']),
                'material_name' => (string) ($material['name'] ?? ''),
                'warehouse_id' => $balance['warehouse_id'],
                'warehouse_code' => (string) ($warehouse['code'] ?? $balance['warehouse_id']),
                'warehouse_name' => (string) ($warehouse['name'] ?? ''),
                'quantity' => $this->normalizeQuantity((string) $balance['quantity']),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$left['material_code'], $left['warehouse_code']]
            <=> [$right['material_code'], $right['warehouse_code']]);

        return $rows;
    }

    /**
     * @param array<int, array{id:int}> $rows
     */
    private function existsIn(int $id, array $rows): bool
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{id:int}> $rows
     * @return array<int, array{id:int}>
     */
    private function rowsById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        return $indexed;
    }

    private function normalizeQuantity(string $quantity): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $quantity), '0'), '.');
    }
}
