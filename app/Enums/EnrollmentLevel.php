<?php

namespace App\Enums;

/**
 * Hoe een groep activiteiten (`ActivitySeries`) wordt aangeboden om je voor
 * in te schrijven (§17.4). Een **bundel** hoort inhoudelijk bij elkaar en
 * wordt gezamenlijk bewerkt, maar je meldt je per activiteit apart aan. Een
 * **reeks** meldt je in één keer aan voor alles. `ActivitySeries.enrollment_level`
 * legt dit per groep vast; `Enrollment.level` registreert hoe een specifieke
 * inschrijving tot stand kwam (altijd hetzelfde als de groep, behalve dat een
 * losse activiteit zonder groep ook `Bundel` gebruikt als standaardwaarde).
 */
enum EnrollmentLevel: string
{
    case Bundel = 'bundel';
    case Reeks = 'reeks';

    public function label(): string
    {
        return match ($this) {
            self::Bundel => 'Bundel — apart aanmelden per activiteit',
            self::Reeks => 'Reeks — in één keer aanmelden voor alles',
        };
    }

    public function allowsPerVoorkomen(): bool
    {
        return $this === self::Bundel;
    }

    public function allowsSerie(): bool
    {
        return $this === self::Reeks;
    }
}
