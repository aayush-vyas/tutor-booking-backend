<?php

namespace App\Dto\Auth;

class RegisterDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $role
    ) {}
}
