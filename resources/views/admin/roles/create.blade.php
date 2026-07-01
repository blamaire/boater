<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">Nieuwe rol</h1>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            @include('admin.roles._form', [
                'role' => null,
                'permissionsByModule' => $permissionsByModule,
                'selectedPermissionIds' => $selectedPermissionIds,
                'action' => route('admin.roles.store'),
                'method' => 'POST',
            ])
        </div>
    </div>
</x-app-layout>
