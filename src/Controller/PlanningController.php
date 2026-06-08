<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\Sidebar;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Planning\MaterialShortageService;

final class PlanningController
{
    public function __construct(
        private readonly MaterialShortageService $shortages,
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

    private function session(): SessionStore
    {
        return $this->session ?? new NativeSessionStore();
    }

    private function redirector(): Redirector
    {
        return $this->redirector ?? new NativeRedirector();
    }
}
