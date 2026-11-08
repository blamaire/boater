{{-- Wachtwoord wijzigen (voorheen op de Breeze-profielpagina). Gewoon formulier
     naar de bestaande `password.update`-route; die stuurt terug naar deze pagina
     met status 'password-updated' of fouten in de bag 'updatePassword'. --}}
<section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4" id="wachtwoord">
    <header>
        <h2 class="font-display text-xl text-gray-900">Wachtwoord wijzigen</h2>
        <p class="mt-1 text-sm text-gray-500">Gebruik een lang wachtwoord dat je nergens anders gebruikt.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-4 max-w-md">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Huidig wachtwoord" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Nieuw wachtwoord" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Herhaal nieuw wachtwoord" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="text-sm px-3 py-1.5 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">Wachtwoord opslaan</button>

            @if (session('status') === 'password-updated')
                <p class="text-sm text-green-700">Je wachtwoord is gewijzigd.</p>
            @endif
        </div>
    </form>
</section>
