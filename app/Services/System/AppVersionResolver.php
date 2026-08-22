<?php

namespace App\Services\System;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Bepaalt de draaiende applicatieversie (git tag + commit, bv.
 * "v0.9.0-12-gabc1234") — gedeeld tussen `BuildInfoComposer` (sidebar-footer)
 * en `FeedbackWidget` (legt vast met welke versie terugkoppeling is gegeven).
 */
class AppVersionResolver
{
    public function current(): string
    {
        try {
            // PHP-FPM draait als root in de container (zie docker/php/Dockerfile),
            // terwijl de gemounte repo op test/acc eigendom is van de deploy-user
            // `rzvg`. Git 2.35+ weigert dan met "dubious ownership" tenzij dit
            // expliciet wordt toegestaan — `*` is veilig hier: de repo-inhoud komt
            // sowieso al uit onze eigen git-checkout, geen extern vertrouwen nodig.
            $process = new Process(
                ['git', '-c', 'safe.directory=*', 'describe', '--tags', '--always'],
                base_path()
            );
            $process->run();

            if (! $process->isSuccessful()) {
                return 'onbekend';
            }

            return trim($process->getOutput()) ?: 'onbekend';
        } catch (Throwable) {
            return 'onbekend';
        }
    }
}
