<?php

namespace App\Http\Controllers;

use App\Enums\ActivityVisibility;
use App\Models\Activity;
use Illuminate\Contracts\View\View;

class ActivityController extends Controller
{
    public function show(Activity $activity): View
    {
        abort_if(
            $activity->visibility === ActivityVisibility::Members && ! auth()->check(),
            403,
            'Deze activiteit is alleen zichtbaar voor ingelogde leden.'
        );

        abort_if(
            $activity->visibility === ActivityVisibility::Staff && ! auth()->user()?->can('activities.view'),
            403,
            'Deze activiteit is alleen zichtbaar voor beheer.'
        );

        return view('activities.show', [
            'activity' => $activity->load(['category', 'enrollments.person']),
        ]);
    }
}
