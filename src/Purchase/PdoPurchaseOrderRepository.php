<?php

declare(strict_types=1);

namespace Erp\Purchase;

use PDO;

final class PdoPurchaseOrderRepository implements PurchaseOrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, order_no, supplier_name, expected_date, status
             FROM purchase_orders
             ORDER BY id DESC
             LIMIT 100'
        );

        $orders = array_map($this->mapOrderRow(...), $statement->fetchAll());
        foreach ($orders as $index => $order) {
            $orders[$index]['items'] = $this->itemsFor((int) $order['id']);
        }

        return $orders;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, order_no, supplier_name, expected_date, status
             FROM purchase_orders
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        $order = $this->mapOrderRow($row);
        $order['items'] = $this->itemsFor($id);

        return $order;
    }

    public function create(array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO purchase_orders (order_no, supplier_name, expected_date, status, created_at)
                 VALUES (:order_no, :supplier_name, :expected_date, :status, CURRENT_TIMESTAMP)'
            );
            $statement->execute([
                'order_no' => $data['order_no'],
                'supplier_name' => $data['supplier_name'],
                'expected_date' => $data['expected_date'] !== '' ? $data['expected_date'] : null,
                'status' => $data['status'],
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            $itemStatement = $this->pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, material_id, quantity, unit_price)
                 VALUES (:purchase_order_id, :material_id, :quantity, :unit_price)'
            );
            foreach ($data['items'] as $item) {
                $itemStatement->execute([
                    'purchase_order_id' => $orderId,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $orders = $this->list();
        foreach ($orders as $order) {
            if ($order['id'] === $orderId) {
                return $order;
            }
        }

        throw new \RuntimeException('purchase order not found');
    }

    public function setStatus(int $id, string $status): array
    {
        $statement = $this->pdo->prepare('UPDATE purchase_orders SET status = :status WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        $order = $this->find($id);
        if ($order === null) {
            throw new \InvalidArgumentException('purchase order must exist');
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>}
     */
    private function mapOrderRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_no' => (string) $row['order_no'],
            'supplier_name' => (string) $row['supplier_name'],
            'expected_date' => (string) ($row['expected_date'] ?? ''),
            'status' => (string) $row['status'],
            'items' => [],
        ];
    }

    /**
     * @return array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>
     */
    private function itemsFor(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, purchase_order_id, material_id, quantity, unit_price
             FROM purchase_order_items
             WHERE purchase_order_id = :purchase_order_id
             ORDER BY id ASC'
        );
        $statement->execute(['purchase_order_id' => $orderId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'purchase_order_id' => (int) $row['purchase_order_id'],
            'material_id' => (int) $row['material_id'],
            'quantity' => rtrim(rtrim((string) $row['quantity'], '0'), '.'),
            'unit_price' => rtrim(rtrim((string) $row['unit_price'], '0'), '.'),
        ], $statement->fetchAll());
    }
}
