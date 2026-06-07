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
    <p class="eyebrow">Master Data</p>
    <h1>Warehouse Master</h1>
    <p class="muted">Maintain warehouses used by purchasing, inventory, and production issue workflows.</p>
    {$message}
    <section class="form-panel">
      <h2>Add Warehouse</h2>
      <form class="master-form" method="post" action="{$action}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>Warehouse Code <input name="code" required placeholder="WH-001"></label>
        <label>Warehouse Name <input name="name" required placeholder="Main Warehouse"></label>
        <button type="submit">Save Warehouse</button>
      </form>
    </section>
    <section class="table-panel">
      <h2>Warehouse List</h2>
      <table>
        <thead><tr><th>Code</th><th>Name</th><th>Status</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </section>
  </section>
</main>
HTML;

        return View::page('Warehouse Master', $body);
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
            return '<p class="success">Warehouse saved.</p>';
        }

        if (isset($_GET['error'])) {
            return '<p class="error">Warehouse save failed. Check the code and name.</p>';
        }

        return '';
    }

    /**
     * @param array<int, array{id:int,code:string,name:string,is_active:int}> $warehouses
     */
    private function warehouseRows(array $warehouses): string
    {
        if ($warehouses === []) {
            return '<tr><td colspan="3" class="empty">No warehouses yet.</td></tr>';
        }

        return implode('', array_map(static fn (array $warehouse): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8'),
            $warehouse['is_active'] === 1 ? 'Active' : 'Inactive',
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
    <a href="{$home}">Dashboard</a>
    <a href="{$materials}">Material Master</a>
    <a href="{$warehouses}">Warehouse Master</a>
    <a href="{$health}">Health Check</a>
  </nav>
</aside>
HTML;
    }
}
