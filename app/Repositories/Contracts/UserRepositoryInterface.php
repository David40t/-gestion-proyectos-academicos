<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function create(array $attributes): User;
}
