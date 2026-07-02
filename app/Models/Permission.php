<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $key
 * @property string $module
 * @property string $action
 * @property string|null $description
 * @property bool $is_sensitive
 */
class Permission extends Model
{
    protected $fillable = [
        'key',
        'module',
        'action',
        'description',
        'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }
}
