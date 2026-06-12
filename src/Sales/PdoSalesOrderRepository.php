<?php

declare(strict_types=1);

namespace Erp\Sales;

use PDO;

final class PdoSalesOrderRepository implements SalesOrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, order_no, customer_id, product_material_id, quantity, due_date, status
             FROM sales_orders
             ORDER BY id DESC
             LIMIT 100'
        );

        return array_map($this->mapRow(...), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, order_no, customer_id, product_material_id, quantity, due_date, status
             FROM sales_orders
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function create(array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sales_orders (order_no, customer_id, product_material_id, quantity, due_date, status, created_at)
             VALUES (:order_no, :customer_id, :product_material_id, :quantity, :due_date, :status, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'order_no' => $data['order_no'],
            'customer_id' => $data['customer_id'],
            'product_material_id' => $data['product_material_id'],
            'quantity' => $data['quantity'],
            'due_date' => $data['due_date'] !== '' ? $data['due_date'] : null,
            'status' => $data['status'],
        ]);

        return $this->find((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('sales order not found');
    }

    public function setStatus(int $id, string $status): array
    {
        $statement = $this->pdo->prepare('UPDATE sales_orders SET status = :status WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('sales order not found');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string}
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_no' => (string) $row['order_no'],
            'customer_id' => (int) $row['customer_id'],
            'product_material_id' => (int) $row['product_material_id'],
            'quantity' => rtrim(rtrim((string) $row['quantity'], '0'), '.'),
            'due_date' => (string) ($row['due_date'] ?? ''),
            'status' => (string) $row['status'],
        ];
    }
}
