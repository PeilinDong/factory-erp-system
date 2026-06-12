<?php

declare(strict_types=1);

namespace Erp\Purchase;

use Erp\Inventory\InventoryService;
use Erp\Material\MaterialRepository;
use Erp\Supplier\SupplierRepository;

final class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly MaterialRepository $materials,
        private readonly ?SupplierRepository $suppliers = null,
    ) {
    }

    /**
     * @return array<int, array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}>
     */
    public function list(): array
    {
        return array_map($this->enrich(...), $this->orders->list());
    }

    /**
     * @return null|array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}
     */
    public function find(int $id): ?array
    {
        $order = $this->orders->find($id);

        return $order === null ? null : $this->enrich($order);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}
     */
    public function create(array $data): array
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        $orderNo = strtoupper(trim((string) ($data['order_no'] ?? '')));
        $expectedDate = trim((string) ($data['expected_date'] ?? ''));
        $items = $this->normalizeItems($data);

        if ($supplierId > 0) {
            $supplier = $this->suppliers?->find($supplierId);
            if ($supplier === null || (int) $supplier['is_active'] !== 1) {
                throw new \InvalidArgumentException('supplier must exist and be active');
            }
            $supplierName = $supplier['name'];
        }

        if ($supplierName === '') {
            throw new \InvalidArgumentException('supplier name must not be empty');
        }

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,63}$/', $orderNo)) {
            throw new \InvalidArgumentException('purchase order number is invalid');
        }

        if ($expectedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedDate)) {
            throw new \InvalidArgumentException('expected date must use YYYY-MM-DD');
        }

        foreach ($items as $item) {
            if ($this->materialById($item['material_id']) === null) {
                throw new \InvalidArgumentException('material must exist');
            }
        }

        return $this->enrich($this->orders->create([
            'supplier_id' => $supplierId,
            'order_no' => $orderNo,
            'supplier_name' => $supplierName,
            'expected_date' => $expectedDate,
            'status' => 'draft',
            'items' => $items,
        ]));
    }

    /**
     * @return array<int, array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,batch_no:string,occurred_at:string}>
     */
    public function receive(
        int $id,
        int $warehouseId,
        string $batchNo,
        InventoryService $inventory,
        ?string $receivedQuantity = null,
    ): array
    {
        $order = $this->find($id);
        if ($order === null) {
            throw new \InvalidArgumentException('purchase order must exist');
        }

        if ($order['status'] === 'received') {
            throw new \InvalidArgumentException('purchase order is already received');
        }

        if ($warehouseId <= 0) {
            throw new \InvalidArgumentException('warehouse must exist');
        }

        if ($receivedQuantity !== null && count($order['items']) !== 1) {
            throw new \InvalidArgumentException('partial receipt currently supports single-item purchase orders');
        }

        $transactions = [];
        foreach ($order['items'] as $item) {
            $quantity = $this->receiptQuantity($order, $item, $inventory, $receivedQuantity);
            $transactions[] = $inventory->record([
                'material_id' => (string) $item['material_id'],
                'warehouse_id' => (string) $warehouseId,
                'transaction_type' => 'inbound',
                'quantity' => $quantity,
                'reference_no' => $order['order_no'],
                'batch_no' => $batchNo,
            ]);
        }

        $this->orders->setStatus($id, $this->receivedAll($order, $inventory) ? 'received' : 'partial');

        return $transactions;
    }

    /**
     * @return array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,batch_no:string,occurred_at:string}
     */
    public function returnToSupplier(
        int $id,
        int $warehouseId,
        string $batchNo,
        InventoryService $inventory,
        string $returnedQuantity,
    ): array {
        $order = $this->find($id);
        if ($order === null) {
            throw new \InvalidArgumentException('purchase order must exist');
        }

        if (count($order['items']) !== 1) {
            throw new \InvalidArgumentException('purchase return currently supports single-item purchase orders');
        }

        if ($warehouseId <= 0) {
            throw new \InvalidArgumentException('warehouse must exist');
        }

        $item = $order['items'][0];
        $quantity = $this->returnQuantity($order['order_no'], $item['material_id'], $inventory, $returnedQuantity);
        $transaction = $inventory->record([
            'material_id' => (string) $item['material_id'],
            'warehouse_id' => (string) $warehouseId,
            'transaction_type' => 'outbound',
            'quantity' => $quantity,
            'reference_no' => $order['order_no'],
            'batch_no' => $batchNo,
        ]);

        $this->orders->setStatus($id, $this->receivedAll($order, $inventory) ? 'received' : 'partial');

        return $transaction;
    }

    /**
     * @param array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>} $order
     * @param array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string} $item
     */
    private function receiptQuantity(array $order, array $item, InventoryService $inventory, ?string $receivedQuantity): string
    {
        $remaining = (float) $item['quantity'] - $this->receivedQuantityFor($order['order_no'], $item['material_id'], $inventory);
        if ($remaining <= 0.0) {
            throw new \InvalidArgumentException('purchase order is already received');
        }

        $rawQuantity = $receivedQuantity === null || trim($receivedQuantity) === ''
            ? (string) $remaining
            : trim($receivedQuantity);

        if (!preg_match('/^\d+(\.\d{1,6})?$/', $rawQuantity) || (float) $rawQuantity <= 0.0) {
            throw new \InvalidArgumentException('received quantity must be greater than zero');
        }

        if (((float) $rawQuantity - $remaining) > 0.000001) {
            throw new \InvalidArgumentException('received quantity exceeds remaining quantity');
        }

        return $this->normalizeNumber($rawQuantity);
    }

    /**
     * @param array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>} $order
     */
    private function receivedAll(array $order, InventoryService $inventory): bool
    {
        foreach ($order['items'] as $item) {
            $received = $this->receivedQuantityFor($order['order_no'], $item['material_id'], $inventory);
            if (((float) $item['quantity'] - $received) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    private function receivedQuantityFor(string $orderNo, int $materialId, InventoryService $inventory): float
    {
        $received = 0.0;
        foreach ($inventory->list() as $transaction) {
            if ($transaction['reference_no'] !== $orderNo
                || $transaction['material_id'] !== $materialId
            ) {
                continue;
            }

            $received += $transaction['transaction_type'] === 'outbound'
                ? -(float) $transaction['quantity']
                : (float) $transaction['quantity'];
        }

        return $received;
    }

    private function returnQuantity(string $orderNo, int $materialId, InventoryService $inventory, string $returnedQuantity): string
    {
        $received = $this->receivedQuantityFor($orderNo, $materialId, $inventory);
        if ($received <= 0.0) {
            throw new \InvalidArgumentException('purchase order has no received quantity to return');
        }

        $rawQuantity = trim($returnedQuantity);
        if (!preg_match('/^\d+(\.\d{1,6})?$/', $rawQuantity) || (float) $rawQuantity <= 0.0) {
            throw new \InvalidArgumentException('returned quantity must be greater than zero');
        }

        if (((float) $rawQuantity - $received) > 0.000001) {
            throw new \InvalidArgumentException('returned quantity exceeds received quantity');
        }

        return $this->normalizeNumber($rawQuantity);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{material_id:int,quantity:string,unit_price:string}>
     */
    private function normalizeItems(array $data): array
    {
        $rawItems = $data['items'] ?? null;
        if (!is_array($rawItems)) {
            $rawItems = [[
                'material_id' => $data['material_id'] ?? '',
                'quantity' => $data['quantity'] ?? '',
                'unit_price' => $data['unit_price'] ?? '',
            ]];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $materialId = (int) ($rawItem['material_id'] ?? 0);
            $quantity = trim((string) ($rawItem['quantity'] ?? ''));
            $unitPrice = trim((string) ($rawItem['unit_price'] ?? ''));

            if (!preg_match('/^\d+(\.\d{1,6})?$/', $quantity) || (float) $quantity <= 0.0) {
                throw new \InvalidArgumentException('quantity must be greater than zero');
            }

            if (!preg_match('/^\d+(\.\d{1,6})?$/', $unitPrice) || (float) $unitPrice < 0.0) {
                throw new \InvalidArgumentException('unit price must not be negative');
            }

            $items[] = [
                'material_id' => $materialId,
                'quantity' => $this->normalizeNumber($quantity),
                'unit_price' => $this->normalizeNumber($unitPrice),
            ];
        }

        if ($items === []) {
            throw new \InvalidArgumentException('purchase order must contain at least one item');
        }

        return $items;
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
     * @param array{id:int,order_no:string,supplier_name:string,expected_date:string,status:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,quantity:string,unit_price:string}>} $order
     * @return array{id:int,supplier_id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{id:int,purchase_order_id:int,material_id:int,material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}
     */
    private function enrich(array $order): array
    {
        $items = [];
        $total = 0.0;
        foreach ($order['items'] as $item) {
            $material = $this->materialById($item['material_id']);
            $lineAmount = (float) $item['quantity'] * (float) $item['unit_price'];
            $total += $lineAmount;
            $items[] = [
                'id' => $item['id'],
                'purchase_order_id' => $item['purchase_order_id'],
                'material_id' => $item['material_id'],
                'material_code' => (string) ($material['code'] ?? $item['material_id']),
                'material_name' => (string) ($material['name'] ?? ''),
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_amount' => $this->normalizeNumber((string) $lineAmount),
            ];
        }

        return [
            'id' => $order['id'],
            'supplier_id' => (int) ($order['supplier_id'] ?? 0),
            'order_no' => $order['order_no'],
            'supplier_name' => $order['supplier_name'],
            'expected_date' => $order['expected_date'],
            'status' => $order['status'],
            'total_amount' => $this->normalizeNumber((string) $total),
            'items' => $items,
        ];
    }

    private function normalizeNumber(string $number): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float) $number), '0'), '.');
    }
}
