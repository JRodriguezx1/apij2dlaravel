<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuerParty extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'identification_number', 'first_name', 'last_name', 'organization_department','job_title',
    ];
}
