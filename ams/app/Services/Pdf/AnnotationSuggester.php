<?php

namespace App\Services\Pdf;

class AnnotationSuggester
{
    // Words too common to use for page matching
    private const STOP_WORDS = [
        'that', 'this', 'with', 'from', 'they', 'have', 'will', 'your',
        'what', 'which', 'when', 'there', 'their', 'about', 'would',
        'could', 'should', 'does', 'each', 'also', 'must', 'been',
    ];

    /**
     * Given marked criteria and per-page text, produce a stamp list for the PDF viewer.
     *
     * Each stamp: {page, x_pct, y_pct, type, criterion_index, criterion}
     * x_pct / y_pct are fractions of the page dimensions (top-left origin, 0→1).
     *
     * @param  array $questions    Items from questions_json (criterion, awarded, max_marks)
     * @param  array $pageTexts    [pageNum => string] from TextExtractor
     * @param  int   $totalPages   Total pages in the PDF (used for fallback distribution)
     */
    public function suggest(array $questions, array $pageTexts, int $totalPages = 1): array
    {
        $stamps = [];

        // Track how many stamps already occupy each page so we can stagger y positions
        $pageStampCount = [];

        foreach ($questions as $idx => $q) {
            $type = ($q['awarded'] >= ($q['max_marks'] * 0.5)) ? 'tick' : 'cross';

            $criterion = $q['criterion'] ?? '';
            $page      = $this->matchPage($criterion, $pageTexts, $totalPages, $idx);

            $pageStampCount[$page] = ($pageStampCount[$page] ?? 0) + 1;
            $slotOnPage = $pageStampCount[$page] - 1;

            // Place stamps in the left margin; stagger vertically.
            // First stamp at 8% from top, each subsequent +7%, wrapping after 12 slots.
            $yPct = 0.08 + (($slotOnPage % 12) * 0.07);

            $stamps[] = [
                'page'            => $page,
                'x_pct'           => 0.03,
                'y_pct'           => round($yPct, 4),
                'type'            => $type,
                'criterion_index' => $idx,
                'criterion'       => mb_substr($criterion, 0, 80),
            ];
        }

        return $stamps;
    }

    /**
     * Find the best-matching page for a criterion by keyword frequency.
     * Falls back to distributing criteria evenly across pages when no text is available.
     */
    private function matchPage(string $criterion, array $pageTexts, int $totalPages, int $criterionIdx): int
    {
        if (empty($pageTexts)) {
            // No text available — distribute criteria evenly across pages
            $pagesAvailable = max(1, $totalPages);
            return (int) (($criterionIdx % $pagesAvailable) + 1);
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

    private function extractKeywords(string $text): array
    {
        $words = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn($w) => mb_strlen($w) >= 4 && !in_array($w, self::STOP_WORDS, true)
        ));
    }
}
