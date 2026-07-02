<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Contracts\View\View;

/**
 * §19.2 — Ledenadministratie: overzicht en detail.
 *
 * De administratie ziet álle gegevens, los van de vlaggen die de
 * "Leden zoeken"-module respecteert (§21.3, "Onderscheid met Ledenbeheer").
 */
class LedenController extends Controller
{
    public function index(): View
    {
        return view('admin.leden.index');
    }

    public function show(Person $person): View
    {
        return view('admin.leden.show', [
            'person' => $person,
        ]);
    }
}
