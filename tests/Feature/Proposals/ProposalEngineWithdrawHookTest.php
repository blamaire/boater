<?php

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Enums\ProposalStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewPolicy;
use App\Models\Template;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalEngine;
use App\Services\Proposals\ProposalHandlerRegistry;
use Tests\Support\FakeProposalHandler;

beforeEach(function () {
    $this->engine = app(ProposalEngine::class);
});

it('zet een PageVersion terug naar concept als het bijbehorende voorstel wordt ingetrokken', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'test-pagina',
        'title' => 'Test pagina',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $author = Person::create(['first_name' => 'A', 'last_name' => 'Uteur']);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::InReview,
        'created_by_person_id' => $author->id,
    ]);

    $proposal = $this->engine->submit(
        subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
        changeType: ChangeType::Create,
        payload: ['page_id' => $page->id],
        proposer: $author,
        subjectId: $version->id,
    );

    $this->engine->withdraw($proposal, $author);

    expect($version->refresh()->status)->toBe(PageVersionStatus::Draft);
});

it('laat een reeds gepubliceerde PageVersion onaangeroerd bij intrekken van een oud voorstel', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'test-pagina-2',
        'title' => 'Test pagina 2',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $author = Person::create(['first_name' => 'A', 'last_name' => 'Uteur']);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
        'created_by_person_id' => $author->id,
    ]);

    // Bewust los van submit()/apply() een voorstel aanmaken dat naar deze
    // (inmiddels gepubliceerde) versie wijst, om de status-guard in
    // onWithdrawn() te testen zonder de revalidatie-exception te raken.
    $proposal = Proposal::create([
        'subject_type' => PageVersionProposalHandler::SUBJECT_TYPE,
        'subject_id' => $version->id,
        'change_type' => ChangeType::Create,
        'payload' => ['page_id' => $page->id],
        'proposed_by_person_id' => $author->id,
        'status' => ProposalStatus::Submitted,
        'current_step' => 0,
    ]);

    $this->engine->withdraw($proposal, $author);

    expect($version->refresh()->status)->toBe(PageVersionStatus::Published);
});

it('blijft werken bij het intrekken van een niet-geregistreerd subject_type', function () {
    app(ProposalHandlerRegistry::class)->register('test.subject', new FakeProposalHandler);

    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer']);

    $policy = ReviewPolicy::create([
        'name' => 'Zonder handler',
        'subject_type' => 'ad-hoc.unregistered',
        'auto_apply' => false,
        'steps' => [],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit(
        subjectType: 'ad-hoc.unregistered',
        changeType: ChangeType::Update,
        payload: ['x' => 1],
        proposer: $proposer,
        policy: $policy,
    );

    $withdrawn = $this->engine->withdraw($proposal, $proposer);

    expect($withdrawn->status)->toBe(ProposalStatus::Withdrawn);
});
