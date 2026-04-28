<?php

namespace App\Services\Pdf;

class AnnotationSuggester
{
    private const STOP_WORDS = [
        'that', 'this', 'with', 'from', 'they', 'have', 'will', 'your',
        'what', 'which', 'when', 'there', 'their', 'about', 'would',
        'could', 'should', 'does', 'each', 'also', 'must', 'been',
    ];

    // Max stamps shown per criterion row
    private const MAX_STAMPS = 8;

    // Horizontal gap between stamps (fraction of page width)
    private const X_STEP = 0.038;

    // Left margin for first stamp
    private const X_START = 0.03;

    // Minimum vertical gap between two criterion rows on the same page
    private const MIN_Y_GAP = 0.055;

    /**
     * Build the full stamp list for all criteria.
     *
     * Each criterion produces a horizontal row of stamps — one per mark:
     *   awarded=1 / max=3  →  [✓][✗][✗]
     *   awarded=3 / max=3  →  [✓][✓][✓]
     *   awarded=4 / max=11 →  scaled to 8: [✓][✓][✓][✗][✗][✗][✗][✗]
     *
     * Rows are pushed down so they never overlap each other on the same page.
     */
    public function suggest(array $questions, array $pageTexts, int $totalPages = 1): array
    {
        $stamps      = [];
        $usedY       = []; // [page => [y, y, …]] — occupied y positions per page
        $slotPerPage = []; // fallback slot counter per page

        foreach ($questions as $idx => $q) {
            $awarded   = max(0, (int) ($q['awarded']   ?? 0));
            $maxMarks  = max(1, (int) ($q['max_marks'] ?? 1));
            $criterion = trim($q['criterion'] ?? '');

            $page = $this->matchPage($criterion, $pageTexts, $totalPages, $idx);

            $slot         = $slotPerPage[$page] ?? 0;
            $slotPerPage[$page] = $slot + 1;

            // Raw y estimate from text (or slot fallback)
            $rawY = $this->estimateY($criterion, $pageTexts[$page] ?? '', $slot);

            // Push down if it would overlap a previously placed row on this page
            $yPct = $this->resolveOverlap($rawY, $page, $usedY);
            $usedY[$page][] = $yPct;

            // Scale marks to MAX_STAMPS if max_marks is large
            $stampsToShow = min($maxMarks, self::MAX_STAMPS);
            $ticksToShow  = ($maxMarks <= self::MAX_STAMPS)
                ? $awarded
                : (int) round($awarded / $maxMarks * self::MAX_STAMPS);

            for ($m = 0; $m < $stampsToShow; $m++) {
                $type = ($m < $ticksToShow) ? 'tick' : 'cross';
                $xPct = self::X_START + ($m * self::X_STEP);

                $stamps[] = [
                    'page'            => $page,
                    'x_pct'           => round($xPct, 4),
                    'y_pct'           => round($yPct, 4),
                    'type'            => $type,
                    'criterion_index' => $idx,
                    'criterion'       => mb_substr($criterion, 0, 80),
                ];
            }
        }

        return $stamps;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Find the best-matching page by keyword frequency.
     */
    private function matchPage(string $criterion, array $pageTexts, int $totalPages, int $idx): int
    {
        if (empty($pageTexts)) {
            return (int) (($idx % max(1, $totalPages)) + 1);
        }

        $keywords = $this->extractKeywords($criterion);
        if (empty($keywords)) {
            return array_key_first($pageTexts);
        }

        $bestPage  = array_key_first($pageTexts);
        $bestScore = 0;

        foreach ($pageTexts as $pageNum => $text) {
            $lower = mb_strtolower($text);
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPage  = $pageNum;
            }
        }

        return $bestPage;
    }

    /**
     * Estimate vertical position (0→1, top-down) from line-based text matching.
     *
     * Skips the first 15% of lines to avoid locking onto page headings/images
     * that appear at the very top but don't correspond to answer content.
     * Falls back to even slot spacing when no line match is found.
     */
    private function estimateY(string $criterion, string $pageText, int $slot): float
    {
        $slotY = min(0.92, 0.08 + ($slot * self::MIN_Y_GAP));

        if (empty($pageText) || empty($criterion)) {
            return $slotY;
        }

        $lines = array_values(array_filter(
            explode("\n", $pageText),
            fn($l) => trim($l) !== ''
        ));

        $totalLines = count($lines);
        if ($totalLines < 3) {
            return $slotY;
        }

        $keywords = $this->extractKeywords($criterion);
        if (empty($keywords)) {
            return $slotY;
        }

        // Skip the top 15% of lines — headings and banner images often sit here
        // and attract false matches that push all stamps to the top of the page.
        $skipLines = (int) ceil($totalLines * 0.15);

        $bestLine  = -1;
        $bestScore = 0;

        foreach ($lines as $lineIdx => $line) {
            if ($lineIdx < $skipLines) continue;

            $lower = mb_strtolower($line);
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLine  = $lineIdx;
            }
        }

        if ($bestLine >= 0) {
            return round(0.04 + (($bestLine / max(1, $totalLines - 1)) * 0.88), 4);
        }

        return $slotY;
    }

    /**
     * Push $yPct down until it is at least MIN_Y_GAP away from every
     * previously placed row on the same page.
     */
    private function resolveOverlap(float $yPct, int $page, array $usedY): float
    {
        $occupied = $usedY[$page] ?? [];

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $conflict = false;
            foreach ($occupied as $taken) {
                if (abs($yPct - $taken) < self::MIN_Y_GAP) {
                    $yPct    = $taken + self::MIN_Y_GAP;
                    $conflict = true;
                    break;
                }
            }
            if (!$conflict) break;
        }

        return min(round($yPct, 4), 0.96);
    }

    private function extractKeywords(string $text): array
    {
        $words = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn($w) => mb_strlen($w) >= 4 && !in_array($w, self::STOP_WORDS, true)
        ));
    }
}
