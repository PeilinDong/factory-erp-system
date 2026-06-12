<?php

declare(strict_types=1);

namespace Erp\Sales;

use Erp\Customer\CustomerRepository;
use Erp\Material\MaterialRepository;

final class SalesOrderService
{
    public function __construct(
        private readonly SalesOrderRepository $orders,
        private readonly CustomerRepository $customers,
        private readonly MaterialRepository $materials,
    ) {
    }

    /**
     * @return array<int, array{id:int,order_no:string,customer_id:int,customer_name:string,product_material_id:int,product_material_code:string,product_material_name:string,quantity:string,due_date:string,status:string}>
     */
    public function list(): array
    {
        return array_map($this->enrich(...), $this->orders->list());
    }

    /**
     * @return null|array{id:int,order_no:string,customer_id:int,customer_name:string,product_material_id:int,product_material_code:string,product_material_name:string,quantity:string,due_date:string,status:string}
     */
    public function find(int $id): ?array
    {
        $order = $this->orders->find($id);

        return $order === null ? null : $this->enrich($order);
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,order_no:string,customer_id:int,customer_name:string,product_material_id:int,product_material_code:string,product_material_name:string,quantity:string,due_date:string,status:string}
     */
    public function create(array $data): array
    {
        $orderNo = strtoupper(trim($data['order_no'] ?? ''));
        $customerId = (int) ($data['customer_id'] ?? 0);
        $productMaterialId = (int) ($data['product_material_id'] ?? 0);
        $quantity = trim($data['quantity'] ?? '');
        $dueDate = trim($data['due_date'] ?? '');

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $orderNo)) {
            throw new \InvalidArgumentException('sales order number is invalid');
        }

        $customer = $this->customers->find($customerId);
        if ($customer === null || (int) $customer['is_active'] !== 1) {
            throw new \InvalidArgumentException('customer must exist and be active');
        }

        $material = $this->materialById($productMaterialId);
        if ($material === null || (int) ($material['is_active'] ?? 1) !== 1) {
            throw new \InvalidArgumentException('product material must exist and be active');
        }

        if (!preg_match('/^\d+(\.\d{1,6})?$/', $quantity) || (float) $quantity <= 0.0) {
            throw new \InvalidArgumentException('sales order quantity must be greater than zero');
        }

        if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new \InvalidArgumentException('due date must use YYYY-MM-DD');
        }

        return $this->enrich($this->orders->create([
            'order_no' => $orderNo,
            'customer_id' => $customerId,
            'product_material_id' => $productMaterialId,
            'quantity' => $this->normalizeQuantity($quantity),
            'due_date' => $dueDate,
            'status' => 'draft',
        ]));
    }

    /**
     * @return null|array{id:int,code:string,name:string,is_active:int}
     */
    private function materialById(int $id): ?array
    {
        foreach ($this->materials->list() as $material) {
            if ((int) $material['id'] === $id) {
                return [
                    'id' => (int) $material['id'],
                    'code' => (string) $material['code'],
                    'name' => (string) $material['name'],
                    'is_active' => (int) ($material['is_active'] ?? 1),
                ];
            }
        }

        return null;
    }

    /**
     * @param array{id:int,order_no:string,customer_id:int,product_material_id:int,quantity:string,due_date:string,status:string} $order
     * @return array{id:int,order_no:string,customer_id:int,customer_name:string,product_material_id:int,product_material_code:string,product_material_name:string,quantity:string,due_date:string,status:string}
     */
    private function enrich(array $order): array
    {
        $customer = $this->customers->find($order['customer_id']);
        $material = $this->materialById($order['product_material_id']);

        return [
            'id' => $order['id'],
            'order_no' => $order['order_no'],
            'customer_id' => $order['customer_id'],
            'customer_name' => (string) ($customer['name'] ?? $order['customer_id']),
            'product_material_id' => $order['product_material_id'],
            'product_material_code' => (string) ($material['code'] ?? $order['product_material_id']),
            'product_material_name' => (string) ($material['name'] ?? ''),
            'quantity' => $order['quantity'],
            'due_date' => $order['due_date'],
            'status' => $order['status'],
        ];
    }

    private function normalizeQuantity(string $quantity): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $quantity), '0'), '.');
    }
}
