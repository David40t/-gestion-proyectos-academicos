<?php

namespace App\Repositories\Contracts;

use App\Models\Audit;

interface AuditRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Audit;
}
