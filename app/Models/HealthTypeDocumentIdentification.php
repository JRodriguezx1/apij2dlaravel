<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HealthTypeDocumentIdentification extends Model
{
    protected $fillable = [
        'name', 'code',
    ];
}
