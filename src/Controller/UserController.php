<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Auth\UserManagementService;
use Erp\Core\App;
use Erp\Core\Sidebar;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;

final class UserController
{
    public function __construct(
        private readonly UserManagementService $users,
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
        $sidebar = Sidebar::render();
        $action = htmlspecialchars(App::url('/users'), ENT_QUOTES, 'UTF-8');
        $message = $this->message();
        $roleOptions = $this->roleOptions($this->users->roles());
        $rows = $this->userRows($this->users->list(), $session->csrfToken());

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">系统管理</p>
    <h1>用户管理</h1>
    <p class="muted">维护系统登录用户、基础角色和启用状态。当前版本先提供用户与角色基础，细粒度权限会在后续版本继续补强。</p>
    {$message}
    <section class="form-panel">
      <h2>新增用户</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>邮箱 <input type="email" name="email" required placeholder="user@example.com"></label>
        <label>姓名 <input name="name" required placeholder="张三"></label>
        <label>初始密码 <input type="password" name="password" required placeholder="至少 8 位"></label>
        <label>角色
          <select name="role_code" required>{$roleOptions}</select>
        </label>
        <button type="submit">保存用户</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>用户列表</h2>
      <table>
        <thead><tr><th>邮箱</th><th>姓名</th><th>角色</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('用户管理', $body);
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
            $this->redirector()->redirect(App::url('/users?error=csrf'));
            return '';
        }

        try {
            $this->users->create($input);
            $this->redirector()->redirect(App::url('/users?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/users?error=validation'));
        }

        return '';
    }

    /**
     * @param null|array<string, string> $input
     */
    public function status(?array $input = null): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $input ??= $_POST;
        if (!$session->verifyCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->redirector()->redirect(App::url('/users?error=csrf'));
            return '';
        }

        try {
            $this->users->setActive((int) ($input['id'] ?? 0), (string) ($input['is_active'] ?? '0') === '1');
            $this->redirector()->redirect(App::url('/users?status=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/users?error=validation'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">用户已保存。</p>';
        }

        if (isset($_GET['status'])) {
            return '<p class="success">用户状态已更新。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">用户保存失败，请检查邮箱、姓名、密码和角色。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{code:string,name:string}> $roles
     */
    private function roleOptions(array $roles): string
    {
        return implode('', array_map(static fn (array $role): string => sprintf(
            '<option value="%s">%s</option>',
            htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'),
        ), $roles));
    }

    /**
     * @param array<int, array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}> $users
     */
    private function userRows(array $users, string $csrfToken): string
    {
        if ($users === []) {
            return '<tr><td colspan="5" class="empty">暂无用户，请先新增一个系统用户。</td></tr>';
        }

        return implode('', array_map(function (array $user) use ($csrfToken): string {
            $status = $user['is_active'] === 1 ? '启用' : '停用';
            $nextStatus = $user['is_active'] === 1 ? '0' : '1';
            $statusLabel = $user['is_active'] === 1 ? '停用' : '启用';
            $action = htmlspecialchars(App::url('/users/status'), ENT_QUOTES, 'UTF-8');
            $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
            $id = (string) (int) $user['id'];

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><form method="post" action="%s"><input type="hidden" name="csrf_token" value="%s"><input type="hidden" name="id" value="%s"><input type="hidden" name="is_active" value="%s"><button type="submit" class="link-button">%s</button></form></td></tr>',
                htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8'),
                $status,
                $action,
                $csrf,
                $id,
                $nextStatus,
                $statusLabel,
            );
        }, $users));
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
