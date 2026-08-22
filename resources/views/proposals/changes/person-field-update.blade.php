@php
    /** @var string $fieldLabel */
    /** @var string|null $oldValue */
    /** @var string|null $newValue */
@endphp

<div class="text-sm text-gray-600">
    <span class="line-through text-gray-400">{{ $oldValue !== null && $oldValue !== '' ? $oldValue : '(leeg)' }}</span>
    <span class="mx-1">&rarr;</span>
    <span class="font-medium text-gray-900">{{ $newValue !== null && $newValue !== '' ? $newValue : '(leeg)' }}</span>
</div>
