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
use Erp\Planning\MaterialShortageService;
use Erp\Planning\PurchaseSuggestionService;
use Erp\Purchase\PurchaseOrderService;

final class PlanningController
{
    public function __construct(
        private readonly MaterialShortageService $shortages,
        private readonly PurchaseSuggestionService $suggestions,
        private readonly PurchaseOrderService $purchases,
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
    ) {
    }

    public function shortages(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $sidebar = Sidebar::render();
        $rows = $this->shortageRows($this->shortages->analyze());

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">计划与齐套</p>
    <h1>缺料分析</h1>
    <p class="muted">根据待领料生产工单的 BOM 需求，扣减当前库存余额，汇总需要采购或补料的物料。已领料和已完工工单不再重复计入待领料需求。</p>
    <section class="table-panel">
      <h2>缺料清单</h2>
      <table>
        <thead><tr><th>物料编码</th><th>物料名称</th><th>需求数量</th><th>当前库存</th><th>缺料数量</th><th>来源工单</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('缺料分析', $body);
    }

    public function purchaseSuggestions(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $sidebar = Sidebar::render();
        $message = $this->message();
        $rows = $this->suggestionRows($this->suggestions->list(), $session->csrfToken());

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">计划与采购</p>
    <h1>采购建议</h1>
    <p class="muted">根据当前缺料分析生成采购建议。采购员确认供应商、单号和单价后，可转为采购订单草稿。</p>
    {$message}
    <section class="table-panel">
      <h2>建议清单</h2>
      <table>
        <thead><tr><th>物料编码</th><th>物料名称</th><th>缺料数量</th><th>建议采购</th><th>来源工单</th><th>转采购单</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('采购建议', $body);
    }

    /**
     * @param null|array<string, string> $input
     */
    public function convertPurchaseSuggestion(?array $input = null): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        if (!PermissionService::can($session->user(), 'purchase.manage')) {
            $this->redirector()->redirect(App::url('/planning/purchase-suggestions?error=forbidden'));
            return '';
        }

        $input ??= $_POST;
        if (!$session->verifyCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->redirector()->redirect(App::url('/planning/purchase-suggestions?error=csrf'));
            return '';
        }

        try {
            $this->suggestions->convertToPurchaseOrder($input, $this->purchases);
            $this->redirector()->redirect(App::url('/planning/purchase-suggestions?converted=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/planning/purchase-suggestions?error=validation'));
        }

        return '';
    }

    /**
     * @param array<int, array{material_code:string,material_name:string,required_quantity:string,stock_quantity:string,shortage_quantity:string,source_orders:string}> $rows
     */
    private function shortageRows(array $rows): string
    {
        if ($rows === []) {
            return '<tr><td colspan="6" class="empty">暂无缺料。请先维护 BOM、库存流水和待领料生产工单。</td></tr>';
        }

        return implode('', array_map(static fn (array $row): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><strong>%s</strong></td><td>%s</td></tr>',
            htmlspecialchars($row['material_code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($row['material_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($row['required_quantity'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($row['stock_quantity'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($row['shortage_quantity'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($row['source_orders'], ENT_QUOTES, 'UTF-8'),
        ), $rows));
    }

    /**
     * @param array<int, array{material_id:int,material_code:string,material_name:string,shortage_quantity:string,suggested_quantity:string,source_orders:string}> $rows
     */
    private function suggestionRows(array $rows, string $csrfToken): string
    {
        if ($rows === []) {
            return '<tr><td colspan="6" class="empty">暂无采购建议。请先维护 BOM、库存流水和待领料生产工单。</td></tr>';
        }

        $action = htmlspecialchars(App::url('/planning/purchase-suggestions/convert'), ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

        return implode('', array_map(static function (array $row) use ($action, $csrf): string {
            $materialId = (string) (int) $row['material_id'];
            $form = <<<HTML
<form method="post" action="{$action}" class="inline-form">
  <input type="hidden" name="csrf_token" value="{$csrf}">
  <input type="hidden" name="material_id" value="{$materialId}">
  <input name="order_no" required placeholder="PO-建议">
  <input name="supplier_name" required placeholder="供应商">
  <input name="unit_price" required placeholder="0">
  <input name="expected_date" placeholder="2026-07-31">
  <button type="submit">转采购单</button>
</form>
HTML;

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td><strong>%s</strong></td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($row['material_code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['material_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['shortage_quantity'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['suggested_quantity'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($row['source_orders'], ENT_QUOTES, 'UTF-8'),
                $form,
            );
        }, $rows));
    }

    private function message(): string
    {
        if (isset($_GET['converted'])) {
            return '<p class="success">采购建议已转为采购订单草稿。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">采购建议处理失败，请检查权限、供应商、单号、单价和建议物料。</p>';
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
}
