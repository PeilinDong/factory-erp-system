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
        $query = trim((string) ($_GET['q'] ?? ''));
        $rows = $this->warehouseRows($this->warehouses->search($query), $session->csrfToken());
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $searchAction = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $queryValue = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>仓库档案</h1>
    <p class="muted">维护采购、库存、生产领料共用的仓库主数据。早期版本先覆盖编码、名称和启停状态。</p>
    {$message}
    <section class="filter-panel">
      <form class="search-form" method="get" action="{$searchAction}">
        <label>搜索仓库 <input name="q" value="{$queryValue}" placeholder="编码、名称"></label>
        <button type="submit">搜索</button>
      </form>
    </section>
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
        <thead><tr><th>编码</th><th>名称</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('仓库档案', $body);
    }

    public function edit(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $id = (int) ($_GET['id'] ?? 0);
        $warehouse = $this->warehouses->find($id);
        if ($warehouse === null) {
            $this->redirector()->redirect(App::url('/warehouses?error=not_found'));
            return '';
        }

        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/warehouses/update'), ENT_QUOTES, 'UTF-8');
        $back = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $idValue = (string) (int) $warehouse['id'];
        $code = htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>编辑仓库</h1>
    <p class="muted">仓库编码会出现在库存流水和库存余额中，修改前请确认业务含义没有变化。</p>
    <section class="form-panel">
      <form class="master-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="id" value="{$idValue}">
        <label>仓库编码 <input name="code" required value="{$code}"></label>
        <label>仓库名称 <input name="name" required value="{$name}"></label>
        <button type="submit">保存修改</button>
      </form>
    </section>
    <p><a class="text-link" href="{$back}">返回仓库列表</a></p>
  </section>
</main>
HTML;

        return View::page('编辑仓库', $body);
    }

    /**
     * @param null|array<string, string> $input
     */
    public function store(?array $input = null): string
    {
        return $this->save($input, 'create');
    }

    /**
     * @param null|array<string, string> $input
     */
    public function update(?array $input = null): string
    {
        return $this->save($input, 'update');
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
            $this->redirector()->redirect(App::url('/warehouses?error=csrf'));
            return '';
        }

        try {
            $this->warehouses->setActive((int) ($input['id'] ?? 0), (string) ($input['is_active'] ?? '0') === '1');
            $this->redirector()->redirect(App::url('/warehouses?status=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/warehouses?error=validation'));
        }

        return '';
    }

    /**
     * @param null|array<string, string> $input
     */
    private function save(?array $input, string $mode): string
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
            if ($mode === 'update') {
                $this->warehouses->update((int) ($input['id'] ?? 0), $input);
                $this->redirector()->redirect(App::url('/warehouses?updated=1'));
            } else {
                $this->warehouses->create($input);
                $this->redirector()->redirect(App::url('/warehouses?created=1'));
            }
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
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

        if (isset($_GET['updated'])) {
            return '<p class="success">仓库已更新。</p>';
        }

        if (isset($_GET['status'])) {
            return '<p class="success">仓库状态已更新。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">仓库保存失败，请检查编码和名称。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,is_active:int}> $warehouses
     */
    private function warehouseRows(array $warehouses, string $csrfToken): string
    {
        if ($warehouses === []) {
            return '<tr><td colspan="4" class="empty">暂无仓库，请先新增一条。</td></tr>';
        }

        return implode('', array_map(function (array $warehouse) use ($csrfToken): string {
            $status = $warehouse['is_active'] === 1 ? '启用' : '停用';
            $nextStatus = $warehouse['is_active'] === 1 ? '0' : '1';
            $statusLabel = $warehouse['is_active'] === 1 ? '停用' : '启用';
            $id = (string) (int) $warehouse['id'];
            $editUrl = htmlspecialchars(App::url('/warehouses/edit?id=' . $id), ENT_QUOTES, 'UTF-8');
            $statusAction = htmlspecialchars(App::url('/warehouses/status'), ENT_QUOTES, 'UTF-8');
            $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><div class="row-actions"><a href="%s">编辑</a><form method="post" action="%s"><input type="hidden" name="csrf_token" value="%s"><input type="hidden" name="id" value="%s"><input type="hidden" name="is_active" value="%s"><button type="submit" class="link-button">%s</button></form></div></td></tr>',
                htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8'),
                $status,
                $editUrl,
                $statusAction,
                $csrf,
                $id,
                $nextStatus,
                $statusLabel,
            );
        }, $warehouses));
    }

    private function sidebar(): string
    {
        $home = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $materials = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehouses = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $inventory = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');
        $balances = htmlspecialchars(App::url('/inventory/balances'), ENT_QUOTES, 'UTF-8');
        $health = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<aside class="sidebar">
  <strong>Factory ERP</strong>
  <nav>
    <a href="{$home}">仪表盘</a>
    <a href="{$materials}">物料档案</a>
    <a href="{$warehouses}">仓库档案</a>
    <a href="{$inventory}">库存流水</a>
    <a href="{$balances}">库存余额</a>
    <a href="{$health}">健康检查</a>
  </nav>
</aside>
HTML;
    }
}
