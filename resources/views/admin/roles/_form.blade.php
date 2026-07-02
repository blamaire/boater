@php
    /** @var \App\Models\Role|null $role */
    /** @var array<string, \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission>> $permissionsByModule */
    /** @var array<int, int> $selectedPermissionIds */
    $moduleLabels = [
        'persons' => 'Personen',
        'roles' => 'Rollen',
        'activities' => 'Activiteiten',
        'reservations' => 'Reserveringen',
        'damage_reports' => 'Schademeldingen',
        'pages' => "Pagina's",
        'invoices' => 'Facturen',
        'ledger' => 'Grootboek',
        'mailings' => 'Mailings',
        'documents' => 'Documenten',
        'imports' => 'Imports',
        'volunteer_tasks' => 'Vrijwilligerstaken',
        'communication_log' => 'Communicatielogboek',
        'audit_trail' => 'Auditlogboek',
        'review_settings' => 'Reviewinstellingen',
        'media' => 'Media',
        'support' => 'Ondersteuning',
        'proposals' => 'Voorstellen',
    ];
    $isSystem = (bool) ($role?->is_system);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
            <ul class="list-disc ps-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Naam</label>
            <input type="text" id="name" name="name" required
                   value="{{ old('name', $role?->name) }}"
                   @disabled($isSystem)
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm disabled:bg-gray-100" />
            @if ($isSystem)
                <p class="mt-1 text-xs text-gray-500">Naam van systeem-rol is vast.</p>
            @endif
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Omschrijving</label>
            <textarea id="description" name="description" rows="2"
                      @disabled($isSystem)
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm disabled:bg-gray-100">{{ old('description', $role?->description) }}</textarea>
        </div>
    </div>

    <div class="space-y-3">
        <h2 class="text-sm font-semibold text-gray-900">Permissies</h2>
        @if ($isSystem)
            <p class="text-xs text-gray-500">De permissie-set van systeem-rollen is vast en niet wijzigbaar.</p>
        @endif

        @foreach ($permissionsByModule as $module => $permissions)
            @php
                $label = $moduleLabels[$module] ?? ucfirst($module);
                $moduleIds = collect($permissions)->pluck('id')->all();
                // Alpine's checkbox-x-model doet strikte === vergelijking; HTML value
                // attributen zijn altijd strings, dus we casten hier ook naar string
                // zodat de initiële 'checked'-state matcht en aangevinkte vakjes tonen.
                $stringModuleIds = array_map('strval', $moduleIds);
                $stringSelected = array_map('strval', array_values(array_intersect($moduleIds, old('permissions', $selectedPermissionIds) ?? [])));
            @endphp
            <div class="border border-gray-200 rounded-md p-4"
                 x-data="{
                     ids: @js($stringModuleIds),
                     checked: @js($stringSelected),
                     get allSelected() { return this.ids.length > 0 && this.checked.length === this.ids.length; },
                     get someSelected() { return this.checked.length > 0 && this.checked.length < this.ids.length; },
                     toggleAll(event) {
                         this.checked = event.target.checked ? [...this.ids] : [];
                     },
                 }">
                <label @class([
                    'flex items-center gap-2 text-sm font-medium text-gray-900 mb-2',
                    'cursor-pointer' => ! $isSystem,
                ])>
                    <input type="checkbox"
                           class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-500 disabled:bg-gray-100"
                           :checked="allSelected"
                           :indeterminate.prop="someSelected"
                           @change="toggleAll($event)"
                           @disabled($isSystem) />
                    <span>{{ $label }}</span>
                    <span class="text-xs text-gray-400" x-text="`(${checked.length}/${ids.length})`"></span>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1 ps-6">
                    @foreach ($permissions as $permission)
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $permission->id }}"
                                   x-model="checked"
                                   class="mt-0.5 rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-500 disabled:bg-gray-100"
                                   @disabled($isSystem) />
                            <span>
                                <span class="font-mono text-xs text-gray-500">{{ $permission->key }}</span>
                                @if ($permission->description)
                                    <span class="block text-xs text-gray-500">{{ $permission->description }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuleren</a>
        @unless ($isSystem)
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 transition">
                Opslaan
            </button>
        @endunless
    </div>
</form>
