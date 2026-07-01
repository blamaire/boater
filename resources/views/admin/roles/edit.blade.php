<x-app-layout>
    <x-slot name="header">
        <div class="flex items-baseline justify-between">
            <h1 class="font-display text-2xl text-gray-900">
                Rol bewerken: {{ $role->name }}
                @if ($role->is_system)
                    <span class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 align-middle">systeem</span>
                @endif
            </h1>
            <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Terug naar overzicht</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            @include('admin.roles._form', [
                'role' => $role,
                'permissionsByModule' => $permissionsByModule,
                'selectedPermissionIds' => $selectedPermissionIds,
                'action' => route('admin.roles.update', $role),
                'method' => 'PATCH',
            ])
        </div>
    </div>
</x-app-layout>
