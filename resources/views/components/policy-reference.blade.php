@php
    /**
     * Toont welke centrale goedkeuringsgroep beslist over voorstellen
     * van een bepaald subject_type. Zorgt dat elke module verwijst naar
     * de centrale lijst i.p.v. eigen groepen aan te leggen (§8/§20/§26).
     *
     * Gebruik: <x-policy-reference subject="reservation.create" />
     */
    $policy = \App\Models\ReviewPolicy::query()->where('subject_type', $subject)->first();
    $groupId = null;
    if ($policy !== null) {
        foreach ($policy->steps as $step) {
            if (($step['assignee_type'] ?? null) === \App\Enums\AssigneeType::Group->value) {
                $groupId = (int) $step['assignee_id'];
                break;
            }
        }
    }
    $group = $groupId !== null ? \App\Models\ApproverGroup::query()->find($groupId) : null;
@endphp

@if ($policy !== null && $group !== null)
    <div class="mt-4 rounded-md bg-gray-50 border border-gray-200 text-xs text-gray-700 px-3 py-2 flex flex-wrap items-center gap-1">
        <span class="text-gray-500">Voorstellen worden beoordeeld door groep</span>
        <span class="font-medium text-gray-900">{{ $group->name }}</span>
        @can('approver_groups.manage')
            <a href="{{ route('admin.approver-groups.index') }}" class="text-rzvg-700 hover:text-rzvg-900 ml-1">(centraal beheer →)</a>
        @endcan
    </div>
@endif
