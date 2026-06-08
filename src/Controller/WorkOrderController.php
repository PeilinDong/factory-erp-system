<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Bom\BomService;
use Erp\Core\App;
use Erp\Core\Sidebar;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Inventory\InventoryService;
use Erp\Warehouse\WarehouseService;
use Erp\WorkOrder\WorkOrderService;

final class WorkOrderController
{
    public function __construct(
        private readonly WorkOrderService $orders,
        private readonly BomService $boms,
        private readonly WarehouseService $warehouses,
        private readonly InventoryService $inventory,
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
        $warehouseOptions = $this->warehouseOptions($this->warehouses->list());
        $rows = $this->orderRows($this->orders->list(), $session->csrfToken(), $warehouseOptions);
        $message = $this->message();
        $sidebar = Sidebar::render();

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">生产执行</p>
    <h1>生产工单</h1>
    <p class="muted">根据 BOM 创建生产计划，按计划数量计算组件需求，并支持领料出库和成品完工入库。</p>
    {$message}
    <section class="form-panel">
      <h2>新增工单</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>工单编号 <input name="order_no" required placeholder="WO-001"></label>
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
        <thead><tr><th>工单编号</th><th>成品</th><th>计划数量</th><th>计划完成日</th><th>组件需求</th><th>状态</th><th>操作</th></tr></thead>
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

    /**
     * @param null|array<string, string> $input
     */
    public function issue(?array $input = null): string
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
            $this->orders->issueMaterials(
                (int) ($input['id'] ?? 0),
                (int) ($input['warehouse_id'] ?? 0),
                $this->inventory,
            );
            $this->redirector()->redirect(App::url('/work-orders?issued=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/work-orders?error=issue'));
        }

        return '';
    }

    /**
     * @param null|array<string, string> $input
     */
    public function complete(?array $input = null): string
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
            $this->orders->complete(
                (int) ($input['id'] ?? 0),
                (int) ($input['warehouse_id'] ?? 0),
                $this->inventory,
            );
            $this->redirector()->redirect(App::url('/work-orders?completed=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/work-orders?error=complete'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">工单已保存。</p>';
        }

        if (isset($_GET['issued'])) {
            return '<p class="success">领料出库已生成。</p>';
        }

        if (isset($_GET['completed'])) {
            return '<p class="success">完工入库已生成。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">工单处理失败，请检查工单编号、BOM、仓库、计划数量和日期。</p>';
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
     * @param array<int, array{id:int,order_no:string,parent_material_code:string,parent_material_name:string,planned_quantity:string,due_date:string,status:string,requirements:array<int, array{component_material_code:string,component_material_name:string,required_quantity:string}>}> $orders
     */
    private function orderRows(array $orders, string $csrfToken, string $warehouseOptions): string
    {
        if ($orders === []) {
            return '<tr><td colspan="7" class="empty">暂无工单，请先新增一条。</td></tr>';
        }

        return implode('', array_map(function (array $order) use ($csrfToken, $warehouseOptions): string {
            $requirements = $this->requirementText($order['requirements']);
            $actions = $this->actionForms($order, $csrfToken, $warehouseOptions);

            return sprintf(
                '<tr><td>%s</td><td>%s - %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($order['order_no'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['parent_material_code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['parent_material_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['planned_quantity'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($order['due_date'], ENT_QUOTES, 'UTF-8'),
                $requirements,
                $this->statusLabel($order['status']),
                $actions,
            );
        }, $orders));
    }

    /**
     * @param array{id:int,status:string} $order
     */
    private function actionForms(array $order, string $csrfToken, string $warehouseOptions): string
    {
        if ($order['status'] === 'completed') {
            return '<span class="muted">已完工</span>';
        }

        return $this->operationForm('/work-orders/issue', '领料出库', $order, $csrfToken, $warehouseOptions)
            . $this->operationForm('/work-orders/complete', '完工入库', $order, $csrfToken, $warehouseOptions);
    }

    /**
     * @param array{id:int} $order
     */
    private function operationForm(
        string $path,
        string $button,
        array $order,
        string $csrfToken,
        string $warehouseOptions,
    ): string {
        $action = htmlspecialchars(App::url($path), ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
        $id = (string) (int) $order['id'];
        $label = htmlspecialchars($button, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<form class="inline-form" method="post" action="{$action}">
  <input type="hidden" name="csrf_token" value="{$csrf}">
  <input type="hidden" name="id" value="{$id}">
  <select name="warehouse_id" required>{$warehouseOptions}</select>
  <button type="submit">{$label}</button>
</form>
HTML;
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

    private function statusLabel(string $status): string
    {
        return [
            'planned' => '已计划',
            'issued' => '已领料',
            'completed' => '已完工',
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
