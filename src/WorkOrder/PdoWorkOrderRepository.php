<?php

declare(strict_types=1);

namespace Erp\WorkOrder;

use PDO;

final class PdoWorkOrderRepository implements WorkOrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, order_no, bom_id, planned_quantity, due_date, status
             FROM work_orders
             ORDER BY id DESC
             LIMIT 100'
        );

        return array_map($this->mapRow(...), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, order_no, bom_id, planned_quantity, due_date, status
             FROM work_orders
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
            'INSERT INTO work_orders (order_no, bom_id, planned_quantity, due_date, status, created_at)
             VALUES (:order_no, :bom_id, :planned_quantity, :due_date, :status, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'order_no' => $data['order_no'],
            'bom_id' => $data['bom_id'],
            'planned_quantity' => $data['planned_quantity'],
            'due_date' => $data['due_date'] !== '' ? $data['due_date'] : null,
            'status' => $data['status'],
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'order_no' => $data['order_no'],
            'bom_id' => $data['bom_id'],
            'planned_quantity' => $data['planned_quantity'],
            'due_date' => $data['due_date'],
            'status' => $data['status'],
        ];
    }

    public function setStatus(int $id, string $status): array
    {
        $statement = $this->pdo->prepare('UPDATE work_orders SET status = :status WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('work order not found');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,order_no:string,bom_id:int,planned_quantity:string,due_date:string,status:string}
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_no' => (string) $row['order_no'],
            'bom_id' => (int) $row['bom_id'],
            'planned_quantity' => rtrim(rtrim((string) $row['planned_quantity'], '0'), '.'),
            'due_date' => (string) ($row['due_date'] ?? ''),
            'status' => (string) $row['status'],
        ];
    }
}
