<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeContract extends Model
{
    protected $fillable = [
        'name', 'code', 'description',
    ];
}
