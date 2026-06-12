<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\PermissionService;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\Sidebar;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Inventory\InventoryService;
use Erp\Material\MaterialService;
use Erp\Purchase\PurchaseOrderService;
use Erp\Supplier\SupplierService;
use Erp\Warehouse\WarehouseService;

final class PurchaseController
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly MaterialService $materials,
        private readonly WarehouseService $warehouses,
        private readonly InventoryService $inventory,
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
        private readonly ?SupplierService $suppliers = null,
    ) {
    }

    public function index(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/purchases'), ENT_QUOTES, 'UTF-8');
        $materialOptions = $this->materialOptions($this->materials->list());
        $supplierField = $this->supplierField();
        $warehouseOptions = $this->warehouseOptions($this->warehouses->list());
        $rows = $this->orderRows($this->orders->list(), $session->csrfToken(), $warehouseOptions);
        $message = $this->message();
        $sidebar = Sidebar::render();

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">采购管理</p>
    <h1>采购订单</h1>
    <p class="muted">记录供应商、采购物料、数量和单价；收货后可直接生成库存入库流水和批次追溯数据。</p>
    {$message}
    <section class="form-panel">
      <h2>新增采购单</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>采购单号 <input name="order_no" required placeholder="PO-001"></label>
        {$supplierField}
        <label>预计到货日 <input name="expected_date" placeholder="2026-07-01"></label>
        <label>采购物料
          <select name="material_id" required>{$materialOptions}</select>
        </label>
        <label>数量 <input name="quantity" required placeholder="10"></label>
        <label>单价 <input name="unit_price" required placeholder="12.50"></label>
        <button type="submit">保存采购单</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>采购单列表</h2>
      <table>
        <thead><tr><th>单号</th><th>供应商</th><th>预计到货</th><th>物料</th><th>数量</th><th>单价</th><th>金额</th><th>状态</th><th>收货</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('采购订单', $body);
    }

    /**
     * @param null|array<string, string> $input
     */
    public function store(?array $input = null): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        if (!PermissionService::can($session->user(), 'purchase.manage')) {
            $this->redirector()->redirect(App::url('/purchases?error=forbidden'));
            return '';
        }

        $input ??= $_POST;
        if (!$session->verifyCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->redirector()->redirect(App::url('/purchases?error=csrf'));
            return '';
        }

        try {
            $this->orders->create($input);
            $this->redirector()->redirect(App::url('/purchases?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/purchases?error=validation'));
        }

        return '';
    }

    /**
     * @param null|array<string, string> $input
     */
    public function receive(?array $input = null): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        if (!PermissionService::can($session->user(), 'purchase.receive')) {
            $this->redirector()->redirect(App::url('/purchases?error=forbidden'));
            return '';
        }

        $input ??= $_POST;
        if (!$session->verifyCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->redirector()->redirect(App::url('/purchases?error=csrf'));
            return '';
        }

        try {
            $this->orders->receive(
                (int) ($input['id'] ?? 0),
                (int) ($input['warehouse_id'] ?? 0),
                (string) ($input['batch_no'] ?? ''),
                $this->inventory,
            );
            $this->redirector()->redirect(App::url('/purchases?received=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/purchases?error=receive'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">采购单已保存。</p>';
        }

        if (isset($_GET['received'])) {
            return '<p class="success">采购收货入库已生成。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">采购单处理失败，请检查供应商、单号、物料、数量、单价、仓库和批次号。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,is_active:int}> $materials
     */
    private function materialOptions(array $materials): string
    {
        if ($materials === []) {
            return '<option value="">请先维护物料</option>';
        }

        return implode('', array_map(static fn (array $material): string => sprintf(
            '<option value="%s">%s - %s</option>',
            (string) (int) $material['id'],
            htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8'),
        ), $materials));
    }

    private function supplierField(): string
    {
        if ($this->suppliers === null) {
            return '<label>供应商 <input name="supplier_name" required placeholder="上海供应商"></label>';
        }

        $suppliers = array_values(array_filter(
            $this->suppliers->list(),
            static fn (array $supplier): bool => (int) $supplier['is_active'] === 1,
        ));

        if ($suppliers === []) {
            return '<label>供应商 <input name="supplier_name" required placeholder="请先维护供应商档案"></label>';
        }

        $options = implode('', array_map(static fn (array $supplier): string => sprintf(
            '<option value="%s">%s - %s</option>',
            (string) (int) $supplier['id'],
            htmlspecialchars($supplier['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($supplier['name'], ENT_QUOTES, 'UTF-8'),
        ), $suppliers));

        return '<label>供应商 <select name="supplier_id" required>' . $options . '</select></label>';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string}> $warehouses
     */
    private function warehouseOptions(array $warehouses): string
    {
        if ($warehouses === []) {
            return '<option value="">请先维护仓库</option>';
        }

        return implode('', array_map(static fn (array $warehouse): string => sprintf(
            '<option value="%s">%s - %s</option>',
            (string) (int) $warehouse['id'],
            htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8'),
        ), $warehouses));
    }

    /**
     * @param array<int, array{id:int,order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}> $orders
     */
    private function orderRows(array $orders, string $csrfToken, string $warehouseOptions): string
    {
        if ($orders === []) {
            return '<tr><td colspan="9" class="empty">暂无采购单，请先新增一条。</td></tr>';
        }

        $rows = [];
        foreach ($orders as $order) {
            foreach ($order['items'] as $item) {
                $rows[] = sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s - %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($order['order_no'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($order['supplier_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($order['expected_date'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['material_code'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['material_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['unit_price'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['line_amount'], ENT_QUOTES, 'UTF-8'),
                    $this->statusLabel($order['status']),
                    $this->receiveForm($order, $csrfToken, $warehouseOptions),
                );
            }
        }

        return implode('', $rows);
    }

    /**
     * @param array{id:int,status:string,order_no:string} $order
     */
    private function receiveForm(array $order, string $csrfToken, string $warehouseOptions): string
    {
        if ($order['status'] === 'received') {
            return '<span class="muted">已收货</span>';
        }

        $action = htmlspecialchars(App::url('/purchases/receive'), ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
        $id = (string) (int) $order['id'];
        $batch = htmlspecialchars('LOT-' . $order['order_no'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
<form class="inline-form" method="post" action="{$action}">
  <input type="hidden" name="csrf_token" value="{$csrf}">
  <input type="hidden" name="id" value="{$id}">
  <select name="warehouse_id" required>{$warehouseOptions}</select>
  <input name="batch_no" required value="{$batch}" placeholder="LOT-PO-001">
  <button type="submit">收货入库</button>
</form>
HTML;
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft' => '草稿',
            'received' => '已收货',
        ][$status] ?? htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
    }

    private function session(): SessionStore
    {
        return $this->session ?? new NativeSessionStore();
    }

    private function redirector(): Redirector
    {
        return $this->redirector ?? new NativeRedirector();
    }
}
