<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\Sidebar;
use Erp\Core\View;
use Erp\Customer\CustomerService;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;

final class CustomerController
{
    public function __construct(
        private readonly CustomerService $customers,
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

        $message = $this->message();
        $query = trim((string) ($_GET['q'] ?? ''));
        $rows = $this->customerRows($this->customers->search($query), $session->csrfToken());
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/customers'), ENT_QUOTES, 'UTF-8');
        $searchAction = htmlspecialchars(App::url('/customers'), ENT_QUOTES, 'UTF-8');
        $queryValue = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
        $sidebar = Sidebar::render();

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>客户档案</h1>
    <p class="muted">维护销售订单使用的客户主数据。早期版本先覆盖编码、名称、联系人、电话和启停状态。</p>
    {$message}
    <section class="filter-panel">
      <form class="search-form" method="get" action="{$searchAction}">
        <label>搜索客户 <input name="q" value="{$queryValue}" placeholder="编码、名称、联系人、电话"></label>
        <button type="submit">搜索</button>
      </form>
    </section>
    <section class="form-panel">
      <h2>新增客户</h2>
      <form class="master-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>客户编码 <input name="code" required placeholder="CUS-001"></label>
        <label>客户名称 <input name="name" required placeholder="上海客户"></label>
        <label>联系人 <input name="contact_name" placeholder="王五"></label>
        <label>电话 <input name="phone" placeholder="13700000000"></label>
        <button type="submit">保存客户</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>客户列表</h2>
      <table>
        <thead><tr><th>编码</th><th>名称</th><th>联系人</th><th>电话</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('客户档案', $body);
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
            $this->redirector()->redirect(App::url('/customers?error=csrf'));
            return '';
        }

        try {
            $this->customers->create($input);
            $this->redirector()->redirect(App::url('/customers?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/customers?error=validation'));
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
            $this->redirector()->redirect(App::url('/customers?error=csrf'));
            return '';
        }

        try {
            $this->customers->setActive((int) ($input['id'] ?? 0), (string) ($input['is_active'] ?? '0') === '1');
            $this->redirector()->redirect(App::url('/customers?status=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/customers?error=validation'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">客户已保存。</p>';
        }

        if (isset($_GET['status'])) {
            return '<p class="success">客户状态已更新。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">客户保存失败，请检查编码和名称。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}> $customers
     */
    private function customerRows(array $customers, string $csrfToken): string
    {
        if ($customers === []) {
            return '<tr><td colspan="6" class="empty">暂无客户，请先新增一条。</td></tr>';
        }

        $statusAction = htmlspecialchars(App::url('/customers/status'), ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

        return implode('', array_map(static function (array $customer) use ($statusAction, $csrf): string {
            $isActive = $customer['is_active'] === 1;
            $nextStatus = $isActive ? '0' : '1';
            $statusLabel = $isActive ? '停用' : '启用';
            $customerId = (string) (int) $customer['id'];
            $actions = <<<HTML
<form method="post" action="{$statusAction}" class="inline-form">
  <input type="hidden" name="csrf_token" value="{$csrf}">
  <input type="hidden" name="id" value="{$customerId}">
  <input type="hidden" name="is_active" value="{$nextStatus}">
  <button type="submit">{$statusLabel}</button>
</form>
HTML;

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($customer['code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($customer['contact_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($customer['phone'], ENT_QUOTES, 'UTF-8'),
                $isActive ? '启用' : '停用',
                $actions,
            );
        }, $customers));
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
