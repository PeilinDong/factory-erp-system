<?php

declare(strict_types=1);

namespace Erp\Inventory;

use PDO;

final class PdoInventoryTransactionRepository implements InventoryTransactionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, material_id, warehouse_id, transaction_type, quantity, reference_no, batch_no, occurred_at
             FROM inventory_transactions
             ORDER BY id DESC
             LIMIT 100'
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'material_id' => (int) $row['material_id'],
            'warehouse_id' => (int) $row['warehouse_id'],
            'transaction_type' => (string) $row['transaction_type'],
            'quantity' => self::formatQuantity((string) $row['quantity']),
            'reference_no' => (string) ($row['reference_no'] ?? ''),
            'batch_no' => (string) ($row['batch_no'] ?? ''),
            'occurred_at' => (string) $row['occurred_at'],
        ], $statement->fetchAll());
    }

    public function create(array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO inventory_transactions
                (material_id, warehouse_id, transaction_type, quantity, reference_no, batch_no, occurred_at)
             VALUES
                (:material_id, :warehouse_id, :transaction_type, :quantity, :reference_no, :batch_no, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'material_id' => $data['material_id'],
            'warehouse_id' => $data['warehouse_id'],
            'transaction_type' => $data['transaction_type'],
            'quantity' => $data['quantity'],
            'reference_no' => $data['reference_no'],
            'batch_no' => $data['batch_no'],
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'material_id' => $data['material_id'],
            'warehouse_id' => $data['warehouse_id'],
            'transaction_type' => $data['transaction_type'],
            'quantity' => $data['quantity'],
            'reference_no' => $data['reference_no'],
            'batch_no' => $data['batch_no'],
            'occurred_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function formatQuantity(string $quantity): string
    {
        return rtrim(rtrim($quantity, '0'), '.');
    }
}
