<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property list<array{key: string, label: string}> $zones
 * @property-read Collection<int, Page> $pages
 */
class Template extends Model
{
    protected $fillable = ['name', 'zones'];

    protected function casts(): array
    {
        return [
            'zones' => 'array',
        ];
    }

    /** @return HasMany<Page, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
