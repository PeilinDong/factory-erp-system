<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Material\MaterialService;

final class MaterialController
{
    public function __construct(
        private readonly MaterialService $materials,
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
        $rows = $this->materialRows($this->materials->search($query));
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $searchAction = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $queryValue = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>物料档案</h1>
    <p class="muted">维护生产、采购、库存共用的物料主数据。</p>
    {$message}
    <section class="filter-panel">
      <form class="search-form" method="get" action="{$searchAction}">
        <label>搜索物料<input name="q" value="{$queryValue}" placeholder="编码、名称、规格"></label>
        <button type="submit">搜索</button>
      </form>
    </section>
    <section class="form-panel">
      <h2>新增物料</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>物料编码<input name="code" placeholder="MAT-001"></label>
        <label>物料名称<input name="name" placeholder="不锈钢螺丝"></label>
        <label>规格型号<input name="specification" placeholder="M6x20"></label>
        <label>基本单位<input name="base_unit" placeholder="pcs"></label>
        <label>物料属性
          <select name="material_type">
            <option value="purchased">采购件</option>
            <option value="manufactured">自制件</option>
            <option value="outsourced">委外件</option>
          </select>
        </label>
        <button type="submit">保存物料</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>物料列表</h2>
      <table>
        <thead><tr><th>编码</th><th>名称</th><th>规格</th><th>单位</th><th>属性</th><th>状态</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('物料档案', $body);
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
            $this->redirector()->redirect(App::url('/materials?error=csrf'));
            return '';
        }

        try {
            $this->materials->create($input);
            $this->redirector()->redirect(App::url('/materials?created=1'));
        } catch (\InvalidArgumentException|\PDOException $exception) {
            $this->redirector()->redirect(App::url('/materials?error=validation'));
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
            return '<p class="success">物料已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">物料保存失败，请检查编码、名称、单位和属性。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}> $materials
     */
    private function materialRows(array $materials): string
    {
        if ($materials === []) {
            return '<tr><td colspan="6" class="empty">暂无物料，请先新增一条。</td></tr>';
        }

        return implode('', array_map(static function (array $material): string {
            $type = [
                'purchased' => '采购件',
                'manufactured' => '自制件',
                'outsourced' => '委外件',
            ][$material['material_type']] ?? $material['material_type'];

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($material['specification'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($material['base_unit'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
                $material['is_active'] === 1 ? '启用' : '停用',
            );
        }, $materials));
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
