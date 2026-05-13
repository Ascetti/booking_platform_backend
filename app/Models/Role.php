<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['slug', 'name'];

    public function permissions(): BelongsToMany {
        return $this->belongsToMany(Permission::class, 'permission_role')
        ->withTimestamps();
    }

    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'role_user')
        ->withPivot('hotel_id')
        ->withTimestamps();
    }
}
