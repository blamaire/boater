@php
    use App\Services\Proposals\Handlers\MembershipApplicationHandler;
    use App\Services\Proposals\Handlers\PageVersionProposalHandler;
    use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
    use App\Services\Proposals\Handlers\ReservationProposalHandler;
@endphp

<div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    @if (session('status'))
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ session('status') }}
        </div>
    @endif
    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($decidableSteps->isNotEmpty())
        <section class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Te beslissen</h3>

            @foreach ($decidableSteps as $step)
                <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3" wire:key="decidable-{{ $step->id }}">
                    <x-proposal-change :proposal="$step->proposal" />
                    <x-proposal-status :proposal="$step->proposal" />

                    <div class="text-xs text-gray-500">
                        Ingediend door {{ $step->proposal->proposedBy?->fullName() ?? 'onbekend' }}
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100">
                        <button type="button" wire:click="approve({{ $step->id }})" wire:confirm="Dit voorstel goedkeuren?"
                            class="px-3 py-1.5 text-sm rounded-md bg-green-600 text-white hover:bg-green-700">
                            Goedkeuren
                        </button>

                        <div x-data="{ open: false }">
                            <button type="button" @click="open = ! open"
                                class="px-3 py-1.5 text-sm rounded-md bg-red-50 text-red-700 hover:bg-red-100">
                                Afwijzen
                            </button>
                            <div x-show="open" x-cloak class="mt-2 space-y-1 max-w-sm">
                                <textarea wire:model="reasonInputs.{{ $step->id }}" rows="2" placeholder="Reden voor afwijzen"
                                    class="w-full text-sm border-gray-300 rounded-md focus:border-rzvg-600 focus:ring-rzvg-600"></textarea>
                                @error('reason.'.$step->id)
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="button" wire:click="reject({{ $step->id }})"
                                    class="px-3 py-1 text-xs rounded-md bg-red-600 text-white hover:bg-red-700">
                                    Bevestig afwijzen
                                </button>
                            </div>
                        </div>

                        <div x-data="{ open: false }">
                            <button type="button" @click="open = ! open"
                                class="px-3 py-1.5 text-sm rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">
                                Terugsturen
                            </button>
                            <div x-show="open" x-cloak class="mt-2 space-y-1 max-w-sm">
                                <textarea wire:model="reasonInputs.{{ $step->id }}" rows="2" placeholder="Toelichting voor de indiener"
                                    class="w-full text-sm border-gray-300 rounded-md focus:border-rzvg-600 focus:ring-rzvg-600"></textarea>
                                @error('reason.'.$step->id)
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="button" wire:click="returnToSubmitter({{ $step->id }})"
                                    class="px-3 py-1 text-xs rounded-md bg-gray-600 text-white hover:bg-gray-700">
                                    Bevestig terugsturen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Mijn voorstellen</h3>

        @forelse ($myOpenProposals as $proposal)
            <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-start justify-between gap-4" wire:key="my-open-{{ $proposal->id }}">
                <div class="flex-1 space-y-2">
                    <x-proposal-change :proposal="$proposal" />
                    <x-proposal-status :proposal="$proposal" />
                </div>
                <div class="shrink-0 flex items-center gap-1">
                    @php
                        $editHref = match ($proposal->subject_type) {
                            PersonFieldUpdateHandler::SUBJECT_TYPE => route('portal.mijn-lidmaatschap'),
                            MembershipApplicationHandler::SUBJECT_TYPE => route('portal.wijzigingsvoorstellen.membership-application.edit', $proposal),
                            ReservationProposalHandler::SUBJECT_TYPE => route('portal.wijzigingsvoorstellen.reservation.edit', $proposal),
                            default => null,
                        };
                    @endphp
                    @if ($proposal->subject_type === PageVersionProposalHandler::SUBJECT_TYPE)
                        <button type="button" wire:click="editPageProposal({{ $proposal->id }})"
                            wire:confirm="Dit voorstel wordt ingetrokken en heropend in de paginabewerker. Doorgaan?"
                            title="Aanpassen" aria-label="Aanpassen"
                            class="px-2 py-1 rounded text-gray-500 hover:text-gray-900 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25l3 3" />
                            </svg>
                        </button>
                    @elseif ($editHref)
                        <a href="{{ $editHref }}" title="Aanpassen" aria-label="Aanpassen"
                            class="px-2 py-1 rounded text-gray-500 hover:text-gray-900 hover:bg-gray-100 inline-flex">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25l3 3" />
                            </svg>
                        </a>
                    @endif
                    <button type="button" wire:click="withdraw({{ $proposal->id }})" wire:confirm="Dit voorstel intrekken?"
                        title="Intrekken" aria-label="Intrekken"
                        class="px-2 py-1 rounded text-gray-500 hover:text-red-600 hover:bg-red-50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M7.72 12.03a.75.75 0 0 1-1.06 0L2.47 7.84a.75.75 0 0 1 0-1.06l4.19-4.19a.75.75 0 1 1 1.06 1.06L4.81 6.56h6.44a5.5 5.5 0 1 1 0 11h-3.5a.75.75 0 0 1 0-1.5h3.5a4 4 0 0 0 0-8H4.81l2.91 2.91a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        @empty
            @if ($myClosedProposals->isEmpty() && $myRejectedProposals->isEmpty())
                <p class="text-sm text-gray-500 italic">Je hebt nog geen wijzigingen voorgesteld.</p>
            @endif
        @endforelse
    </section>

    @if ($myRejectedProposals->isNotEmpty())
        <section class="space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Afgewezen — actie nodig</h3>

            @foreach ($myRejectedProposals as $proposal)
                <div class="bg-white border border-red-200 rounded-lg p-4 space-y-2" wire:key="my-rejected-{{ $proposal->id }}">
                    <x-proposal-change :proposal="$proposal" />
                    <x-proposal-status :proposal="$proposal" />

                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100">
                        @php
                            $editHref = match ($proposal->subject_type) {
                                PersonFieldUpdateHandler::SUBJECT_TYPE => route('portal.mijn-lidmaatschap'),
                                MembershipApplicationHandler::SUBJECT_TYPE => route('portal.wijzigingsvoorstellen.membership-application.edit', $proposal),
                                ReservationProposalHandler::SUBJECT_TYPE => route('portal.wijzigingsvoorstellen.reservation.edit', $proposal),
                                default => null,
                            };
                        @endphp
                        @if ($proposal->subject_type === PageVersionProposalHandler::SUBJECT_TYPE)
                            <button type="button" wire:click="editPageProposal({{ $proposal->id }})"
                                wire:confirm="Dit voorstel wordt gearchiveerd en heropend in de paginabewerker. Doorgaan?"
                                class="px-3 py-1.5 text-sm rounded-md bg-rzvg-50 text-rzvg-700 hover:bg-rzvg-100">
                                Opnieuw indienen
                            </button>
                        @elseif ($editHref)
                            <a href="{{ $editHref }}" class="px-3 py-1.5 text-sm rounded-md bg-rzvg-50 text-rzvg-700 hover:bg-rzvg-100 inline-block">
                                Opnieuw indienen
                            </a>
                        @endif
                        <button type="button" wire:click="archive({{ $proposal->id }})" wire:confirm="Dit voorstel archiveren?"
                            class="px-3 py-1.5 text-sm rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">
                            Archiveren
                        </button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="space-y-3">
        @if ($myClosedProposals->isNotEmpty())
            <div x-data="{ open: false }" class="pt-2">
                <button type="button" @click="open = ! open" class="text-sm text-gray-500 hover:text-gray-700">
                    Afgehandeld ({{ $myClosedProposals->count() }})
                </button>
                <div x-show="open" x-cloak class="mt-2 space-y-2">
                    @foreach ($myClosedProposals as $proposal)
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-2" wire:key="my-closed-{{ $proposal->id }}">
                            <x-proposal-change :proposal="$proposal" />
                            <x-proposal-status :proposal="$proposal" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>
