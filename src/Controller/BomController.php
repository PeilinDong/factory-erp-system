<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Bom\BomService;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;
use Erp\Material\MaterialService;

final class BomController
{
    public function __construct(
        private readonly BomService $boms,
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

        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/boms'), ENT_QUOTES, 'UTF-8');
        $message = $this->message();
        $materialOptions = $this->materialOptions($this->materials->list());
        $rows = $this->bomRows($this->boms->list());

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">生产资料</p>
    <h1>BOM 管理</h1>
    <p class="muted">维护成品与组件用量关系。当前早期版本先支持一条组件明细，后续会扩展多行组件、版本生效和工单齐套分析。</p>
    {$message}
    <section class="form-panel">
      <h2>新增 BOM</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>成品物料
          <select name="parent_material_id" required>{$materialOptions}</select>
        </label>
        <label>版本 <input name="version" required placeholder="v1"></label>
        <label>组件物料
          <select name="component_material_id" required>{$materialOptions}</select>
        </label>
        <label>单位用量 <input name="quantity" required placeholder="1"></label>
        <label>损耗率 % <input name="scrap_rate" placeholder="0"></label>
        <button type="submit">保存 BOM</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>BOM 列表</h2>
      <table>
        <thead><tr><th>成品</th><th>版本</th><th>组件</th><th>单位用量</th><th>损耗率</th><th>状态</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('BOM 管理', $body);
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
            $this->redirector()->redirect(App::url('/boms?error=csrf'));
            return '';
        }

        try {
            $this->boms->create($input);
            $this->redirector()->redirect(App::url('/boms?created=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/boms?error=validation'));
        }

        return '';
    }

    private function message(): string
    {
        if (isset($_GET['created'])) {
            return '<p class="success">BOM 已保存。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">BOM 保存失败，请检查成品、组件、用量和损耗率。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,is_active:int}> $materials
     */
    private function materialOptions(array $materials): string
    {
        if ($materials === []) {
            return '<option value="">请先维护物料</option>';
        }

        return implode('', array_map(static fn (array $material): string => sprintf(
            '<option value="%s">%s - %s</option>',
            (string) (int) $material['id'],
            htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8'),
        ), $materials));
    }

    /**
     * @param array<int, array{id:int,parent_material_code:string,parent_material_name:string,version:string,is_active:int,items:array<int, array{component_material_code:string,component_material_name:string,quantity:string,scrap_rate:string}>}> $boms
     */
    private function bomRows(array $boms): string
    {
        if ($boms === []) {
            return '<tr><td colspan="6" class="empty">暂无 BOM，请先新增一条。</td></tr>';
        }

        $rows = [];
        foreach ($boms as $bom) {
            foreach ($bom['items'] as $item) {
                $rows[] = sprintf(
                    '<tr><td>%s - %s</td><td>%s</td><td>%s - %s</td><td>%s</td><td>%s%%</td><td>%s</td></tr>',
                    htmlspecialchars($bom['parent_material_code'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($bom['parent_material_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($bom['version'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['component_material_code'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['component_material_name'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['scrap_rate'], ENT_QUOTES, 'UTF-8'),
                    $bom['is_active'] === 1 ? '启用' : '停用',
                );
            }
        }

        return implode('', $rows);
    }

    private function sidebar(): string
    {
        $home = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $materials = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehouses = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $boms = htmlspecialchars(App::url('/boms'), ENT_QUOTES, 'UTF-8');
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
    <a href="{$boms}">BOM 管理</a>
    <a href="{$inventory}">库存流水</a>
    <a href="{$balances}">库存余额</a>
    <a href="{$health}">健康检查</a>
  </nav>
</aside>
HTML;
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
