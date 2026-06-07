<?php

declare(strict_types=1);

namespace Erp\Core;

final class View
{
    /**
     * @param array<string, string> $data
     */
    public static function page(string $title, string $body, array $data = []): string
    {
        $appName = htmlspecialchars($data['appName'] ?? 'Factory ERP', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        $asset = htmlspecialchars(App::asset('/assets/app.css'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} - {$appName}</title>
  <link rel="stylesheet" href="{$asset}">
</head>
<body>
  {$body}
</body>
</html>
HTML;
    }
}
