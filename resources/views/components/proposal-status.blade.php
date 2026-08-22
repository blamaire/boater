@props(['proposal'])

@php
    $badgeClasses = match ($proposal->status) {
        \App\Enums\ProposalStatus::Applied => 'bg-green-50 text-green-700 border-green-200',
        \App\Enums\ProposalStatus::Rejected, \App\Enums\ProposalStatus::Conflicted => 'bg-red-50 text-red-700 border-red-200',
        \App\Enums\ProposalStatus::Returned => 'bg-amber-50 text-amber-700 border-amber-200',
        \App\Enums\ProposalStatus::Withdrawn => 'bg-gray-100 text-gray-600 border-gray-200',
        default => 'bg-blue-50 text-blue-700 border-blue-200',
    };

    $reasonLabel = match ($proposal->status) {
        \App\Enums\ProposalStatus::Rejected => 'Reden van afwijzing',
        \App\Enums\ProposalStatus::Returned => 'Toelichting van de beoordelaar',
        \App\Enums\ProposalStatus::Conflicted => 'Reden',
        default => null,
    };

    $currentStep = $proposal->status->isOpen() ? $proposal->currentStep() : null;
@endphp

<div class="flex flex-wrap items-center gap-2">
    <span {{ $attributes->class(['inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap', $badgeClasses]) }}>
        {{ $proposal->status->label() }}
    </span>
    @if ($currentStep)
        <span class="text-xs text-gray-500">wacht op {{ $currentStep->assigneeName() }}</span>
    @endif
</div>

@if ($reasonLabel && $proposal->decision_reason)
    <div class="mt-1 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
        <span class="font-medium text-gray-600">{{ $reasonLabel }}:</span> {{ $proposal->decision_reason }}
    </div>
@endif
