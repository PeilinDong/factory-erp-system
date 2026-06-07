<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Bom\BomService;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\WorkOrder\WorkOrderService;

final class WorkOrderController
{
    public function __construct(
        private readonly WorkOrderService $orders,
        private readonly BomService $boms,
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
        $action = htmlspecialchars(App::url('/work-orders'), ENT_QUOTES, 'UTF-8');
        $bomOptions = $this->bomOptions($this->boms->list());
        $rows = $this->orderRows($this->orders->list());
        $message = $this->message();

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">生产执行</p>
    <h1>生产工单</h1>
    <p class="muted">根据 BOM 创建生产计划，系统会按计划数量计算组件需求，为后续领料、齐套和完工入库提供依据。</p>
    {$message}
    <section class="form-panel">
      <h2>新增工单</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>工单号 <input name="order_no" required placeholder="WO-001"></label>
        <label>选择 BOM
          <select name="bom_id" required>{$bomOptions}</select>
        </label>
        <label>计划数量 <input name="planned_quantity" required placeholder="10"></label>
        <label>计划完成日 <input name="due_date" placeholder="2026-07-20"></label>
        <button type="submit">保存工单</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>工单列表</h2>
      <table>
        <thead><tr><th>工单号</th><th>成品</th><th>计划数量</th><th>计划完成日</th><th>组件需求</th><th>状态</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('生产工单', $body);
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
            $this->redirector()->redirect(App::url('/work-orders?error=csrf'));
            return '';
        }

        try {
            $this->orders->create($input);
            $this->redirector()->redirect(App::url('/work-orders?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/work-orders?error=validation'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">工单已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">工单保存失败，请检查工单号、BOM、计划数量和日期。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,parent_material_code:string,parent_material_name:string,version:string}> $boms
     */
    private function bomOptions(array $boms): string
    {
        if ($boms === []) {
            return '<option value="">请先维护 BOM</option>';
        }

        return implode('', array_map(static fn (array $bom): string => sprintf(
            '<option value="%s">%s - %s / %s</option>',
            (string) (int) $bom['id'],
            htmlspecialchars($bom['parent_material_code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($bom['parent_material_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($bom['version'], ENT_QUOTES, 'UTF-8'),
        ), $boms));
    }

    /**
     * @param array<int, array{order_no:string,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}> $orders
     */
    private function orderRows(array $orders): string
    {
        if ($orders === []) {
            return '<tr><td colspan="6" class="empty">暂无工单，请先新增一条。</td></tr>';
        }

        return implode('', array_map(function (array $order): string {
            $requirements = $this->requirementText($order['requirements']);

            return sprintf(
                '<tr><td>%s</td><td>%s - %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($order['order_no'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['parent_material_code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['parent_material_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['planned_quantity'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['due_date'], ENT_QUOTES, 'UTF-8'),
                $requirements,
                $order['status'] === 'planned' ? '已计划' : htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'),
            );
        }, $orders));
    }

    /**
     * @param array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}> $requirements
     */
    private function requirementText(array $requirements): string
    {
        if ($requirements === []) {
            return '-';
        }

        return implode('<br>', array_map(static fn (array $requirement): string => sprintf(
            '%s - %s：%s',
            htmlspecialchars($requirement['component_material_code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($requirement['component_material_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($requirement['required_quantity'], ENT_QUOTES, 'UTF-8'),
        ), $requirements));
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
