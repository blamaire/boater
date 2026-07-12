<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $number
 * @property int $debtor_person_id
 * @property InvoiceStatus $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property string $total
 * @property-read Person $debtor
 * @property-read Collection<int, Charge> $charges
 */
class Invoice extends Model
{
    protected $fillable = [
        'number',
        'debtor_person_id',
        'status',
        'issued_at',
        'due_at',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'debtor_person_id');
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
