<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'description', 'key_responsibilities', 'icon'];

    /** Users that have this role */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Menu permission records for this role */
    public function menuPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menu_permissions', 'role_id', 'menu_id')
            ->withPivot('can_view', 'can_add', 'can_edit', 'can_delete')
            ->withTimestamps();
    }
}
