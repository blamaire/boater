<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dagboek_id
 * @property Carbon $date
 * @property string $description
 * @property string|null $reference
 * @property-read Dagboek $dagboek
 * @property-read Collection<int, JournalLine> $lines
 */
class JournalEntry extends Model
{
    protected $fillable = [
        'dagboek_id',
        'date',
        'description',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Dagboek, $this> */
    public function dagboek(): BelongsTo
    {
        return $this->belongsTo(Dagboek::class);
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
