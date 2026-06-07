<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;

final class DashboardController
{
    public function __construct(
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
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

        $name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $logoutAction = htmlspecialchars(App::url('/logout'), ENT_QUOTES, 'UTF-8');
        $homeUrl = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $healthUrl = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');
        $materialsUrl = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehousesUrl = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $inventoryUrl = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  <aside class="sidebar">
    <strong>Factory ERP</strong>
    <nav>
      <a href="{$homeUrl}">仪表盘</a>
      <a href="{$materialsUrl}">物料档案</a>
      <a href="{$warehousesUrl}">仓库档案</a>
      <a href="{$inventoryUrl}">库存流水</a>
      <a href="{$healthUrl}">健康检查</a>
    </nav>
    <form class="logout-form" method="post" action="{$logoutAction}">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <button type="submit">退出登录</button>
    </form>
  </aside>
  <section class="content">
    <p class="eyebrow">生产工作台</p>
    <h1>今天需要关注的业务</h1>
    <p class="muted">当前用户：{$name}</p>
    <div class="metric-grid">
      <article><span>缺料预警</span><strong>0 项</strong></article>
      <article><span>待采购建议</span><strong>0 条</strong></article>
      <article><span>待领料工单</span><strong>0 张</strong></article>
      <article><span>待完工入库</span><strong>0 张</strong></article>
    </div>
    <p class="muted empty-note">先维护物料和仓库，库存流水上线后将自动计算这些指标。</p>
    <section class="quick-panel">
      <h2>快捷入口</h2>
      <div class="quick-grid">
        <a href="{$materialsUrl}">物料档案</a>
        <a href="{$warehousesUrl}">仓库档案</a>
        <a href="{$inventoryUrl}">库存流水</a>
        <span class="disabled-link">BOM 管理 <small>开发中</small></span>
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

    private function session(): SessionStore
    {
        return $this->session ?? new NativeSessionStore();
    }

    private function redirector(): Redirector
    {
        return $this->redirector ?? new NativeRedirector();
    }
}
