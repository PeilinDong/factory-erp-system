<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Inventory\InventoryService;
use Erp\Material\MaterialService;
use Erp\Warehouse\WarehouseService;

final class InventoryController
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly MaterialService $materials,
        private readonly WarehouseService $warehouses,
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
    ) {
    }

    public function index(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $materials = $this->materials->list();
        $warehouses = $this->warehouses->list();
        $message = $this->message();
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');
        $materialOptions = $this->materialOptions($materials);
        $warehouseOptions = $this->warehouseOptions($warehouses);
        $rows = $this->transactionRows($this->inventory->list(), $materials, $warehouses);

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">库存管理</p>
    <h1>库存流水</h1>
    <p class="muted">记录入库、出库和调整流水，形成库存余额和批次追溯的基础数据。</p>
    {$message}
    <section class="form-panel">
      <h2>新增库存流水</h2>
      <form class="master-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>物料
          <select name="material_id" required>{$materialOptions}</select>
        </label>
        <label>仓库
          <select name="warehouse_id" required>{$warehouseOptions}</select>
        </label>
        <label>业务类型
          <select name="transaction_type" required>
            <option value="inbound">入库</option>
            <option value="outbound">出库</option>
            <option value="adjustment">调整</option>
          </select>
        </label>
        <label>数量 <input name="quantity" required placeholder="10"></label>
        <label>单据号 <input name="reference_no" placeholder="PO-001"></label>
        <label>批次号 <input name="batch_no" placeholder="LOT-20260608-A"></label>
        <button type="submit">保存流水</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>流水列表</h2>
      <table>
        <thead><tr><th>物料</th><th>仓库</th><th>类型</th><th>数量</th><th>单据号</th><th>批次号</th><th>时间</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('库存流水', $body);
    }

    public function balances(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $rows = $this->balanceRows($this->inventory->stockBalances());

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">库存管理</p>
    <h1>库存余额</h1>
    <p class="muted">按物料和仓库汇总当前库存数量，用于发现短缺、负库存和补货需求。</p>
    <section class="table-panel">
      <h2>余额列表</h2>
      <table>
        <thead><tr><th>物料编码</th><th>物料名称</th><th>仓库编码</th><th>仓库名称</th><th>当前库存</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('库存余额', $body);
    }

    public function trace(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $materials = $this->materials->list();
        $warehouses = $this->warehouses->list();
        $batchNo = strtoupper(trim((string) ($_GET['batch_no'] ?? '')));
        $batchValue = htmlspecialchars($batchNo, ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/inventory/trace'), ENT_QUOTES, 'UTF-8');
        $rows = $this->transactionRows($this->inventory->traceBatch($batchNo), $materials, $warehouses);

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">库存追溯</p>
    <h1>批次追溯</h1>
    <p class="muted">输入批次号，查看该批次从入库到出库、调整和生产使用的全部库存流水。</p>
    <section class="filter-panel">
      <form class="search-form" method="get" action="{$action}">
        <label>批次号 <input name="batch_no" value="{$batchValue}" placeholder="LOT-20260608-A"></label>
        <button type="submit">查询</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>追溯结果</h2>
      <table>
        <thead><tr><th>物料</th><th>仓库</th><th>类型</th><th>数量</th><th>单据号</th><th>批次号</th><th>时间</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('批次追溯', $body);
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

        $input ??= $_POST;
        if (!$session->verifyCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->redirector()->redirect(App::url('/inventory?error=csrf'));
            return '';
        }

        try {
            $this->inventory->record($input);
            $this->redirector()->redirect(App::url('/inventory?created=1'));
        } catch (\InvalidArgumentException|\PDOException) {
            $this->redirector()->redirect(App::url('/inventory?error=validation'));
        }

        return '';
    }

    private function session(): SessionStore
    {
        return $this->session ?? new NativeSessionStore();
    }

    private function redirector(): Redirector
    {
        return $this->redirector ?? new NativeRedirector();
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">库存流水已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">库存流水保存失败，请检查物料、仓库、类型、数量和批次号。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string}> $materials
     */
    private function materialOptions(array $materials): string
    {
        if ($materials === []) {
            return '<option value="">请先维护物料</option>';
        }

        return implode('', array_map(static fn (array $material): string => sprintf(
            '<option value="%d">%s - %s</option>',
            (int) $material['id'],
            htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8'),
        ), $materials));
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
            '<option value="%d">%s - %s</option>',
            (int) $warehouse['id'],
            htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8'),
        ), $warehouses));
    }

    /**
     * @param array<int, array{id:int,material_id:int,warehouse_id:int,transaction_type:string,quantity:string,reference_no:string,batch_no:string,occurred_at:string}> $transactions
     * @param array<int, array{id:int,code:string,name:string}> $materials
     * @param array<int, array{id:int,code:string,name:string}> $warehouses
     */
    private function transactionRows(array $transactions, array $materials, array $warehouses): string
    {
        if ($transactions === []) {
            return '<tr><td colspan="7" class="empty">暂无库存流水。</td></tr>';
        }

        $materialNames = $this->namesById($materials);
        $warehouseNames = $this->namesById($warehouses);

        return implode('', array_map(function (array $transaction) use ($materialNames, $warehouseNames): string {
            $batch = htmlspecialchars($transaction['batch_no'], ENT_QUOTES, 'UTF-8');
            $batchCell = $batch === ''
                ? '-'
                : sprintf(
                    '<a href="%s">%s</a>',
                    htmlspecialchars(App::url('/inventory/trace?batch_no=' . rawurlencode($transaction['batch_no'])), ENT_QUOTES, 'UTF-8'),
                    $batch,
                );

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($materialNames[$transaction['material_id']] ?? (string) $transaction['material_id'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($warehouseNames[$transaction['warehouse_id']] ?? (string) $transaction['warehouse_id'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->typeLabel($transaction['transaction_type']), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($transaction['quantity'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($transaction['reference_no'], ENT_QUOTES, 'UTF-8'),
                $batchCell,
                htmlspecialchars($transaction['occurred_at'], ENT_QUOTES, 'UTF-8'),
            );
        }, $transactions));
    }

    /**
     * @param array<int, array{material_id:int,material_code:string,material_name:string,warehouse_id:int,warehouse_code:string,warehouse_name:string,quantity:string}> $balances
     */
    private function balanceRows(array $balances): string
    {
        if ($balances === []) {
            return '<tr><td colspan="5" class="empty">暂无库存余额，请先登记库存流水。</td></tr>';
        }

        return implode('', array_map(static fn (array $balance): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($balance['material_code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($balance['material_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($balance['warehouse_code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($balance['warehouse_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($balance['quantity'], ENT_QUOTES, 'UTF-8'),
        ), $balances));
    }

    /**
     * @param array<int, array{id:int,code:string,name:string}> $rows
     * @return array<int, string>
     */
    private function namesById(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['id']] = $row['code'] . ' - ' . $row['name'];
        }

        return $names;
    }

    private function typeLabel(string $type): string
    {
        return [
            'inbound' => '入库',
            'outbound' => '出库',
            'adjustment' => '调整',
        ][$type] ?? $type;
    }

    private function sidebar(): string
    {
        $home = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $materials = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehouses = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $boms = htmlspecialchars(App::url('/boms'), ENT_QUOTES, 'UTF-8');
        $purchases = htmlspecialchars(App::url('/purchases'), ENT_QUOTES, 'UTF-8');
        $workOrders = htmlspecialchars(App::url('/work-orders'), ENT_QUOTES, 'UTF-8');
        $inventory = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');
        $balances = htmlspecialchars(App::url('/inventory/balances'), ENT_QUOTES, 'UTF-8');
        $trace = htmlspecialchars(App::url('/inventory/trace'), ENT_QUOTES, 'UTF-8');
        $health = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<aside class="sidebar">
  <strong>Factory ERP</strong>
  <nav>
    <a href="{$home}">仪表盘</a>
    <a href="{$materials}">物料档案</a>
    <a href="{$warehouses}">仓库档案</a>
    <a href="{$boms}">BOM 管理</a>
    <a href="{$purchases}">采购订单</a>
    <a href="{$workOrders}">生产工单</a>
    <a href="{$inventory}">库存流水</a>
    <a href="{$balances}">库存余额</a>
    <a href="{$trace}">批次追溯</a>
    <a href="{$health}">健康检查</a>
  </nav>
</aside>
HTML;
    }
}
