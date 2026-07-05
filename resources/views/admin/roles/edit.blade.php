<x-app-layout>
    <x-slot name="header">Rol bewerken: {{ $role->name }}</x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div class="flex items-baseline justify-between">
            @if ($role->is_system)
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">systeem-rol</span>
            @else
                <span></span>
            @endif
            <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Terug naar overzicht</a>
        </div>
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
