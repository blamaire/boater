<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $btw_code_id
 * @property int $debtor_person_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property string $amount
 * @property string|null $period
 * @property ChargeStatus $status
 * @property int|null $invoice_id
 * @property Carbon|null $due_at
 * @property-read Product $product
 * @property-read BtwCode|null $btwCode
 * @property-read Person $debtor
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, Charge> $credits
 */
class Charge extends Model
{
    protected $fillable = [
        'product_id',
        'btw_code_id',
        'debtor_person_id',
        'subject_type',
        'subject_id',
        'description',
        'amount',
        'period',
        'status',
        'invoice_id',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => ChargeStatus::class,
            'due_at' => 'date',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<BtwCode, $this> */
    public function btwCode(): BelongsTo
    {
        return $this->belongsTo(BtwCode::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'debtor_person_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Creditregels die deze post corrigeren (§23.6 B3): posten met
     * `subject_type = Charge::class`, `subject_id = $this->id`, negatief bedrag.
     *
     * @return HasMany<Charge, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(self::class, 'subject_id')->where('subject_type', self::class);
    }

    /** @param  Builder<Charge>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ChargeStatus::Open->value);
    }

    public function creditedAmount(): string
    {
        return number_format(abs((float) $this->credits()->sum('amount')), 2, '.', '');
    }

    public function remainingCreditable(): string
    {
        return number_format((float) $this->amount + (float) $this->credits()->sum('amount'), 2, '.', '');
    }
}
