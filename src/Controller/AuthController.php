<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\AuthService;
use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;

final class AuthController
{
    public function __construct(
        private readonly ?AuthService $auth = null,
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
    )
    {
    }

    public function login(): string
    {
        $message = isset($_GET['error'])
            ? '<p class="error">账号或密码不正确，或系统尚未完成数据库配置。</p>'
            : '';
        $session = $this->session();
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/login'), ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
<main class="auth-shell">
  <section class="auth-panel">
    <p class="eyebrow">开源工厂 ERP</p>
    <h1>中国中小制造企业生产物料管控平台</h1>
    <p class="muted">先打穿销售订单、BOM、齐套、采购建议、工单、领料和库存追溯闭环。</p>
    {$message}
    <form class="login-form" method="post" action="{$action}">
      <input type="hidden" name="csrf_token" value="{$csrf}">
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

    /**
     * @param null|array<string, string> $input
     */
    public function submit(?array $input = null): string
    {
        $input ??= $_POST;
        $email = (string) ($input['email'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $csrfToken = (string) ($input['csrf_token'] ?? '');
        $session = $this->session();

        if (!$session->verifyCsrf($csrfToken)) {
            $this->redirector()->redirect(App::url('/login?error=csrf'));
            return '';
        }

        $user = $this->auth?->attempt($email, $password);

        if ($user === null) {
            $this->redirector()->redirect(App::url('/login?error=1'));
            return '';
        }

        $session->regenerate();
        $session->setUser($user);

        $this->redirector()->redirect(App::url('/'));
        return '';
    }

    /**
     * @param null|array<string, string> $input
     */
    public function logout(?array $input = null): string
    {
        $input ??= $_POST;
        $session = $this->session();
        if (!$session->verifyCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->redirector()->redirect(App::url('/'));
            return '';
        }

        $session->clearUser();
        $this->redirector()->redirect(App::url('/login'));
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
