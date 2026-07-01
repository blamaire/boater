import './bootstrap';

// Alpine wordt door Livewire 3 zelf geladen en op window gezet;
// het handmatig importeren van 'alpinejs' hier veroorzaakt een
// tweede Alpine-instantie en breekt Livewire's $wire-injectie.

import 'trix';
import 'trix/dist/trix.css';
