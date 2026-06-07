<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Warehouse\WarehouseService;

final class WarehouseController
{
    public function __construct(
        private readonly WarehouseService $warehouses,
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
        $rows = $this->warehouseRows($this->warehouses->list());
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>仓库档案</h1>
    <p class="muted">维护采购、库存、生产领料共用的仓库主数据。</p>
    {$message}
    <section class="form-panel">
      <h2>新增仓库</h2>
      <form class="master-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>仓库编码 <input name="code" required placeholder="WH-001"></label>
        <label>仓库名称 <input name="name" required placeholder="原料仓"></label>
        <button type="submit">保存仓库</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>仓库列表</h2>
      <table>
        <thead><tr><th>编码</th><th>名称</th><th>状态</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('仓库档案', $body);
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
            $this->redirector()->redirect(App::url('/warehouses?error=csrf'));
            return '';
        }

        try {
            $this->warehouses->create($input);
            $this->redirector()->redirect(App::url('/warehouses?created=1'));
        } catch (\InvalidArgumentException|\PDOException $exception) {
            $this->redirector()->redirect(App::url('/warehouses?error=validation'));
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

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">仓库已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">仓库保存失败，请检查编码和名称。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,is_active:int}> $warehouses
     */
    private function warehouseRows(array $warehouses): string
    {
        if ($warehouses === []) {
            return '<tr><td colspan="3" class="empty">暂无仓库，请先新增一条。</td></tr>';
        }

        return implode('', array_map(static fn (array $warehouse): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8'),
            $warehouse['is_active'] === 1 ? '启用' : '停用',
        ), $warehouses));
    }

    private function sidebar(): string
    {
        $home = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $materials = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehouses = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $health = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<aside class="sidebar">
  <strong>Factory ERP</strong>
  <nav>
    <a href="{$home}">仪表盘</a>
    <a href="{$materials}">物料档案</a>
    <a href="{$warehouses}">仓库档案</a>
    <a href="{$health}">健康检查</a>
  </nav>
</aside>
HTML;
    }
}
