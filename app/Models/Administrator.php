<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Administrator extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'identification_number', 'dv', 'name', 'address', 'phone', 'email', 'contact_name', 'password', 'plan', 'state', 'observation',
    ];

}
