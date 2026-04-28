<?php

namespace App\Services\Pdf;

class AnnotationSuggester
{
    private const STOP_WORDS = [
        'that', 'this', 'with', 'from', 'they', 'have', 'will', 'your',
        'what', 'which', 'when', 'there', 'their', 'about', 'would',
        'could', 'should', 'does', 'each', 'also', 'must', 'been',
    ];

    // Max individual stamps per criterion — above this the row gets too long
    private const MAX_STAMPS_PER_CRITERION = 8;

    // Horizontal gap between stamps as a fraction of page width
    private const STAMP_X_STEP = 0.038;

    // Left margin where the first stamp lands
    private const STAMP_X_START = 0.03;

    /**
     * Build the full stamp list for all criteria.
     *
     * For each criterion we produce one stamp per mark, arranged horizontally:
     *   awarded=1, max=3  →  [✓][✗][✗]
     *   awarded=3, max=3  →  [✓][✓][✓]
     *
     * Y position is estimated from where the criterion text appears in the
     * extracted page text. Falls back to evenly-spaced slots when no text match
     * is found (scanned/image PDFs).
     *
     * @param  array $questions   Items from questions_json (criterion, awarded, max_marks)
     * @param  array $pageTexts   [pageNum => string] from TextExtractor
     * @param  int   $totalPages  Total PDF page count (for even distribution fallback)
     */
    public function suggest(array $questions, array $pageTexts, int $totalPages = 1): array
    {
        $stamps          = [];
        $criteriaPerPage = []; // number of criteria placed per page (for slot fallback)

        foreach ($questions as $idx => $q) {
            $awarded   = max(0, (int) ($q['awarded']   ?? 0));
            $maxMarks  = max(1, (int) ($q['max_marks'] ?? 1));
            $criterion = trim($q['criterion'] ?? '');

            $page       = $this->matchPage($criterion, $pageTexts, $totalPages, $idx);
            $slotOnPage = $criteriaPerPage[$page] ?? 0;
            $criteriaPerPage[$page] = $slotOnPage + 1;

            $yPct = $this->estimateY($criterion, $pageTexts[$page] ?? '', $slotOnPage);

            // Cap to avoid an unreadably long row
            $stampsToShow = min($maxMarks, self::MAX_STAMPS_PER_CRITERION);

            for ($m = 0; $m < $stampsToShow; $m++) {
                $type = ($m < $awarded) ? 'tick' : 'cross';
                $xPct = self::STAMP_X_START + ($m * self::STAMP_X_STEP);

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
     * Find the best-matching page for a criterion using keyword frequency.
     * Falls back to distributing criteria evenly across pages.
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
     * Estimate the vertical position (0→1, top-down) of a criterion on its page.
     *
     * Strategy:
     *  1. Split extracted page text into non-empty lines.
     *  2. Score each line against the criterion's keywords.
     *  3. Use the best-matching line's relative position as y_pct.
     *  4. Fall back to evenly-spaced slot positioning when no match is found.
     */
    private function estimateY(string $criterion, string $pageText, int $slotOnPage): float
    {
        $slotY = min(0.92, 0.06 + ($slotOnPage * 0.07));

        if (empty($pageText) || empty($criterion)) {
            return $slotY;
        }

        $lines = array_values(array_filter(
            explode("\n", $pageText),
            fn($l) => trim($l) !== ''
        ));

        $totalLines = count($lines);
        if ($totalLines === 0) {
            return $slotY;
        }

        $keywords = $this->extractKeywords($criterion);
        if (empty($keywords)) {
            return $slotY;
        }

        $bestLine  = -1;
        $bestScore = 0;

        foreach ($lines as $lineIdx => $line) {
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
            // Map line index to y_pct with a small top and bottom margin
            return round(0.04 + (($bestLine / max(1, $totalLines - 1)) * 0.88), 4);
        }

        return $slotY;
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
