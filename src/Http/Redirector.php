<?php

declare(strict_types=1);

namespace Erp\Http;

interface Redirector
{
    public function redirect(string $path): void;
}

