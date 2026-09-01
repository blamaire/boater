@props(['field'])

{{-- Kleine, toolbar-achtige knop om een variabele in te voegen in $field
     (een element-id) — zelfde openMenu()/insert()-Alpine-state als de
     ouder-modal (message-template-beheer.blade.php), geen eigen x-data
     nodig. --}}
<button type="button" x-on:click="openMenu('{{ $field }}', false)"
    class="shrink-0 inline-flex items-center justify-center h-6 min-w-[1.5rem] px-1 border border-gray-300 rounded text-[11px] font-mono text-gray-500 hover:bg-gray-100 hover:text-gray-800"
    title="Variabele invoegen">{ }</button>
