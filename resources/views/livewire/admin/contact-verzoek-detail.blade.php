<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <a href="{{ route('admin.contact-requests.index') }}" class="text-sm text-rzvg-600 hover:underline">&larr; Terug naar contactverzoeken</a>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-display text-xl text-rzvg-600">{{ $contactRequest->name }}</h2>
                <p class="text-sm text-gray-500">Ingediend op {{ $contactRequest->created_at?->format('d-m-Y H:i') }}</p>
            </div>
            <select wire:change="updateStatus($event.target.value)" class="border-gray-300 rounded shadow-sm text-sm">
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected($contactRequest->status === $st)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>

        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-gray-500">Onderwerp</dt>
                <dd class="text-gray-900">{{ $contactRequest->topic?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Verantwoordelijke</dt>
                <dd class="text-gray-900">{{ $contactRequest->topic?->responsible?->fullName() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Voorkeur</dt>
                <dd class="text-gray-900">{{ $contactRequest->contactMethodLabel() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Telefoon</dt>
                <dd class="text-gray-900">
                    @if ($contactRequest->phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $contactRequest->phone) }}" class="hover:text-rzvg-600">{{ $contactRequest->phone }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">E-mail</dt>
                <dd class="text-gray-900">
                    @if ($contactRequest->email)
                        <a href="mailto:{{ $contactRequest->email }}" class="hover:text-rzvg-600">{{ $contactRequest->email }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">IP-adres</dt>
                <dd class="text-gray-900">{{ $contactRequest->ip_address ?? '—' }}</dd>
            </div>
        </dl>

        <div>
            <dt class="text-gray-500 text-sm">Bericht</dt>
            <dd class="text-gray-900 whitespace-pre-line mt-1">{{ $contactRequest->message }}</dd>
        </div>
    </section>
</div>
