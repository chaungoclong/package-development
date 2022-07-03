<?php

namespace App\Repositories;

use App\Models\User;

class UserRepositoryEloquent extends BaseRepository
{
    public function model(): string
    {
        return User::class;
    }
}
