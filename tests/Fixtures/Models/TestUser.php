<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
    ];
}
