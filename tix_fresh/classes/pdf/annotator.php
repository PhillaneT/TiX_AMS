<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\pdf;

defined('MOODLE_INTERNAL') || die();

/**
 * Stamps AI marking results onto a learner submission PDF.
 *
 * Strategy
 * --------
 * FPDI imports each page of the original PDF as a template, then TCPDF draws
 * the annotation layer on top before outputting the new file.
 *
 * Annotation layout per question (evenly distributed down the right margin):
 *   COMPETENT        → green  ✓  + comment text
 *   NOT_YET_COMPETENT → red   ✗  + comment text
 *   PARTIAL          → orange ~  + comment text
 *   FLAGGED          → amber  ⚑  + comment text + "ASSESSOR REVIEW REQUIRED"
 *
 * Page 1 gets a header banner:  "AI PRE-MARKED — Awaiting assessor sign-off"
 * Last page gets a footer:      assessor name | signature line | date
 *
 * Dependencies (via composer):
 *   setasign/fpdi          ^2.3
 *   tecnickcom/tcpdf       ^6.6
 *   smalot/pdfparser       ^2.0  (used by extractor, autoloaded here too)
 */
class annotator {

    // -----------------------------------------------------------------------
    // Layout constants (all in mm)
    // -----------------------------------------------------------------------
    private const BAND_WIDTH    = 18;   // right margin annotation band width
    private const TICK_SIZE     = 3;    // mm — size of each tick/cross glyph
    private const TICK_GAP      = 1;    // mm — gap between stacked tick/cross glyphs
    private const BANNER_HEIGHT = 10;   // header banner height
    private const FOOTER_HEIGHT = 14;   // footer block height

    // All assessor marks are red — SAQA/MICT SETA compliance standard.
    private const COLOUR_MARK   = [204, 0,   0];    // assessor red
    private const COLOUR_BANNER = [30,  80,  160];  // AjanaNova blue
    private const COLOUR_FOOTER = [80,  80,  80];   // dark grey

    /**
     * Annotates the submission PDF and writes the result to a temp file.
     *
     * @param  string $filepath   Absolute path to the original submission PDF.
     * @param  array  $questions  Questions array from the AI marking result.
     * @return string  Absolute path to the annotated PDF (in Moodle temp dir).
     * @throws \moodle_exception  If the source PDF cannot be loaded.
     */
    public function annotate(string $filepath, array $questions): string {
        $this->require_libraries();

        if (!is_readable($filepath)) {
            throw new \moodle_exception('ajananova_pdf_unreadable', 'local_ajananova', '', $filepath);
        }

        // ---------------------------------------------------------------
        // Build the annotated PDF
        // ---------------------------------------------------------------
        $pdf = $this->create_pdf_instance();

        try {
            $pagecount = $pdf->setSourceFile($filepath);
        } catch (\Exception $e) {
            throw new \moodle_exception('ajananova_pdf_unreadable', 'local_ajananova', '', $e->getMessage());
        }

        // Distribute questions evenly across pages.
        $qdistrib = $this->distribute_questions($questions, $pagecount);

        for ($page = 1; $page <= $pagecount; $page++) {
            $tplid = $pdf->importPage($page);
            $size  = $pdf->getTemplateSize($tplid);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplid);

            // Header banner on page 1.
            if ($page === 1) {
                $this->draw_header_banner($pdf, $size['width']);
            }

            // Footer on last page.
            if ($page === $pagecount) {
                $this->draw_footer($pdf, $size['width'], $size['height']);
            }

            // Annotations for questions assigned to this page.
            if (!empty($qdistrib[$page])) {
                $this->draw_question_annotations($pdf, $qdistrib[$page], $size);
            }
        }

        // ---------------------------------------------------------------
        // Write to a Moodle temp file and return the path
        // ---------------------------------------------------------------
        $tmpdir  = make_temp_directory('local_ajananova_annotated');
        $outpath = $tmpdir . '/' . uniqid('ajananova_', true) . '.pdf';

        $pdf->Output($outpath, 'F');

        return $outpath;
    }

    // -----------------------------------------------------------------------
    // Drawing helpers
    // -----------------------------------------------------------------------

    /**
     * Draws the "AI PRE-MARKED" banner across the top of the page.
     */
    private function draw_header_banner(\TCPDF $pdf, float $pagewidth): void {
        [$r, $g, $b] = self::COLOUR_BANNER;

        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->SetXY(0, 0);
        $pdf->Cell(
            $pagewidth,
            self::BANNER_HEIGHT,
            'AI PRE-MARKED — Awaiting assessor sign-off',
            0, 1, 'C', true
        );

        // Reset colours.
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * Draws the assessor signature footer on the last page.
     */
    private function draw_footer(\TCPDF $pdf, float $pagewidth, float $pageheight): void {
        [$r, $g, $b] = self::COLOUR_FOOTER;

        $y = $pageheight - self::FOOTER_HEIGHT;

        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont('helvetica', '', 7);

        // Thin separator line.
        $pdf->Line(10, $y, $pagewidth - 10, $y);

        $y += 2;
        $date = date('Y-m-d');

        $pdf->SetXY(10, $y);
        $pdf->Cell(60, 4, 'Assessor: ____________________________', 0, 0, 'L');

        $pdf->SetXY(80, $y);
        $pdf->Cell(60, 4, 'Signature: ____________________________', 0, 0, 'L');

        $pdf->SetXY($pagewidth - 40, $y);
        $pdf->Cell(30, 4, 'Date: ' . $date, 0, 0, 'R');

        // Reset.
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
    }

    /**
     * Draws verdict marks for all questions assigned to one page.
     *
     * For each question:
     *   - One geometric red tick (✓) per mark awarded
     *   - One geometric red cross (✗) per mark NOT awarded
     *   - Marks fraction label (e.g. "3/5") below the glyphs
     *   - Thin separator line between questions
     *
     * All glyphs are drawn geometrically — no font dependency.
     * AI feedback text lives in assignfeedback_comments in Moodle only.
     *
     * @param  array $questions  Subset of questions assigned to this page.
     * @param  array $size       ['width' => float, 'height' => float]
     */
    private function draw_question_annotations(\TCPDF $pdf, array $questions, array $size): void {
        $bandx        = $size['width'] - self::BAND_WIDTH - 2;
        $usableheight = $size['height'] - self::BANNER_HEIGHT - self::FOOTER_HEIGHT - 10;
        $step         = $usableheight / max(1, count($questions));
        $y            = self::BANNER_HEIGHT + 5;

        [$r, $g, $b] = self::COLOUR_MARK;
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetLineWidth(0.4);

        foreach ($questions as $q) {
            $verdict   = $q['verdict'] ?? 'NOT_YET_COMPETENT';
            $awarded   = max(0, (int) ($q['marks_awarded']   ?? 0));
            $available = max(1, (int) ($q['marks_available'] ?? 1));
            $missed    = $available - $awarded;
            $isPass    = in_array($verdict, ['COMPETENT', 'PARTIAL'], true);

            // Draw ticks (marks awarded) then crosses (marks missed).
            $cx = $bandx + 2;
            $cy = $y;

            // Ticks — one per mark awarded.
            for ($i = 0; $i < $awarded; $i++) {
                $this->draw_tick($pdf, $cx, $cy);
                $cx += self::TICK_SIZE + self::TICK_GAP;
                // Wrap to next row if band is full.
                if ($cx > $bandx + self::BAND_WIDTH - self::TICK_SIZE) {
                    $cx  = $bandx + 2;
                    $cy += self::TICK_SIZE + self::TICK_GAP + 1;
                }
            }

            // Crosses — one per mark not awarded (only if NYC or PARTIAL).
            if (!$isPass || $missed > 0) {
                for ($i = 0; $i < $missed; $i++) {
                    $this->draw_cross($pdf, $cx, $cy);
                    $cx += self::TICK_SIZE + self::TICK_GAP;
                    if ($cx > $bandx + self::BAND_WIDTH - self::TICK_SIZE) {
                        $cx  = $bandx + 2;
                        $cy += self::TICK_SIZE + self::TICK_GAP + 1;
                    }
                }
            }

            // Marks fraction label below glyphs.
            $labelY = max($cy, $y) + self::TICK_SIZE + 2;
            $pdf->SetFont('helvetica', 'B', 5);
            $pdf->SetXY($bandx, $labelY);
            $pdf->Cell(self::BAND_WIDTH, 3, $awarded . '/' . $available, 0, 1, 'C');

            // Thin separator line.
            $sepY = $labelY + 4;
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line($bandx + 1, $sepY, $bandx + self::BAND_WIDTH - 1, $sepY);
            $pdf->SetDrawColor($r, $g, $b);

            $y += $step;
        }

        // Reset colours.
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * Draws a geometric tick (✓) at the given top-left position.
     * Two line segments: short down-right, then longer up-right.
     */
    private function draw_tick(\TCPDF $pdf, float $x, float $y): void {
        $s = self::TICK_SIZE;
        $pdf->Line($x,            $y + $s * 0.6,  $x + $s * 0.35, $y + $s);
        $pdf->Line($x + $s * 0.35, $y + $s,       $x + $s,         $y);
    }

    /**
     * Draws a geometric cross (✗) at the given top-left position.
     * Two diagonal lines.
     */
    private function draw_cross(\TCPDF $pdf, float $x, float $y): void {
        $s = self::TICK_SIZE;
        $pdf->Line($x,      $y,      $x + $s, $y + $s);
        $pdf->Line($x + $s, $y,      $x,      $y + $s);
    }

    // -----------------------------------------------------------------------
    // Private utilities
    // -----------------------------------------------------------------------


    /**
     * Distributes questions across pages as evenly as possible.
     *
     * Page 1 is always skipped — it is typically a cover page with no
     * question content. Distribution starts from page 2.
     *
     * Returns an array keyed by 1-based page number, each value an array
     * of question sub-arrays to annotate on that page.
     *
     * NOTE: Without Vision API we cannot know the exact page each answer
     * appears on. This distributes evenly across content pages as a best
     * approximation. Vision API upgrade will replace this with precise
     * per-page placement based on answer box detection.
     */
    private function distribute_questions(array $questions, int $pagecount): array {
        $distrib = [];
        $total   = count($questions);

        if ($total === 0 || $pagecount === 0) {
            return $distrib;
        }

        // Content pages start at page 2. If only 1 page, fall back to page 1.
        $firstpage    = min(2, $pagecount);
        $contentpages = max(1, $pagecount - ($firstpage - 1));

        foreach ($questions as $i => $q) {
            $page = $firstpage + (int) floor(($i / $total) * $contentpages);
            $page = max($firstpage, min($page, $pagecount));
            $distrib[$page][] = $q;
        }

        return $distrib;
    }

    /**
     * Creates and configures the TCPDF+FPDI instance.
     */
    private function create_pdf_instance(): \setasign\Fpdi\Tcpdf\Fpdi {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->SetCreator('AjanaNova Grader');
        $pdf->SetAuthor('AjanaNova Grader');
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        return $pdf;
    }

    /**
     * Requires the Composer autoloader. Throws a clear exception if missing.
     */
    private function require_libraries(): void {
        $autoload = __DIR__ . '/../../vendor/autoload.php';

        if (!file_exists($autoload)) {
            throw new \moodle_exception(
                'ajananova_composer_missing', 'local_ajananova', '',
                'Run "composer install" inside local/ajananova/ to install PDF libraries.'
            );
        }

        require_once $autoload;

        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            throw new \moodle_exception(
                'ajananova_fpdi_missing', 'local_ajananova', '',
                'setasign/fpdi package not found. Run "composer install" in local/ajananova/.'
            );
        }
    }

}
