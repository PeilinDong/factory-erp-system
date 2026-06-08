<?php

declare(strict_types=1);

namespace Erp\Core;

final class Sidebar
{
    public static function render(?string $csrfToken = null, ?string $logoutAction = null): string
    {
        $home = htmlspecialchars(App::url('/'), ENT_QUOTES, 'UTF-8');
        $materials = htmlspecialchars(App::url('/materials'), ENT_QUOTES, 'UTF-8');
        $warehouses = htmlspecialchars(App::url('/warehouses'), ENT_QUOTES, 'UTF-8');
        $boms = htmlspecialchars(App::url('/boms'), ENT_QUOTES, 'UTF-8');
        $purchases = htmlspecialchars(App::url('/purchases'), ENT_QUOTES, 'UTF-8');
        $workOrders = htmlspecialchars(App::url('/work-orders'), ENT_QUOTES, 'UTF-8');
        $shortages = htmlspecialchars(App::url('/planning/shortages'), ENT_QUOTES, 'UTF-8');
        $inventory = htmlspecialchars(App::url('/inventory'), ENT_QUOTES, 'UTF-8');
        $balances = htmlspecialchars(App::url('/inventory/balances'), ENT_QUOTES, 'UTF-8');
        $trace = htmlspecialchars(App::url('/inventory/trace'), ENT_QUOTES, 'UTF-8');
        $users = htmlspecialchars(App::url('/users'), ENT_QUOTES, 'UTF-8');
        $health = htmlspecialchars(App::url('/health'), ENT_QUOTES, 'UTF-8');
        $logoutForm = '';

        if ($csrfToken !== null && $logoutAction !== null) {
            $csrf = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
            $logoutUrl = htmlspecialchars($logoutAction, ENT_QUOTES, 'UTF-8');
            $logoutForm = <<<HTML
  <form class="logout-form" method="post" action="{$logoutUrl}">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <button type="submit">退出登录</button>
  </form>
HTML;
        }

        return <<<HTML
<aside class="sidebar">
  <strong>Factory ERP</strong>
  <nav>
    <a href="{$home}">仪表盘</a>
    <a href="{$materials}">物料档案</a>
    <a href="{$warehouses}">仓库档案</a>
    <a href="{$boms}">BOM 管理</a>
    <a href="{$purchases}">采购订单</a>
    <a href="{$workOrders}">生产工单</a>
    <a href="{$shortages}">缺料分析</a>
    <a href="{$inventory}">库存流水</a>
    <a href="{$balances}">库存余额</a>
    <a href="{$trace}">批次追溯</a>
    <a href="{$users}">用户管理</a>
    <a href="{$health}">健康检查</a>
  </nav>
{$logoutForm}
</aside>
HTML;
    }
}
