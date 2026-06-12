<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\Sidebar;
use Erp\Core\View;
use Erp\Customer\CustomerService;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Material\MaterialService;
use Erp\Sales\SalesOrderService;

final class SalesOrderController
{
    public function __construct(
        private readonly SalesOrderService $orders,
        private readonly CustomerService $customers,
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
        $action = htmlspecialchars(App::url('/sales-orders'), ENT_QUOTES, 'UTF-8');
        $customerOptions = $this->customerOptions($this->customers->list());
        $materialOptions = $this->materialOptions($this->materials->list());
        $rows = $this->orderRows($this->orders->list());
        $message = $this->message();
        $sidebar = Sidebar::render();

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">销售管理</p>
    <h1>销售订单</h1>
    <p class="muted">记录客户订单、成品物料、数量和交期。当前早期版本先建立销售需求数据，后续会连接生产需求和交付状态。</p>
    {$message}
    <section class="form-panel">
      <h2>新增销售订单</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>销售单号 <input name="order_no" required placeholder="SO-001"></label>
        <label>客户
          <select name="customer_id" required>{$customerOptions}</select>
        </label>
        <label>成品物料
          <select name="product_material_id" required>{$materialOptions}</select>
        </label>
        <label>数量 <input name="quantity" required placeholder="20"></label>
        <label>交付日期 <input name="due_date" placeholder="2026-08-31"></label>
        <button type="submit">保存销售订单</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>销售订单列表</h2>
      <table>
        <thead><tr><th>单号</th><th>客户</th><th>成品</th><th>数量</th><th>交付日期</th><th>状态</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('销售订单', $body);
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
            $this->redirector()->redirect(App::url('/sales-orders?error=csrf'));
            return '';
        }

        try {
            $this->orders->create($input);
            $this->redirector()->redirect(App::url('/sales-orders?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/sales-orders?error=validation'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">销售订单已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">销售订单保存失败，请检查客户、成品物料、单号、数量和交期。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,is_active:int}> $customers
     */
    private function customerOptions(array $customers): string
    {
        $active = array_values(array_filter($customers, static fn (array $customer): bool => (int) $customer['is_active'] === 1));
        if ($active === []) {
            return '<option value="">请先维护客户</option>';
        }

        return implode('', array_map(static fn (array $customer): string => sprintf(
            '<option value="%s">%s - %s</option>',
            (string) (int) $customer['id'],
            htmlspecialchars($customer['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8'),
        ), $active));
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,material_type:string,is_active:int}> $materials
     */
    private function materialOptions(array $materials): string
    {
        $active = array_values(array_filter(
            $materials,
            static fn (array $material): bool => (int) $material['is_active'] === 1 && $material['material_type'] === 'manufactured',
        ));
        if ($active === []) {
            return '<option value="">请先维护自制件成品物料</option>';
        }

        return implode('', array_map(static fn (array $material): string => sprintf(
            '<option value="%s">%s - %s</option>',
            (string) (int) $material['id'],
            htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8'),
        ), $active));
    }

    /**
     * @param array<int, array{order_no:string,customer_name:string,product_material_code:string,product_material_name:string,quantity:string,due_date:string,status:string}> $orders
     */
    private function orderRows(array $orders): string
    {
        if ($orders === []) {
            return '<tr><td colspan="6" class="empty">暂无销售订单，请先新增一条。</td></tr>';
        }

        return implode('', array_map(static fn (array $order): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s - %s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($order['order_no'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($order['product_material_code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($order['product_material_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($order['quantity'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($order['due_date'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'),
        ), $orders));
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
