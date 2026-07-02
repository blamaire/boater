<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FailedJobsController extends Controller
{
    public function index(): View
    {
        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get()
            ->map(fn ($job) => [
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'payload_preview' => $this->truncate((string) $job->payload, 200),
                'exception_preview' => $this->truncate((string) $job->exception, 500),
                'failed_at' => $job->failed_at,
            ]);

        return view('admin.failed-jobs.index', [
            'jobs' => $jobs,
        ]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        abort_unless($exists, 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return redirect()->route('admin.failed-jobs.index')
            ->with('status', "Job opnieuw ingepland ({$uuid}).");
    }

    public function destroy(string $uuid): RedirectResponse
    {
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        abort_unless($exists, 404);

        Artisan::call('queue:forget', ['id' => $uuid]);

        return redirect()->route('admin.failed-jobs.index')
            ->with('status', "Job verwijderd ({$uuid}).");
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length).'…';
    }
}
