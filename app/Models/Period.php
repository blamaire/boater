<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Periode binnen een boekjaar. Nummer 0 is de beginbalans (marker, geen
 * echte datumrange); 1-12 zijn de kalendermaanden januari t/m december.
 *
 * @property int $id
 * @property int $fiscal_year_id
 * @property int $number
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $closed_at
 * @property-read FiscalYear $fiscalYear
 * @property-read Collection<int, JournalEntry> $journalEntries
 */
class Period extends Model
{
    protected $fillable = [
        'fiscal_year_id',
        'number',
        'start_date',
        'end_date',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return HasMany<JournalEntry, $this> */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isOpeningBalance(): bool
    {
        return $this->number === 0;
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function label(): string
    {
        return $this->isOpeningBalance() ? 'Beginbalans' : $this->start_date->translatedFormat('F Y');
    }
}
