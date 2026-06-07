<?php

declare(strict_types=1);

namespace Erp\Auth;

interface SessionStore
{
    /**
     * @return null|array{id:int,email:string,name:string}
     */
    public function user(): ?array;

    /**
     * @param array{id:int,email:string,name:string} $user
     */
    public function setUser(array $user): void;

    public function clearUser(): void;

    public function regenerate(): void;

    public function csrfToken(): string;

    public function verifyCsrf(?string $token): bool;
}

