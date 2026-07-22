<?php

namespace App\View\Composers;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Vult `$buildVersion` (git tag + commit, bv. "v0.9.0-12-gabc1234") en
 * `$environmentLabel` (Lokaal/Test/Acceptatie/Productie) voor de
 * sidebar-footer, zodat direct zichtbaar is welke omgeving en welke
 * code-versie voor je staat.
 */
class BuildInfoComposer
{
    public function compose(View $view): void
    {
        $view->with('buildVersion', $this->resolveVersion());
        $view->with('environmentLabel', $this->resolveEnvironmentLabel());
    }

    private function resolveVersion(): string
    {
        try {
            $process = new Process(['git', 'describe', '--tags', '--always'], base_path());
            $process->run();

            if (! $process->isSuccessful()) {
                return 'onbekend';
            }

            return trim($process->getOutput()) ?: 'onbekend';
        } catch (Throwable) {
            return 'onbekend';
        }
    }

    private function resolveEnvironmentLabel(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?? '';

        return match (true) {
            Str::contains($host, 'rzvg-tst') => 'Test',
            Str::contains($host, 'rzvg-acc') => 'Acceptatie',
            $host === 'localhost' || $host === '127.0.0.1' => 'Lokaal',
            default => 'Productie',
        };
    }
}
