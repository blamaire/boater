@props(['proposal'])

@php
    $presenter = app(\App\Services\Proposals\ProposalPresenter::class);
@endphp

<div {{ $attributes->class(['space-y-2']) }}>
    <div class="font-medium text-gray-900">{{ $presenter->summary($proposal) }}</div>
    @include($presenter->partial($proposal), $presenter->data($proposal))
</div>
