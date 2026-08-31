<?php

namespace App\Http\Controllers;

use App\Enums\ActivityVisibility;
use App\Models\ActivitySeries;
use Illuminate\Contracts\View\View;

class ActivitySeriesController extends Controller
{
    public function show(ActivitySeries $series): View
    {
        abort_if(
            $series->visibility === ActivityVisibility::Members && ! auth()->check(),
            403,
            'Deze reeks is alleen zichtbaar voor ingelogde leden.'
        );

        abort_if(
            $series->visibility === ActivityVisibility::Staff && ! auth()->user()?->can('activities.view'),
            403,
            'Deze reeks is alleen zichtbaar voor beheer.'
        );

        abort_if(
            ! $series->isWithinPublishWindow() && ! auth()->user()?->can('activities.view'),
            404
        );

        return view('activities.series', [
            'series' => $series->load(['category', 'activities.enrollments']),
        ]);
    }
}
