<?php

namespace App\Services\Cms;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ontwikkelpad-blok: een gerichte, acyclische graaf van stappen. Parseert de
 * redactie-invoer (twee textareas), bepaalt per stap het niveau (langste pad
 * vanaf een bron) en rekent de SVG-geometrie uit voor desktop (niveaus als
 * kolommen, links naar rechts) en mobiel (alle stappen onder elkaar).
 *
 * @phpstan-type Node array{key: string, label: string, sub: ?string, href: ?string}
 * @phpstan-type Edge array{from: string, to: string}
 * @phpstan-type Content array{nodes: list<Node>, edges: list<Edge>}
 */
class PathwayGraph
{
    private const FONT_LABEL = 16;

    private const FONT_SUB = 13;

    private const LINE_LABEL = 19;

    private const LINE_SUB = 16;

    private const PAD = 24;

    /**
     * @return Content
     */
    public function parse(string $stepsRaw, string $edgesRaw): array
    {
        $nodes = [];
        foreach ($this->lines($stepsRaw) as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            $label = $parts[0];
            if ($label === '') {
                continue;
            }

            $key = Str::slug($label);
            if ($key === '') {
                throw new InvalidArgumentException("Het label ‘{$label}’ bevat geen letters of cijfers.");
            }
            if (isset($nodes[$key])) {
                throw new InvalidArgumentException("De stap ‘{$label}’ komt twee keer voor.");
            }

            $href = ($parts[2] ?? '') !== '' ? $parts[2] : null;
            if ($href !== null && ! self::isSafeHref($href)) {
                throw new InvalidArgumentException("De link ‘{$href}’ bij stap ‘{$label}’ moet met / of https:// beginnen.");
            }

            $nodes[$key] = [
                'key' => $key,
                'label' => $label,
                'sub' => ($parts[1] ?? '') !== '' ? $parts[1] : null,
                'href' => $href,
            ];
        }

        $edges = [];
        foreach ($this->lines($edgesRaw) as $line) {
            $parts = preg_split('/\s*(?:->|→)\s*/u', $line) ?: [];
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new InvalidArgumentException("De pijl ‘{$line}’ moet de vorm ‘Label A -> Label B’ hebben.");
            }

            $from = Str::slug(trim($parts[0]));
            $to = Str::slug(trim($parts[1]));
            foreach ([trim($parts[0]) => $from, trim($parts[1]) => $to] as $label => $key) {
                if (! isset($nodes[$key])) {
                    throw new InvalidArgumentException("Onbekend label ‘{$label}’ in pijl ‘{$line}’. Gebruik exact een label uit de stappen.");
                }
            }

            $edges["{$from}>{$to}"] = ['from' => $from, 'to' => $to];
        }

        $content = ['nodes' => array_values($nodes), 'edges' => array_values($edges)];
        $this->levels($content);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{steps: string, edges: string}
     */
    public function toRaw(array $content): array
    {
        $nodes = $this->normalize($content)['nodes'];
        $labels = [];
        foreach ($nodes as $node) {
            $labels[$node['key']] = $node['label'];
        }

        $steps = array_map(
            fn (array $n): string => rtrim($n['label'].' | '.($n['sub'] ?? '').' | '.($n['href'] ?? ''), ' |'),
            $nodes,
        );
        $edges = [];
        foreach ($this->normalize($content)['edges'] as $e) {
            if (isset($labels[$e['from']], $labels[$e['to']])) {
                $edges[] = $labels[$e['from']].' -> '.$labels[$e['to']];
            }
        }

        return ['steps' => implode("\n", $steps), 'edges' => implode("\n", $edges)];
    }

    /**
     * Niveau per stap = lengte van het langste pad vanaf een bron.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, int>
     *
     * @throws InvalidArgumentException bij een cyclus
     */
    public function levels(array $content): array
    {
        $c = $this->normalize($content);
        $level = [];
        $indegree = [];
        $out = [];
        foreach ($c['nodes'] as $n) {
            $level[$n['key']] = 0;
            $indegree[$n['key']] = 0;
            $out[$n['key']] = [];
        }
        foreach ($c['edges'] as $e) {
            if (! isset($level[$e['from']], $level[$e['to']])) {
                continue;
            }
            $out[$e['from']][] = $e['to'];
            $indegree[$e['to']]++;
        }

        $queue = array_keys(array_filter($indegree, fn (int $d): bool => $d === 0));
        $done = 0;
        while ($queue !== []) {
            $key = array_shift($queue);
            $done++;
            foreach ($out[$key] as $next) {
                $level[$next] = max($level[$next], $level[$key] + 1);
                if (--$indegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if ($done !== count($level)) {
            throw new InvalidArgumentException('De pijlen vormen een cirkel; een ontwikkelpad mag niet terugkeren naar een eerdere stap.');
        }

        return $level;
    }

    /**
     * Geometrie voor één SVG. Horizontaal: niveaus als kolommen. Verticaal
     * (mobiel): alle stappen onder elkaar, volgorde op niveau.
     *
     * @param  array<string, mixed>  $content
     * @return array{width: int, height: int, nodeWidth: int, nodeHeight: int, nodes: list<array{key: string, label: string, sub: ?string, href: ?string, x: int, y: int, level: int, lines: list<string>, sublines: list<string>}>, edges: list<array{from: string, to: string, d: string}>}
     */
    public function layout(array $content, bool $horizontal): array
    {
        $c = $this->normalize($content);
        if ($c['nodes'] === []) {
            return ['width' => 0, 'height' => 0, 'nodeWidth' => 0, 'nodeHeight' => 0, 'nodes' => [], 'edges' => []];
        }

        $levels = $this->levels($c);
        $width = $horizontal ? 210 : 240;
        $textWidth = $width - 24;

        $prepared = [];
        $maxLabelLines = 1;
        $maxSubLines = 0;
        foreach ($c['nodes'] as $n) {
            $lines = $this->wrap($n['label'], (int) floor($textWidth / (self::FONT_LABEL * 0.6)));
            $sublines = $n['sub'] !== null ? $this->wrap($n['sub'], (int) floor($textWidth / (self::FONT_SUB * 0.54))) : [];
            $maxLabelLines = max($maxLabelLines, count($lines));
            $maxSubLines = max($maxSubLines, count($sublines));
            $prepared[$n['key']] = $n + ['level' => $levels[$n['key']], 'lines' => $lines, 'sublines' => $sublines];
        }
        $height = max(56, 20 + $maxLabelLines * self::LINE_LABEL + ($maxSubLines > 0 ? 4 + $maxSubLines * self::LINE_SUB : 0));

        $columns = $this->orderedColumns($c, $levels);

        return $horizontal
            ? $this->layoutHorizontal($c, $prepared, $columns, $width, $height)
            : $this->layoutVertical($c, $prepared, $columns, $width, $height);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return Content
     */
    public function normalize(array $content): array
    {
        $nodes = [];
        foreach ((array) ($content['nodes'] ?? []) as $n) {
            if (! is_array($n) || trim((string) ($n['label'] ?? '')) === '') {
                continue;
            }
            $label = trim((string) $n['label']);
            $href = trim((string) ($n['href'] ?? ''));
            $sub = trim((string) ($n['sub'] ?? ''));
            $nodes[] = [
                'key' => (string) ($n['key'] ?? Str::slug($label)),
                'label' => $label,
                'sub' => $sub !== '' ? $sub : null,
                'href' => $href !== '' && self::isSafeHref($href) ? $href : null,
            ];
        }

        $edges = [];
        foreach ((array) ($content['edges'] ?? []) as $e) {
            if (is_array($e) && isset($e['from'], $e['to'])) {
                $edges[] = ['from' => (string) $e['from'], 'to' => (string) $e['to']];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    public static function isSafeHref(string $href): bool
    {
        return (bool) preg_match('#^(/(?!/)|https?://)#i', $href);
    }

    /**
     * Stappen per niveau, binnen een niveau gesorteerd op het gemiddelde van de
     * posities van hun voorgangers (beperkt kruisende pijlen).
     *
     * @param  Content  $c
     * @param  array<string, int>  $levels
     * @return list<list<string>>
     */
    private function orderedColumns(array $c, array $levels): array
    {
        $columns = [];
        foreach ($c['nodes'] as $n) {
            $columns[$levels[$n['key']]][] = $n['key'];
        }
        ksort($columns);

        $preds = [];
        foreach ($c['edges'] as $e) {
            $preds[$e['to']][] = $e['from'];
        }

        $position = [];
        $result = [];
        foreach ($columns as $keys) {
            $original = array_flip($keys);
            usort($keys, function (string $a, string $b) use ($preds, $position, $original): int {
                $score = function (string $k) use ($preds, $position, $original): float {
                    $p = array_filter(array_map(fn (string $x): ?float => $position[$x] ?? null, $preds[$k] ?? []), fn (?float $v): bool => $v !== null);

                    return $p === [] ? (float) $original[$k] : array_sum($p) / count($p);
                };

                return [$score($a), $original[$a]] <=> [$score($b), $original[$b]];
            });
            $count = count($keys);
            foreach ($keys as $i => $k) {
                $position[$k] = $i - ($count - 1) / 2;
            }
            $result[] = $keys;
        }

        return $result;
    }

    /**
     * @param  Content  $c
     * @param  array<string, array<string, mixed>>  $prepared
     * @param  list<list<string>>  $columns
     * @return array{width: int, height: int, nodeWidth: int, nodeHeight: int, nodes: list<array{key: string, label: string, sub: ?string, href: ?string, x: int, y: int, level: int, lines: list<string>, sublines: list<string>}>, edges: list<array{from: string, to: string, d: string}>}
     */
    private function layoutHorizontal(array $c, array $prepared, array $columns, int $w, int $h): array
    {
        $colGap = 64;
        $rowGap = 22;
        $tallest = max(array_map('count', $columns));
        $innerHeight = $tallest * $h + ($tallest - 1) * $rowGap;

        $placed = [];
        $colBounds = [];
        foreach ($columns as $ci => $keys) {
            $colHeight = count($keys) * $h + (count($keys) - 1) * $rowGap;
            $offset = (int) round(($innerHeight - $colHeight) / 2);
            $x = self::PAD + $ci * ($w + $colGap);
            foreach ($keys as $i => $key) {
                $placed[$key] = ['x' => $x, 'y' => self::PAD + $offset + $i * ($h + $rowGap)] + $prepared[$key];
            }
            $colBounds[$ci] = [self::PAD + $offset, self::PAD + $offset + $colHeight, $x];
        }

        $edges = [];
        foreach ($c['edges'] as $e) {
            $a = $placed[$e['from']];
            $b = $placed[$e['to']];
            $x0 = $a['x'] + $w;
            $y0 = $a['y'] + $h / 2;
            $x1 = $b['x'] - 1;
            $y1 = $b['y'] + $h / 2;

            $points = [[$x0, $y0]];
            for ($k = $a['level'] + 1; $k < $b['level']; $k++) {
                [$top, $bottom, $cx] = $colBounds[$k];
                $yAt = $y0 + ($y1 - $y0) * (($cx + $w / 2) - $x0) / ($x1 - $x0);
                if ($this->curveCrosses($x0, $y0, $x1, $y1, $cx, $cx + $w, $top, $bottom)) {
                    $wy = abs($yAt - ($top - 14)) <= abs($yAt - ($bottom + 14)) ? $top - 14 : $bottom + 14;
                    $points[] = [$cx - 6, $wy];
                    $points[] = [$cx + $w + 6, $wy];
                }
            }
            $points[] = [$x1, $y1];

            $d = 'M'.$this->n($points[0][0]).' '.$this->n($points[0][1]);
            for ($p = 1; $p < count($points); $p++) {
                [$px, $py] = $points[$p - 1];
                [$qx, $qy] = $points[$p];
                $isDetour = $p > 1 && $points[$p - 1][1] === $points[$p][1] && $p < count($points) - 1;
                if ($isDetour) {
                    $d .= ' L'.$this->n($qx).' '.$this->n($qy);
                } else {
                    $half = ($qx - $px) / 2;
                    $d .= ' C'.$this->n($px + $half).' '.$this->n($py).' '.$this->n($qx - $half).' '.$this->n($qy).' '.$this->n($qx).' '.$this->n($qy);
                }
            }
            $edges[] = ['from' => $e['from'], 'to' => $e['to'], 'd' => $d];
        }

        return [
            'width' => 2 * self::PAD + count($columns) * $w + (count($columns) - 1) * $colGap,
            'height' => 2 * self::PAD + $innerHeight,
            'nodeWidth' => $w,
            'nodeHeight' => $h,
            'nodes' => array_values($placed),
            'edges' => $edges,
        ];
    }

    /**
     * @param  Content  $c
     * @param  array<string, array<string, mixed>>  $prepared
     * @param  list<list<string>>  $columns
     * @return array{width: int, height: int, nodeWidth: int, nodeHeight: int, nodes: list<array{key: string, label: string, sub: ?string, href: ?string, x: int, y: int, level: int, lines: list<string>, sublines: list<string>}>, edges: list<array{from: string, to: string, d: string}>}
     */
    private function layoutVertical(array $c, array $prepared, array $columns, int $w, int $h): array
    {
        $gap = 44;
        $pad = 16;
        $maxBulge = 64;

        $order = array_merge(...$columns);
        $placed = [];
        $index = [];
        foreach ($order as $i => $key) {
            $index[$key] = $i;
            $placed[$key] = ['x' => $pad, 'y' => $pad + $i * ($h + $gap)] + $prepared[$key];
        }

        $edges = [];
        foreach ($c['edges'] as $e) {
            $a = $placed[$e['from']];
            $b = $placed[$e['to']];
            $span = $index[$e['to']] - $index[$e['from']];
            if ($span === 1) {
                $cx = $a['x'] + $w / 2;
                $d = 'M'.$this->n($cx).' '.$this->n($a['y'] + $h).' L'.$this->n($cx).' '.$this->n($b['y'] - 1);
            } else {
                $bulge = min($maxBulge, 16 + 9 * $span);
                $xr = $a['x'] + $w;
                $y0 = $a['y'] + $h / 2;
                $y1 = $b['y'] + $h / 2;
                $d = 'M'.$this->n($xr).' '.$this->n($y0).' C'.$this->n($xr + $bulge).' '.$this->n($y0).' '.$this->n($xr + $bulge).' '.$this->n($y1).' '.$this->n($xr + 1).' '.$this->n($y1);
            }
            $edges[] = ['from' => $e['from'], 'to' => $e['to'], 'd' => $d];
        }

        return [
            'width' => $pad + $w + $maxBulge + $pad,
            'height' => 2 * $pad + count($order) * $h + (count($order) - 1) * $gap,
            'nodeWidth' => $w,
            'nodeHeight' => $h,
            'nodes' => array_values($placed),
            'edges' => $edges,
        ];
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $max): array
    {
        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($current !== '' && mb_strlen($current.' '.$word) > $max) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $current === '' ? $word : $current.' '.$word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function lines(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $raw) ?: []),
            fn (string $l): bool => $l !== '',
        ));
    }

    /**
     * Snijdt de S-curve van (x0,y0) naar (x1,y1) de kolom [left,right] x [top,bottom] (met marge)?
     */
    private function curveCrosses(float $x0, float $y0, float $x1, float $y1, float $left, float $right, float $top, float $bottom): bool
    {
        for ($i = 0; $i <= 40; $i++) {
            $t = $i / 40;
            // Control points op halve x-afstand: x(t) = x0 + (x1-x0)(3t^2-2t^3), y(t) = y0 + (y1-y0)(3t^2-2t^3)
            $s = 3 * $t * $t - 2 * $t * $t * $t;
            $x = $x0 + ($x1 - $x0) * $s;
            $y = $y0 + ($y1 - $y0) * $s;
            if ($x >= $left - 6 && $x <= $right + 6 && $y > $top - 8 && $y < $bottom + 8) {
                return true;
            }
        }

        return false;
    }

    private function n(float|int $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
