<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class Permission extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'description', 'institution_id'];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function scopeSameInstitute($query)
    {
        return $query->where('institution_id', Auth::user()->institution_id);
    }






}
