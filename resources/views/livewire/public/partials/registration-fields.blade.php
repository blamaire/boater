@if ($fields->isNotEmpty())
    @php
        $total = 0.0;
        foreach ($fields as $rf) {
            $answer = $fieldAnswers[$rf->id] ?? null;
            if ($answer === null || $answer === '') {
                continue;
            }
            if ($rf->type === 'count') {
                $total += ($rf->price_per_unit ?? 0) * (float) $answer;
            } elseif ($rf->type === 'choice') {
                $option = $rf->options->firstWhere('id', (int) $answer);
                $total += $option->price ?? 0;
            }
        }
    @endphp

    <div class="space-y-3 border border-gray-200 rounded-md p-3">
        @foreach ($fields as $rf)
            <div>
                <label class="text-sm text-gray-700">
                    {{ $rf->label }}@if ($rf->required) <span class="text-red-600">*</span> @endif
                </label>

                @if ($rf->type === 'text')
                    <input type="text" wire:model.live="fieldAnswers.{{ $rf->id }}"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm text-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                @elseif ($rf->type === 'choice')
                    <select wire:model.live="fieldAnswers.{{ $rf->id }}"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm text-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies —</option>
                        @foreach ($rf->options as $option)
                            <option value="{{ $option->id }}">
                                {{ $option->label }}{{ $option->price ? ' (€'.number_format($option->price, 2, ',', '.').')' : '' }}
                            </option>
                        @endforeach
                    </select>
                @elseif ($rf->type === 'count')
                    <input type="number" min="0" @if ($rf->max_count) max="{{ $rf->max_count }}" @endif
                        wire:model.live="fieldAnswers.{{ $rf->id }}"
                        class="mt-1 w-32 border-gray-300 rounded shadow-sm text-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                    @if ($rf->price_per_unit)
                        <span class="text-xs text-gray-500 ml-2">€{{ number_format($rf->price_per_unit, 2, ',', '.') }} per stuk</span>
                    @endif
                @endif

                @error('fieldAnswers.'.$rf->id) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach

        @if ($total > 0)
            <div class="text-sm text-gray-700 border-t border-gray-200 pt-2">
                Indicatief totaal: <span class="font-medium">€{{ number_format($total, 2, ',', '.') }}</span>
                <span class="text-xs text-gray-500">(nog niet gefactureerd)</span>
            </div>
        @endif
    </div>
@endif
