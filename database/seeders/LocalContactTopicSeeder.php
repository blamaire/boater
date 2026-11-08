<?php

namespace Database\Seeders;

use App\Models\ContactTopic;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Standaard contactonderwerpen voor lokale ontwikkeling, zodat het publieke
 * contactformulier (/contact) iets te kiezen heeft. Elk onderwerp vereist een
 * verantwoordelijke; lokaal is dat de dev-beheerder (LocalDevUserSeeder).
 * Alleen bij `APP_ENV=local` en alleen als er nog geen onderwerpen zijn: op
 * test/productie stelt een beheerder ze in via Beheer → Contactonderwerpen.
 */
class LocalContactTopicSeeder extends Seeder
{
    private const TOPICS = ['Lidmaatschap', 'Proefles', 'Roeien', 'Zeilen', 'Suppen', 'Jeugd', 'Overig'];

    public function run(): void
    {
        if (app()->environment() !== 'local' || ContactTopic::query()->exists()) {
            return;
        }

        $userId = User::query()->where('email', 'beheerder@rzvg.local')->value('id');
        $responsible = $userId !== null ? Person::query()->where('account_id', $userId)->first() : null;
        if ($responsible === null) {
            return;
        }

        foreach (self::TOPICS as $sortOrder => $name) {
            ContactTopic::query()->create([
                'name' => $name,
                'responsible_person_id' => $responsible->id,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
