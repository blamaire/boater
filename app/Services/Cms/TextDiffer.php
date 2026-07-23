<?php

namespace App\Services\Cms;

/**
 * Regel-voor-regel tekstdiff (LCS-gebaseerd) voor de "JSON — beide versies
 * rauw"-tab van de historie-diff: legt overeenkomstige regels op één rij en
 * markeert per rij wat gelijk/toegevoegd/verwijderd/gewijzigd is, in plaats
 * van twee ongerelateerde tekstblokken naast elkaar te tonen.
 */
class TextDiffer
{
    /**
     * @return list<array{type: string, left: ?string, right: ?string}>
     */
    public function diffLines(string $a, string $b): array
    {
        $linesA = explode("\n", $a);
        $linesB = explode("\n", $b);

        return $this->pairOps($this->lcsOps($linesA, $linesB));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{op: string, line: string}>
     */
    private function lcsOps(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        // LCS-lengtetabel (dynamic programming): $table[$i][$j] = lengte van
        // de langste gemeenschappelijke subsequentie van a[$i..] en b[$j..].
        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['op' => 'same', 'line' => $a[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['op' => 'removed', 'line' => $a[$i]];
                $i++;
            } else {
                $ops[] = ['op' => 'added', 'line' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $ops[] = ['op' => 'removed', 'line' => $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $ops[] = ['op' => 'added', 'line' => $b[$j]];
            $j++;
        }

        return $ops;
    }

    /**
     * Groepeert opeenvolgende removed/added-reeksen tot gepaarde
     * "gewijzigd"-rijen (regel-voor-regel gekoppeld), zodat de weergave op
     * één rij toont wat links verdween en wat er rechts voor in de plaats
     * kwam — i.p.v. eerst een blok verwijderde en dan een los blok
     * toegevoegde regels.
     *
     * @param  list<array{op: string, line: string}>  $ops
     * @return list<array{type: string, left: ?string, right: ?string}>
     */
    private function pairOps(array $ops): array
    {
        $rows = [];
        $i = 0;
        $count = count($ops);

        while ($i < $count) {
            if ($ops[$i]['op'] === 'same') {
                $rows[] = ['type' => 'same', 'left' => $ops[$i]['line'], 'right' => $ops[$i]['line']];
                $i++;

                continue;
            }

            $removed = [];
            while ($i < $count && $ops[$i]['op'] === 'removed') {
                $removed[] = $ops[$i]['line'];
                $i++;
            }
            $added = [];
            while ($i < $count && $ops[$i]['op'] === 'added') {
                $added[] = $ops[$i]['line'];
                $i++;
            }

            $pairs = max(count($removed), count($added));
            for ($k = 0; $k < $pairs; $k++) {
                $left = $removed[$k] ?? null;
                $right = $added[$k] ?? null;
                $type = $left !== null && $right !== null ? 'changed' : ($left !== null ? 'removed' : 'added');
                $rows[] = ['type' => $type, 'left' => $left, 'right' => $right];
            }
        }

        return $rows;
    }
}
