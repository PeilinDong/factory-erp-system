<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Core\View;

final class AuthController
{
    public function login(): string
    {
        $body = <<<HTML
<main class="auth-shell">
  <section class="auth-panel">
    <p class="eyebrow">开源工厂 ERP</p>
    <h1>中国中小制造企业生产物料管控平台</h1>
    <p class="muted">先打穿销售订单、BOM、齐套、采购建议、工单、领料和库存追溯闭环。</p>
    <form class="login-form" method="post" action="/erp/login">
      <label>
        邮箱
        <input type="email" name="email" autocomplete="username" placeholder="admin@example.com">
      </label>
      <label>
        密码
        <input type="password" name="password" autocomplete="current-password" placeholder="请输入密码">
      </label>
      <button type="submit">登录</button>
    </form>
  </section>
</main>
HTML;

        return View::page('登录', $body);
    }
}

