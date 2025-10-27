<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'institution_id',
        'department_code',
        'head_id',
        'description',
        'is_active',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

}
