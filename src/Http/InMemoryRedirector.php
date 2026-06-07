<?php

declare(strict_types=1);

namespace Erp\Http;

final class InMemoryRedirector implements Redirector
{
    private ?string $lastLocation = null;

    public function redirect(string $path): void
    {
        $this->lastLocation = $path;
    }

    public function lastLocation(): ?string
    {
        return $this->lastLocation;
    }
}

