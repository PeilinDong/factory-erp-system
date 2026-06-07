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

    private function normalizeQuantity(string $quantity): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $quantity), '0'), '.');
    }
}
