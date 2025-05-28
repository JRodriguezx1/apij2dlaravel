<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCoverage extends Model
{
    protected $fillable = [
        'name', 'code',
    ];
}
