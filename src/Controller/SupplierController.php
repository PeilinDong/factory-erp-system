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
use Erp\Supplier\SupplierService;

final class SupplierController
{
    public function __construct(
        private readonly SupplierService $suppliers,
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
        $rows = $this->supplierRows($this->suppliers->search($query), $session->csrfToken());
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/suppliers'), ENT_QUOTES, 'UTF-8');
        $searchAction = htmlspecialchars(App::url('/suppliers'), ENT_QUOTES, 'UTF-8');
        $queryValue = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
        $sidebar = Sidebar::render();

        $body = <<<HTML
<main class="app-shell">
  {$sidebar}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>供应商档案</h1>
    <p class="muted">维护采购订单和采购建议使用的供应商主数据。早期版本先覆盖编码、名称、联系人、电话和启停状态。</p>
    {$message}
    <section class="filter-panel">
      <form class="search-form" method="get" action="{$searchAction}">
        <label>搜索供应商 <input name="q" value="{$queryValue}" placeholder="编码、名称、联系人、电话"></label>
        <button type="submit">搜索</button>
      </form>
    </section>
    <section class="form-panel">
      <h2>新增供应商</h2>
      <form class="master-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>供应商编码 <input name="code" required placeholder="SUP-001"></label>
        <label>供应商名称 <input name="name" required placeholder="上海供应商"></label>
        <label>联系人 <input name="contact_name" placeholder="张三"></label>
        <label>电话 <input name="phone" placeholder="13800000000"></label>
        <button type="submit">保存供应商</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>供应商列表</h2>
      <table>
        <thead><tr><th>编码</th><th>名称</th><th>联系人</th><th>电话</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('供应商档案', $body);
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
            $this->redirector()->redirect(App::url('/suppliers?error=csrf'));
            return '';
        }

        try {
            $this->suppliers->create($input);
            $this->redirector()->redirect(App::url('/suppliers?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/suppliers?error=validation'));
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
            $this->redirector()->redirect(App::url('/suppliers?error=csrf'));
            return '';
        }

        try {
            $this->suppliers->setActive((int) ($input['id'] ?? 0), (string) ($input['is_active'] ?? '0') === '1');
            $this->redirector()->redirect(App::url('/suppliers?status=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/suppliers?error=validation'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">供应商已保存。</p>';
        }

        if (isset($_GET['status'])) {
            return '<p class="success">供应商状态已更新。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">供应商保存失败，请检查编码和名称。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,contact_name:string,phone:string,is_active:int}> $suppliers
     */
    private function supplierRows(array $suppliers, string $csrfToken): string
    {
        if ($suppliers === []) {
            return '<tr><td colspan="6" class="empty">暂无供应商，请先新增一条。</td></tr>';
        }

        $statusAction = htmlspecialchars(App::url('/suppliers/status'), ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

        return implode('', array_map(static function (array $supplier) use ($statusAction, $csrf): string {
            $isActive = $supplier['is_active'] === 1;
            $nextStatus = $isActive ? '0' : '1';
            $statusLabel = $isActive ? '停用' : '启用';
            $supplierId = (string) (int) $supplier['id'];
            $actions = <<<HTML
<form method="post" action="{$statusAction}" class="inline-form">
  <input type="hidden" name="csrf_token" value="{$csrf}">
  <input type="hidden" name="id" value="{$supplierId}">
  <input type="hidden" name="is_active" value="{$nextStatus}">
  <button type="submit">{$statusLabel}</button>
</form>
HTML;

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($supplier['code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($supplier['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($supplier['contact_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($supplier['phone'], ENT_QUOTES, 'UTF-8'),
                $isActive ? '启用' : '停用',
                $actions,
            );
        }, $suppliers));
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
