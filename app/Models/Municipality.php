<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    use HasFactory;
    /**
     * With default model.
     *
     * @var array
     */
    protected $with = [
        'department',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'department_id', 'name', 'code', 'codefacturador',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        //'department_id',
    ];

    /**
     * Get the department identification that owns the department.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
