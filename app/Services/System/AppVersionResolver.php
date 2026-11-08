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

    /**
     * Naam van de uitgecheckte git-branch (alleen voor de lokale branch-balk);
     * bij een detached HEAD de korte commit-hash, bij fouten null. Bewust niet
     * gecachet: de branch kan tijdens de levensduur van de container wisselen.
     */
    public function currentBranch(): ?string
    {
        try {
            $branch = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);

            if ($branch === 'HEAD') {
                return $this->git(['rev-parse', '--short', 'HEAD']);
            }

            return $branch;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments): ?string
    {
        $process = new Process(['git', '-c', 'safe.directory=*', ...$arguments], base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }
}
