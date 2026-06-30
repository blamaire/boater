<?php

namespace Tests\Browser;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_logged_in_user_sees_dashboard_with_their_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Anne Tester',
            'email' => 'anne@example.test',
            'password' => bcrypt('geheim123'),
            'email_verified_at' => now(),
        ]);
        Person::create([
            'first_name' => 'Anne',
            'last_name' => 'Tester',
            'account_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', 'anne@example.test')
                ->type('password', 'geheim123')
                ->press('Inloggen')
                ->waitForLocation('/dashboard')
                ->assertSee('Welkom, Anne')
                ->assertSee('Dashboard')
                ->assertSee('Voorstellen')
                ->assertSee('binnenkort');
        });
    }
}
