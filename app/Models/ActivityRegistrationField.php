<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een extra inschrijfveld op een voorkomen (§17.3/17.4, Fase C): tekst, een
 * keuze uit `options` (elk met een eigen indicatieve prijs), of een aantal
 * met `price_per_unit`. Prijzen zijn hier indicatief — nog geen koppeling met
 * Product/Charge (Fase D).
 *
 * @property int $id
 * @property int $activity_id
 * @property string $type
 * @property string $label
 * @property bool $required
 * @property int $sort_order
 * @property float|null $price_per_unit
 * @property int|null $max_count
 * @property-read Activity $activity
 * @property-read Collection<int, ActivityRegistrationFieldOption> $options
 */
class ActivityRegistrationField extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_CHOICE = 'choice';

    public const TYPE_COUNT = 'count';

    protected $fillable = [
        'activity_id',
        'type',
        'label',
        'required',
        'sort_order',
        'price_per_unit',
        'max_count',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'bool',
            'sort_order' => 'int',
            'price_per_unit' => 'float',
            'max_count' => 'int',
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return HasMany<ActivityRegistrationFieldOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ActivityRegistrationFieldOption::class, 'field_id')->orderBy('sort_order');
    }
}
