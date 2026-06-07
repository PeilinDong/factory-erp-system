<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Core\View;

final class DashboardController
{
    public function index(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $name = htmlspecialchars($_SESSION['user']['name'] ?? '未登录用户', ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
<main class="app-shell">
  <aside class="sidebar">
    <strong>Factory ERP</strong>
    <nav>
      <a href="/erp/">仪表盘</a>
      <a href="/erp/login">登录</a>
      <a href="/erp/health">健康检查</a>
    </nav>
  </aside>
  <section class="content">
    <p class="eyebrow">MVP Foundation</p>
    <h1>生产物料管控闭环</h1>
    <p class="muted">当前用户：{$name}</p>
    <div class="metric-grid">
      <article><span>物料</span><strong>准备中</strong></article>
      <article><span>BOM</span><strong>准备中</strong></article>
      <article><span>库存</span><strong>准备中</strong></article>
      <article><span>工单</span><strong>准备中</strong></article>
    </div>
  </section>
</main>
HTML;

        return View::page('仪表盘', $body);
    }

    public function health(): string
    {
        return 'Factory ERP OK';
    }
}
