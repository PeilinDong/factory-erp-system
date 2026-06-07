<?php

declare(strict_types=1);

namespace Erp\Auth;

final class NativeSessionStore implements SessionStore
{
    public function user(): ?array
    {
        $this->start();
        return $_SESSION['user'] ?? null;
    }

    public function setUser(array $user): void
    {
        $this->start();
        $_SESSION['user'] = $user;
    }

    public function clearUser(): void
    {
        $this->start();
        unset($_SESSION['user']);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function csrfToken(): string
    {
        $this->start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }

    private function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (headers_sent()) {
                $_SESSION ??= [];
                return;
            }
            session_start();
        }
    }
}
