<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Material\MaterialService;
use Erp\Purchase\PurchaseOrderService;

final class PurchaseController
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly MaterialService $materials,
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

        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/purchases'), ENT_QUOTES, 'UTF-8');
        $materialOptions = $this->materialOptions($this->materials->list());
        $rows = $this->orderRows($this->orders->list());
        $message = $this->message();

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">采购管理</p>
    <h1>采购订单</h1>
    <p class="muted">记录供应商、采购物料、数量和单价，为后续到货入库、采购成本和缺料建议提供基础数据。</p>
    {$message}
    <section class="form-panel">
      <h2>新增采购单</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>采购单号 <input name="order_no" required placeholder="PO-001"></label>
        <label>供应商 <input name="supplier_name" required placeholder="上海供应商"></label>
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
        <thead><tr><th>单号</th><th>供应商</th><th>预计到货</th><th>物料</th><th>数量</th><th>单价</th><th>金额</th><th>状态</th></tr></thead>
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

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">采购单已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">采购单保存失败，请检查供应商、单号、物料、数量和单价。</p>';
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

    /**
     * @param array<int, array{order_no:string,supplier_name:string,expected_date:string,status:string,total_amount:string,items:array<int, array{material_code:string,material_name:string,quantity:string,unit_price:string,line_amount:string}>}> $orders
     */
    private function orderRows(array $orders): string
    {
        if ($orders === []) {
            return '<tr><td colspan="8" class="empty">暂无采购单，请先新增一条。</td></tr>';
        }

        $rows = [];
        foreach ($orders as $order) {
            foreach ($order['items'] as $item) {
                $rows[] = sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s - %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($order['order_no'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($order['supplier_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($order['expected_date'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['material_code'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['material_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['unit_price'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['line_amount'], ENT_QUOTES, 'UTF-8'),
                    $order['status'] === 'draft' ? '草稿' : htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'),
                );
            }
        }

        return implode('', $rows);
    }

    private function sidebar(): string
    {
        $home = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $materials = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehouses = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $boms = htmlspecialchars(App::url('/boms'), ENT_QUOTES, 'UTF-8');
        $purchases = htmlspecialchars(App::url('/purchases'), ENT_QUOTES, 'UTF-8');
        $inventory = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');
        $balances = htmlspecialchars(App::url('/inventory/balances'), ENT_QUOTES, 'UTF-8');
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
    <a href="{$inventory}">库存流水</a>
    <a href="{$balances}">库存余额</a>
    <a href="{$health}">健康检查</a>
  </nav>
</aside>
HTML;
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
