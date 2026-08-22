<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Livewire\FeedbackWidget;
use App\Models\Band;
use App\Models\Block;
use App\Models\Feedback;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

function feedbackTestPage(): array
{
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'over-ons',
        'title' => 'Over ons',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Heading,
        'content' => ['level' => 1, 'text' => 'Over ons'],
    ]);
    $page->update(['published_version_id' => $version->id]);

    return [$page, $version];
}

function feedbackTestUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'Feed', 'last_name' => 'Back', 'account_id' => $user->id]);

    return $user;
}

it('toont het terugkoppelknopje op een publieke pagina alleen voor ingelogde gebruikers', function () {
    [$page] = feedbackTestPage();

    $this->get($page->publicUrl())->assertOk()->assertDontSee('Terugkoppeling');

    $this->actingAs(feedbackTestUser())
        ->get($page->publicUrl())
        ->assertOk()
        ->assertSee('Terugkoppeling');
});

it('slaat terugkoppeling op met pagina- en versiecontext', function () {
    [$page, $version] = feedbackTestPage();
    $user = feedbackTestUser();

    Livewire::actingAs($user)
        ->test(FeedbackWidget::class, ['pageId' => $page->id, 'pageVersionId' => $version->id])
        ->set('category', 'bug')
        ->set('message', 'De knop doet het niet.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $feedback = Feedback::query()->firstOrFail();
    expect($feedback->person_id)->toBe($user->person->id)
        ->and($feedback->category->value)->toBe('bug')
        ->and($feedback->message)->toBe('De knop doet het niet.')
        ->and($feedback->page_id)->toBe($page->id)
        ->and($feedback->page_version_id)->toBe($version->id)
        ->and($feedback->status->value)->toBe('nieuw')
        ->and($feedback->app_version)->not->toBeNull();
});

it('slaat terugkoppeling zonder paginacontext op met lege page_id/page_version_id (bv. vanuit het portaal)', function () {
    $user = feedbackTestUser();

    Livewire::actingAs($user)
        ->test(FeedbackWidget::class)
        ->set('category', 'suggestie')
        ->set('message', 'Fijn als het menu ook X kan.')
        ->call('submit')
        ->assertHasNoErrors();

    $feedback = Feedback::query()->firstOrFail();
    expect($feedback->page_id)->toBeNull()
        ->and($feedback->page_version_id)->toBeNull();
});

it('weigert een lege categorie of leeg bericht', function () {
    Livewire::actingAs(feedbackTestUser())
        ->test(FeedbackWidget::class)
        ->set('category', '')
        ->set('message', '')
        ->call('submit')
        ->assertHasErrors(['category', 'message']);

    expect(Feedback::count())->toBe(0);
});

it('toont een melding i.p.v. op te slaan als het account niet aan een persoon is gekoppeld', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test(FeedbackWidget::class)
        ->set('category', 'vraag')
        ->set('message', 'Test')
        ->call('submit')
        ->assertHasErrors('message');

    expect(Feedback::count())->toBe(0);
});
