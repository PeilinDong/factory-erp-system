<?php

declare(strict_types=1);

namespace Erp\Controller;

use Erp\Auth\NativeSessionStore;
use Erp\Auth\SessionStore;
use Erp\Core\App;
use Erp\Core\View;
use Erp\Http\NativeRedirector;
use Erp\Http\Redirector;

final class DashboardController
{
    public function __construct(
        private readonly ?SessionStore $session = null,
        private readonly ?Redirector $redirector = null,
    ) {
    }

    public function index(): string
    {
        $session = $this->session();
        $user = $session->user();
        if ($user === null) {
            $this->redirector()->redirect(App::url('/login'));
            return '';
        }

        $name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8');
        $logoutAction = htmlspecialchars(App::url('/logout'), ENT_QUOTES, 'UTF-8');
        $homeUrl = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $healthUrl = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');
        $materialsUrl = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehousesUrl = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main class="app-shell">
  <aside class="sidebar">
    <strong>Factory ERP</strong>
    <nav>
      <a href="{$homeUrl}">&#x4EEA;&#x8868;&#x76D8;</a>
      <a href="{$materialsUrl}">&#x7269;&#x6599;&#x6863;&#x6848;</a>
      <a href="{$warehousesUrl}">&#x4ED3;&#x5E93;&#x6863;&#x6848;</a>
      <a href="{$healthUrl}">&#x5065;&#x5EB7;&#x68C0;&#x67E5;</a>
    </nav>
    <form class="logout-form" method="post" action="{$logoutAction}">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <button type="submit">&#x9000;&#x51FA;&#x767B;&#x5F55;</button>
    </form>
  </aside>
  <section class="content">
    <p class="eyebrow">&#x751F;&#x4EA7;&#x5DE5;&#x4F5C;&#x53F0;</p>
    <h1>&#x4ECA;&#x5929;&#x9700;&#x8981;&#x5173;&#x6CE8;&#x7684;&#x4E1A;&#x52A1;</h1>
    <p class="muted">&#x5F53;&#x524D;&#x7528;&#x6237;&#xFF1A;{$name}</p>
    <div class="metric-grid">
      <article><span>&#x7F3A;&#x6599;&#x9884;&#x8B66;</span><strong>0 &#x9879;</strong></article>
      <article><span>&#x5F85;&#x91C7;&#x8D2D;&#x5EFA;&#x8BAE;</span><strong>0 &#x6761;</strong></article>
      <article><span>&#x5F85;&#x9886;&#x6599;&#x5DE5;&#x5355;</span><strong>0 &#x5F20;</strong></article>
      <article><span>&#x5F85;&#x5B8C;&#x5DE5;&#x5165;&#x5E93;</span><strong>0 &#x5F20;</strong></article>
    </div>
    <p class="muted empty-note">&#x5148;&#x7EF4;&#x62A4;&#x7269;&#x6599;&#x548C;&#x4ED3;&#x5E93;&#xFF0C;&#x5E93;&#x5B58;&#x6D41;&#x6C34;&#x4E0A;&#x7EBF;&#x540E;&#x5C06;&#x81EA;&#x52A8;&#x8BA1;&#x7B97;&#x8FD9;&#x4E9B;&#x6307;&#x6807;&#x3002;</p>
    <section class="quick-panel">
      <h2>&#x5FEB;&#x6377;&#x5165;&#x53E3;</h2>
      <div class="quick-grid">
        <a href="{$materialsUrl}">&#x7269;&#x6599;&#x6863;&#x6848;</a>
        <a href="{$warehousesUrl}">&#x4ED3;&#x5E93;&#x6863;&#x6848;</a>
        <span class="disabled-link">BOM &#x7BA1;&#x7406; <small>&#x5F00;&#x53D1;&#x4E2D;</small></span>
        <span class="disabled-link">&#x5E93;&#x5B58;&#x6D41;&#x6C34; <small>&#x5F00;&#x53D1;&#x4E2D;</small></span>
        <span class="disabled-link">&#x751F;&#x4EA7;&#x5DE5;&#x5355; <small>&#x5F00;&#x53D1;&#x4E2D;</small></span>
      </div>
    </section>
  </section>
</main>
HTML;

        return View::page('Dashboard', $body);
    }

    public function health(): string
    {
        return 'Factory ERP OK';
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
