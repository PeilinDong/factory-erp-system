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

final class DashboardController
{
    public function __construct(
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
        private readonly ?InventoryService $inventory = null,
    ) {
    }

    public function index(): string
    {
        $session = $this->session();
        $user = $session->user();
        if ($user === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $metrics = $this->metrics();
        $name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $logoutAction = htmlspecialchars(App::url('/logout'), ENT_QUOTES, 'UTF-8');
        $homeUrl = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $healthUrl = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');
        $materialsUrl = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehousesUrl = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $bomsUrl = htmlspecialchars(App::url('/boms'), ENT_QUOTES, 'UTF-8');
        $inventoryUrl = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');
        $balancesUrl = htmlspecialchars(App::url('/inventory/balances'), ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  <aside class="sidebar">
    <strong>Factory ERP</strong>
    <nav>
      <a href="{$homeUrl}">仪表盘</a>
      <a href="{$materialsUrl}">物料档案</a>
      <a href="{$warehousesUrl}">仓库档案</a>
      <a href="{$bomsUrl}">BOM 管理</a>
      <a href="{$inventoryUrl}">库存流水</a>
      <a href="{$balancesUrl}">库存余额</a>
      <a href="{$healthUrl}">健康检查</a>
    </nav>
    <form class="logout-form" method="post" action="{$logoutAction}">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <button type="submit">退出登录</button>
    </form>
  </aside>
  <section class="content">
    <p class="eyebrow">生产工作台</p>
    <h1>今天需要关注的运营状态</h1>
    <p class="muted">当前用户：{$name}</p>
    <div class="metric-grid">
      <article><span>库存余额</span><strong>{$metrics['balance_count']} 项</strong></article>
      <article><span>负库存预警</span><strong>{$metrics['negative_count']} 项</strong></article>
      <article><span>库存流水</span><strong>{$metrics['transaction_count']} 条</strong></article>
      <article><span>待完工入库</span><strong>0 张</strong></article>
    </div>
    <p class="muted empty-note">库存指标已根据库存流水实时汇总，BOM 管理已接入，工单和采购建议将在后续模块继续补齐。</p>
    <section class="quick-panel">
      <h2>快捷入口</h2>
      <div class="quick-grid">
        <a href="{$materialsUrl}">物料档案</a>
        <a href="{$warehousesUrl}">仓库档案</a>
        <a href="{$bomsUrl}">BOM 管理</a>
        <a href="{$inventoryUrl}">库存流水</a>
        <a href="{$balancesUrl}">库存余额</a>
        <span class="disabled-link">生产工单 <small>开发中</small></span>
      </div>
    </section>
  </section>
</main>
HTML;

        return View::page('仪表盘', $body);
    }

    public function health(): string
    {
        return 'Factory ERP 正常';
    }

    /**
     * @return array{balance_count:int,negative_count:int,transaction_count:int}
     */
    private function metrics(): array
    {
        if ($this->inventory === null) {
            return [
                'balance_count' => 0,
                'negative_count' => 0,
                'transaction_count' => 0,
            ];
        }

        $balances = $this->inventory->stockBalances();
        $negative = array_filter($balances, static fn (array $balance): bool => (float) $balance['quantity'] < 0);

        return [
            'balance_count' => count($balances),
            'negative_count' => count($negative),
            'transaction_count' => count($this->inventory->list()),
        ];
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
