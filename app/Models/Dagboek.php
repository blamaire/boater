<?php

namespace App\Models;

use App\Enums\DagboekType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property DagboekType $type
 * @property string $name
 * @property-read Collection<int, JournalEntry> $journalEntries
 */
class Dagboek extends Model
{
    protected $table = 'dagboeken';

    protected $fillable = [
        'type',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'type' => DagboekType::class,
        ];
    }

    /** @return HasMany<JournalEntry, $this> */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
