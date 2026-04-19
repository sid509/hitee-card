<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'slug'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($permission) {
            $permission->slug = $permission->slug ?? Str::slug($permission->name);
        });
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
