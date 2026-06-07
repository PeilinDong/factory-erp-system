<?php

declare(strict_types=1);

namespace Erp\Http;

final class NativeRedirector implements Redirector
{
    public function redirect(string $path): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path, true, 302);
        }
    }
}

