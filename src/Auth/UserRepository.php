<?php

declare(strict_types=1);

namespace Erp\Auth;

interface UserRepository
{
    /**
     * @return null|array{id:int,email:string,name:string,password_hash:string,is_active:int}
     */
    public function findByEmail(string $email): ?array;

    public function createAdmin(string $email, string $passwordHash, string $name = '管理员'): void;
}

