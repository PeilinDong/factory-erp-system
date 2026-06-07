<?php

declare(strict_types=1);

namespace Erp\Auth;

final class InMemorySessionStore implements SessionStore
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private int $regenerateCount = 0;

    public function user(): ?array
    {
        return $this->data['user'] ?? null;
    }

    public function setUser(array $user): void
    {
        $this->data['user'] = $user;
    }

    public function clearUser(): void
    {
        unset($this->data['user']);
    }

    public function regenerate(): void
    {
        $this->regenerateCount++;
    }

    public function regenerateCount(): int
    {
        return $this->regenerateCount;
    }

    public function csrfToken(): string
    {
        if (!isset($this->data['csrf_token'])) {
            $this->data['csrf_token'] = bin2hex(random_bytes(16));
        }

        return $this->data['csrf_token'];
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }
}

