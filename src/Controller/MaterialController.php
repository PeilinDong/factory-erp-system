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
        $rows = $this->materialRows($this->materials->search($query), $session->csrfToken());
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
    <p class="muted">维护生产、采购、库存共用的物料主数据。早期版本先覆盖编码、规格、单位、属性和启停状态。</p>
    {$message}
    <section class="filter-panel">
      <form class="search-form" method="get" action="{$searchAction}">
        <label>搜索物料 <input name="q" value="{$queryValue}" placeholder="编码、名称、规格"></label>
        <button type="submit">搜索</button>
      </form>
    </section>
    <section class="form-panel">
      <h2>新增物料</h2>
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>物料编码 <input name="code" required placeholder="MAT-001"></label>
        <label>物料名称 <input name="name" required placeholder="不锈钢螺丝"></label>
        <label>规格型号 <input name="specification" placeholder="M6x20"></label>
        <label>基本单位 <input name="base_unit" required placeholder="pcs"></label>
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
        <thead><tr><th>编码</th><th>名称</th><th>规格</th><th>单位</th><th>属性</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('物料档案', $body);
    }

    public function edit(): string
    {
        $session = $this->session();
        if ($session->user() === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $id = (int) ($_GET['id'] ?? 0);
        $material = $this->materials->find($id);
        if ($material === null) {
            $this->redirector()->redirect(App::url('/materials?error=not_found'));
            return '';
        }

        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars(App::url('/materials/update'), ENT_QUOTES, 'UTF-8');
        $back = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $idValue = (string) (int) $material['id'];
        $code = htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8');
        $specification = htmlspecialchars($material['specification'], ENT_QUOTES, 'UTF-8');
        $baseUnit = htmlspecialchars($material['base_unit'], ENT_QUOTES, 'UTF-8');
        $typeOptions = $this->materialTypeOptions($material['material_type']);

        $body = <<<HTML
<main class="app-shell">
  {$this->sidebar()}
  <section class="content">
    <p class="eyebrow">基础资料</p>
    <h1>编辑物料</h1>
    <p class="muted">修改物料主数据会影响后续采购、库存和工单选项，请确认编码与单位保持一致。</p>
    <section class="form-panel">
      <form class="material-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="id" value="{$idValue}">
        <label>物料编码 <input name="code" required value="{$code}"></label>
        <label>物料名称 <input name="name" required value="{$name}"></label>
        <label>规格型号 <input name="specification" value="{$specification}"></label>
        <label>基本单位 <input name="base_unit" required value="{$baseUnit}"></label>
        <label>物料属性
          <select name="material_type">{$typeOptions}</select>
        </label>
        <button type="submit">保存修改</button>
      </form>
    </section>
    <p><a class="text-link" href="{$back}">返回物料列表</a></p>
  </section>
</main>
HTML;

        return View::page('编辑物料', $body);
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
            $this->redirector()->redirect(App::url('/materials?error=csrf'));
            return '';
        }

        try {
            $this->materials->setActive((int) ($input['id'] ?? 0), (string) ($input['is_active'] ?? '0') === '1');
            $this->redirector()->redirect(App::url('/materials?status=1'));
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
            $this->redirector()->redirect(App::url('/materials?error=validation'));
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
            $this->redirector()->redirect(App::url('/materials?error=csrf'));
            return '';
        }

        try {
            if ($mode === 'update') {
                $this->materials->update((int) ($input['id'] ?? 0), $input);
                $this->redirector()->redirect(App::url('/materials?updated=1'));
            } else {
                $this->materials->create($input);
                $this->redirector()->redirect(App::url('/materials?created=1'));
            }
        } catch (\InvalidArgumentException|\RuntimeException|\PDOException) {
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

        if (isset($_GET['updated'])) {
            return '<p class="success">物料已更新。</p>';
        }

        if (isset($_GET['status'])) {
            return '<p class="success">物料状态已更新。</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">物料保存失败，请检查编码、名称、单位和属性。</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,specification:string,base_unit:string,material_type:string,is_active:int}> $materials
     */
    private function materialRows(array $materials, string $csrfToken): string
    {
        if ($materials === []) {
            return '<tr><td colspan="7" class="empty">暂无物料，请先新增一条。</td></tr>';
        }

        return implode('', array_map(function (array $material) use ($csrfToken): string {
            $type = [
                'purchased' => '采购件',
                'manufactured' => '自制件',
                'outsourced' => '委外件',
            ][$material['material_type']] ?? $material['material_type'];
            $status = $material['is_active'] === 1 ? '启用' : '停用';
            $nextStatus = $material['is_active'] === 1 ? '0' : '1';
            $statusLabel = $material['is_active'] === 1 ? '停用' : '启用';
            $id = (string) (int) $material['id'];
            $editUrl = htmlspecialchars(App::url('/materials/edit?id=' . $id), ENT_QUOTES, 'UTF-8');
            $statusAction = htmlspecialchars(App::url('/materials/status'), ENT_QUOTES, 'UTF-8');
            $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><div class="row-actions"><a href="%s">编辑</a><form method="post" action="%s"><input type="hidden" name="csrf_token" value="%s"><input type="hidden" name="id" value="%s"><input type="hidden" name="is_active" value="%s"><button type="submit" class="link-button">%s</button></form></div></td></tr>',
                htmlspecialchars($material['code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($material['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($material['specification'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($material['base_unit'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
                $status,
                $editUrl,
                $statusAction,
                $csrf,
                $id,
                $nextStatus,
                $statusLabel,
            );
        }, $materials));
    }

    private function materialTypeOptions(string $selected): string
    {
        $types = [
            'purchased' => '采购件',
            'manufactured' => '自制件',
            'outsourced' => '委外件',
        ];

        return implode('', array_map(static function (string $value, string $label) use ($selected): string {
            $selectedAttribute = $value === $selected ? ' selected' : '';
            return sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
                $selectedAttribute,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            );
        }, array_keys($types), $types));
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
