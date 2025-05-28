<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthTypeUser extends Model
{
    protected $fillable = [
        'name', 'code',
    ];
}
