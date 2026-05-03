<?php

namespace App\Services\Pdf;

class AssessorDeclarationGenerator
{
    private const NAVY   = [30,  58,  95];
    private const WHITE  = [255, 255, 255];
    private const BLACK  = [0,   0,   0];
    private const GRAY   = [100, 100, 100];
    private const LIGHT  = [245, 247, 250];
    private const BORDER = [200, 210, 220];
    private const GREEN  = [16,  122,  56];
    private const RED    = [185,  28,  28];

    /**
     * Generate a standalone Assessor Declaration PDF and return its absolute path.
     *
     * @param array $data {
     *   qualification_name, assignment_name, saqa_id, seta,
     *   learner_name, student_no,
     *   assessor_name, etqa_registration, assessment_provider,
     *   verdict ('COMPETENT'|'NOT_YET_COMPETENT'), date (Carbon|string)
     * }
     */
    public function generate(array $data): string
    {
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            throw new \RuntimeException(
                'setasign/fpdi and tecnickcom/tcpdf must be installed.'
            );
        }

        $prevHandler = set_error_handler(null);
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(20, 20, 20);
            $pdf->AddPage('P', 'A4');
            $this->drawPage($pdf, $data);
            $tmpPath = sys_get_temp_dir() . '/assessor_decl_' . uniqid() . '.pdf';
            $pdf->Output($tmpPath, 'F');
        } finally {
            set_error_handler($prevHandler);
        }
        return $tmpPath;
    }

    // ─── GhostScript helpers ──────────────────────────────────────────────────

    /**
     * Pre-validate a PDF for FPDI importability using a throwaway FPDI instance.
     *
     * Returns [importPath, pageCount] on success, or [null, 0] on failure.
     * If the original PDF fails but a GhostScript downgrade works, returns the
     * temp downgraded path (caller must unlink it when done).
     */
    private function preValidatePdf(string $path): array
    {
        foreach ([$path, '__gs__'] as $attempt) {
            $testSrc = $path;
            $isGs    = false;

            if ($attempt === '__gs__') {
                $gs = $this->tryGhostscriptDowngrade($path);
                if (!$gs) break;
                $testSrc = $gs;
                $isGs    = true;
            }

            try {
                $test = new \setasign\Fpdi\Tcpdf\Fpdi();
                $test->setPrintHeader(false);
                $test->setPrintFooter(false);
                $test->SetAutoPageBreak(false);
                $count = $test->setSourceFile($testSrc);
                // Try all pages — if any fails the import would corrupt our real doc
                for ($p = 1; $p <= $count; $p++) {
                    $test->AddPage();
                    $test->importPage($p);
                }
                unset($test);
                return [$testSrc, $count];
            } catch (\Throwable $e) {
                if ($isGs) @unlink($testSrc);
                // Try GhostScript on next iteration
            }
        }
        return [null, 0];
    }

    /**
     * Locate a usable GhostScript binary, or return null if none found.
     */
    private function findGhostscript(): ?string
    {
        $candidates = ['gs', 'gswin64c', 'gswin32c', 'gsc'];
        foreach ($candidates as $bin) {
            exec(escapeshellcmd($bin) . ' --version 2>&1', $out, $code);
            if ($code === 0) return $bin;
        }
        // Common Windows install paths
        foreach (glob('C:/Program Files/gs/gs*/bin/gswin64c.exe') ?: [] as $path) {
            if (file_exists($path)) return '"' . $path . '"';
        }
        foreach (glob('C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe') ?: [] as $path) {
            if (file_exists($path)) return '"' . $path . '"';
        }
        return null;
    }

    /**
     * Use GhostScript to rewrite the PDF at PDF 1.4 compatibility so that FPDI
     * (which only supports uncompressed cross-reference tables) can import it.
     * Returns the path of the converted temp file, or null on failure.
     */
    private function tryGhostscriptDowngrade(string $inputPath): ?string
    {
        $gs = $this->findGhostscript();
        if (!$gs) return null;

        $tmp = sys_get_temp_dir() . '/gs_compat_' . uniqid() . '.pdf';
        $cmd = $gs
            . ' -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite'
            . ' -dCompatibilityLevel=1.4'
            . ' -sOutputFile=' . escapeshellarg($tmp)
            . ' ' . escapeshellarg($inputPath)
            . ' 2>&1';
        exec($cmd, $output, $exitCode);

        if ($exitCode === 0 && file_exists($tmp) && filesize($tmp) > 0) {
            return $tmp;
        }
        @unlink($tmp);
        return null;
    }

    /**
     * Try to call setSourceFile() on $pdf for the given path.
     * If FPDI chokes (PDF 1.5+), attempt a GhostScript downgrade and retry.
     * Returns [resolvedPath|null, pageCount].  resolvedPath is null when both attempts fail.
     */
    private function openPdfSource(\setasign\Fpdi\Tcpdf\Fpdi $pdf, string $path): array
    {
        try {
            $count = $pdf->setSourceFile($path);
            return [$path, $count];
        } catch (\Throwable $e) {
            // Try GhostScript downgrade (PDF 1.5+ compatibility issue)
            $gsPath = $this->tryGhostscriptDowngrade($path);
            if ($gsPath) {
                try {
                    $count = $pdf->setSourceFile($gsPath);
                    return [$gsPath, $count];   // caller must unlink $gsPath when done
                } catch (\Throwable $e2) {
                    @unlink($gsPath);
                }
            }
            return [null, 0];   // Could not open — caller should add a notice page
        }
    }

    /**
     * Add a "submission could not be merged" notice page.
     * Gives the assessor something useful even when FPDI can't read the student's PDF.
     */
    private function addMergeNoticePage(\setasign\Fpdi\Tcpdf\Fpdi $pdf): void
    {
        $pdf->AddPage('P', 'A4');
        $pdf->SetFillColorArray(self::NAVY);
        $pdf->SetTextColorArray(self::WHITE);
        $pdf->SetFont('dejavusans', 'B', 13);
        $pdf->SetXY(20, 20);
        $pdf->Cell(170, 12, 'STUDENT SUBMISSION', 0, 1, 'C', true);

        $pdf->SetTextColorArray(self::BLACK);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Ln(10);
        $pdf->MultiCell(170, 6,
            "The student's original submission PDF could not be automatically merged into this document.\n\n" .
            "This is a PDF 1.5+ compatibility limitation of the merge library (FPDI).\n\n" .
            "To include the submission:\n" .
            "  1. Install GhostScript (https://ghostscript.com) and ensure it is on the system PATH, then re-sign the submission.\n" .
            "     — OR —\n" .
            "  2. Attach the original submission PDF alongside this document manually.\n\n" .
            "All assessor marks and feedback are recorded on the Marking Report page above.",
            0, 'L'
        );
    }

    // ─── Marking report ───────────────────────────────────────────────────────

    /**
     * Generate a Marking Report PDF (criteria table with marks + assessor comments).
     *
     * @param array $data {
     *   assignment_name, learner_name, student_no, assessor_name, date,
     *   questions: array of ['criterion','max_marks','awarded','comment'],
     *   verdict ('COMPETENT'|'NOT_YET_COMPETENT')
     * }
     */
    public function generateMarkingReport(array $data): string
    {
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            throw new \RuntimeException('setasign/fpdi and tecnickcom/tcpdf must be installed.');
        }

        $prevHandler = set_error_handler(null);
        try {

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage('P', 'A4');

        // ── Title banner ────────────────────────────────────────────────────
        $pdf->SetFillColorArray(self::NAVY);
        $pdf->SetTextColorArray(self::WHITE);
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(0, 12, 'MARKING REPORT', 0, 1, 'C', true);
        $pdf->Ln(3);

        // ── Info block ──────────────────────────────────────────────────────
        $dateStr = is_string($data['date']) ? $data['date'] : ($data['date'] ? $data['date']->format('d F Y') : date('d F Y'));
        $infoHtml = sprintf(
            '<table border="0.3" cellpadding="4" style="background-color:#f5f7fa; font-size:9pt;">
                <tr><td width="38%%"><b>Assignment:</b></td><td>%s</td></tr>
                <tr><td><b>Learner:</b></td><td>%s &nbsp;|&nbsp; Student No: %s</td></tr>
                <tr><td><b>Assessor:</b></td><td>%s</td></tr>
                <tr><td><b>Date Assessed:</b></td><td>%s</td></tr>
            </table>',
            htmlspecialchars($data['assignment_name'] ?? ''),
            htmlspecialchars($data['learner_name']    ?? ''),
            htmlspecialchars($data['student_no']      ?? ''),
            htmlspecialchars($data['assessor_name']   ?? ''),
            $dateStr
        );
        $pdf->SetTextColorArray(self::BLACK);
        $pdf->writeHTML($infoHtml, true, false, true, false, '');
        $pdf->Ln(4);

        // ── Criteria table ──────────────────────────────────────────────────
        $questions = $data['questions'] ?? [];
        $tableHtml = '<table border="0.3" cellpadding="4" cellspacing="0" style="font-size:8.5pt;">
            <tr style="background-color:#1e3a5f; color:#ffffff; font-weight:bold; font-size:8pt;">
                <td width="4%"  align="center">#</td>
                <td width="37%">Criterion / Question</td>
                <td width="7%"  align="center">Max</td>
                <td width="7%"  align="center">Mark</td>
                <td width="45%">Assessor Feedback / Comment</td>
            </tr>';

        foreach ($questions as $i => $q) {
            $max     = (int) ($q['max_marks'] ?? 1);
            $awarded = (int) ($q['awarded']   ?? 0);
            $pct     = $max > 0 ? $awarded / $max : 0;
            $rowBg   = $pct >= 0.5 ? '#f0fdf4' : '#fef2f2';
            $markCol = $pct >= 0.5 ? '#166534' : '#991b1b';

            // Strip [LABEL] prefix so only question text shows
            $criterion = $q['criterion'] ?? $q['question'] ?? '—';
            if (preg_match('/^\[([^\]]+)\]\s*(.+)/su', $criterion, $cm)) {
                $criterion = '[' . $cm[1] . '] ' . trim($cm[2]);
            }

            $tableHtml .= sprintf(
                '<tr style="background-color:%s;">
                    <td align="center" style="font-weight:bold; color:%s;">%d</td>
                    <td style="font-size:8pt;">%s</td>
                    <td align="center">%d</td>
                    <td align="center" style="font-weight:bold; color:%s;">%d</td>
                    <td style="font-size:8pt; color:#374151;">%s</td>
                </tr>',
                $rowBg, $markCol, $i + 1,
                htmlspecialchars($criterion),
                $max, $markCol, $awarded,
                htmlspecialchars($q['comment'] ?? '—')
            );
        }
        $tableHtml .= '</table>';
        $pdf->writeHTML($tableHtml, true, false, true, false, '');
        $pdf->Ln(4);

        // ── Total / Verdict ─────────────────────────────────────────────────
        $totalMax     = array_sum(array_column($questions, 'max_marks'));
        $totalAwarded = array_sum(array_column($questions, 'awarded'));
        $pctTotal     = $totalMax > 0 ? round($totalAwarded / $totalMax * 100) : 0;
        $isC          = ($data['verdict'] ?? '') === 'COMPETENT';

        $summaryHtml = sprintf(
            '<table border="0.5" cellpadding="6" style="font-size:11pt;">
                <tr style="background-color:%s;">
                    <td width="55%%"><b>Total Score:</b> %d / %d &nbsp;(%d%%)</td>
                    <td width="45%%" align="center" style="font-weight:bold; color:%s; font-size:13pt;">%s</td>
                </tr>
            </table>',
            $isC ? '#f0fdf4' : '#fef2f2',
            $totalAwarded, $totalMax, $pctTotal,
            $isC ? '#166534' : '#991b1b',
            $isC ? '✓ COMPETENT' : '✗ NOT YET COMPETENT'
        );
        $pdf->writeHTML($summaryHtml, true, false, true, false, '');

        // ── Footer note ─────────────────────────────────────────────────────
        $pdf->Ln(6);
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->Cell(0, 5, 'CONFIDENTIAL — For assessment and moderation purposes only.', 0, 1, 'C');

        $tmpPath = sys_get_temp_dir() . '/report_' . uniqid() . '.pdf';
        $pdf->Output($tmpPath, 'F');

        } finally {
            set_error_handler($prevHandler);
        }
        return $tmpPath;
    }

    /**
     * Build the final return-to-learner PDF in a single FPDI session.
     *
     * Draws stamps directly onto each submission page in the same session —
     * avoiding the double-import problem where stamps placed via useTemplate()
     * live in a separate content-stream layer that FPDI loses on re-import.
     *
     * Structure of the output:
     *   [front pages: declaration, marking report, …]
     *   [submission page 1 + stamps]
     *   [submission page 2 + stamps]  …
     *
     * @param string[] $prependPaths   Ordered PDFs to prepend (declaration, report …)
     * @param string   $submissionPath Original (unencrypted) submission PDF
     * @param array    $stamps         annotations_json rows: {page, x_pct, y_pct, type}
     * @param string   $outputPath     Where to write the locked output
     */
    /**
     * Build the complete return-to-learner PDF in a single session.
     *
     * Front pages (declaration + marking report) are drawn NATIVELY in TCPDF —
     * no FPDI import needed for them, so PDF-version incompatibility cannot occur.
     * Only the student's submission pages use FPDI import (with GhostScript
     * fallback for PDF 1.5+ files).
     *
     * @param array  $declData       Passed straight to drawPage()
     * @param array  $reportData     Passed straight to drawMarkingReportContent()
     * @param string $submissionPath Absolute path to the original submission PDF
     * @param array  $stamps         annotations_json rows
     * @param string $outputPath     Where to write the final locked PDF
     */
    public function buildFinalPdfWithStamps(
        array  $declData,
        array  $reportData,
        string $submissionPath,
        array  $stamps,
        string $outputPath
    ): void {
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            throw new \RuntimeException('setasign/fpdi and tecnickcom/tcpdf must be installed.');
        }

        // TCPDF was written for PHP 7 semantics where accessing an undefined array
        // key returns null silently.  PHP 8 emits a warning, and Laravel converts
        // warnings to ErrorException — breaking TCPDF's internal Output() logic.
        // We suspend Laravel's error handler for the entire PDF build so TCPDF
        // runs with PHP's default (non-throwing) error behaviour.
        $prevHandler = set_error_handler(null);

        try {

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);

        // ── Page 1: Assessor Declaration (drawn natively) ────────────────────
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('P', 'A4');
        $this->drawPage($pdf, $declData);

        // ── Page 2+: Marking Report (drawn natively) ──────────────────────────
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage('P', 'A4');
        $this->drawMarkingReportContent($pdf, $reportData);

        // ── Submission pages: FPDI import + stamps ────────────────────────────
        $pdf->SetAutoPageBreak(false);
        $gsTemp = null;

        if ($submissionPath && file_exists($submissionPath)) {
            // Pre-validate in a THROWAWAY FPDI instance.
            // If the real import would leave a broken xobject stub, TCPDF's Output()
            // will crash with "Undefined array key 'Length'".  By validating first,
            // we only call importPage() in the real document when we KNOW it succeeds,
            // so the main document's internal state is never corrupted.
            [$importSrc, $pageCount] = $this->preValidatePdf($submissionPath);
            if ($importSrc && $importSrc !== $submissionPath) $gsTemp = $importSrc;

            if ($importSrc) {
                $pdf->setSourceFile($importSrc);
                for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                    $tplId = $pdf->importPage($pageNum);
                    $sz    = $pdf->getTemplateSize($tplId);
                    $pdf->AddPage(($sz['width'] > $sz['height']) ? 'L' : 'P',
                                  [$sz['width'], $sz['height']]);
                    $pdf->useTemplate($tplId, 0, 0, $sz['width'], $sz['height']);

                    foreach ($stamps as $stamp) {
                        if ((int)($stamp['page'] ?? 0) !== $pageNum) continue;
                        $this->drawStampOnPage(
                            $pdf,
                            (float)$stamp['x_pct'] * $sz['width'],
                            (float)$stamp['y_pct'] * $sz['height'],
                            $stamp['type'] ?? 'tick'
                        );
                    }
                }
            } else {
                // PDF cannot be imported (PDF 1.5+ without GhostScript).
                // The front pages are intact; add a notice for the submission.
                $this->addMergeNoticePage($pdf);
            }
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $pdf->Output($outputPath, 'F');

        } finally {
            set_error_handler($prevHandler);
            if ($gsTemp) @unlink($gsTemp);
        }
    }

    /**
     * Draw marking report content onto an existing PDF instance.
     * Extracted so buildFinalPdfWithStamps can draw it natively without an FPDI re-import.
     */
    private function drawMarkingReportContent(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        // Title banner
        $pdf->SetFillColorArray(self::NAVY);
        $pdf->SetTextColorArray(self::WHITE);
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->SetXY(20, 20);
        $pdf->Cell(170, 12, 'MARKING REPORT', 0, 1, 'C', true);
        $pdf->Ln(3);

        // Info block
        $dateStr  = is_string($d['date'] ?? '') ? $d['date'] : ($d['date'] ? $d['date']->format('d F Y') : date('d F Y'));
        $infoHtml = sprintf(
            '<table border="0.3" cellpadding="4" style="background-color:#f5f7fa; font-size:9pt;">
                <tr><td width="38%%"><b>Assignment:</b></td><td>%s</td></tr>
                <tr><td><b>Learner:</b></td><td>%s &nbsp;|&nbsp; Student No: %s</td></tr>
                <tr><td><b>Assessor:</b></td><td>%s</td></tr>
                <tr><td><b>Date Assessed:</b></td><td>%s</td></tr>
            </table>',
            htmlspecialchars($d['assignment_name'] ?? ''),
            htmlspecialchars($d['learner_name']    ?? ''),
            htmlspecialchars($d['student_no']      ?? ''),
            htmlspecialchars($d['assessor_name']   ?? ''),
            $dateStr
        );
        $pdf->SetTextColorArray(self::BLACK);
        $pdf->writeHTML($infoHtml, true, false, true, false, '');
        $pdf->Ln(4);

        // Criteria table
        $questions = $d['questions'] ?? [];
        $tableHtml = '<table border="0.3" cellpadding="4" cellspacing="0" style="font-size:8.5pt;">
            <tr style="background-color:#1e3a5f; color:#ffffff; font-weight:bold; font-size:8pt;">
                <td width="4%"  align="center">#</td>
                <td width="37%">Criterion / Question</td>
                <td width="7%"  align="center">Max</td>
                <td width="7%"  align="center">Mark</td>
                <td width="45%">Assessor Feedback / Comment</td>
            </tr>';

        foreach ($questions as $i => $q) {
            $max     = (int)($q['max_marks'] ?? 1);
            $awarded = (int)($q['awarded']   ?? 0);
            $pct     = $max > 0 ? $awarded / $max : 0;
            $rowBg   = $pct >= 0.5 ? '#f0fdf4' : '#fef2f2';
            $markCol = $pct >= 0.5 ? '#166534' : '#991b1b';
            $crit    = $q['criterion'] ?? $q['question'] ?? '—';
            if (preg_match('/^\[([^\]]+)\]\s*(.+)/su', $crit, $cm)) {
                $crit = '[' . $cm[1] . '] ' . trim($cm[2]);
            }
            $tableHtml .= sprintf(
                '<tr style="background-color:%s;">
                    <td align="center" style="font-weight:bold; color:%s;">%d</td>
                    <td style="font-size:8pt;">%s</td>
                    <td align="center">%d</td>
                    <td align="center" style="font-weight:bold; color:%s;">%d</td>
                    <td style="font-size:8pt; color:#374151;">%s</td>
                </tr>',
                $rowBg, $markCol, $i + 1,
                htmlspecialchars($crit), $max, $markCol, $awarded,
                htmlspecialchars($q['comment'] ?? '—')
            );
        }
        $tableHtml .= '</table>';
        $pdf->writeHTML($tableHtml, true, false, true, false, '');
        $pdf->Ln(4);

        // Totals / verdict
        $totalMax     = array_sum(array_column($questions, 'max_marks'));
        $totalAwarded = array_sum(array_column($questions, 'awarded'));
        $pctTotal     = $totalMax > 0 ? round($totalAwarded / $totalMax * 100) : 0;
        $isC          = ($d['verdict'] ?? '') === 'COMPETENT';

        $pdf->writeHTML(sprintf(
            '<table border="0.5" cellpadding="6" style="font-size:11pt;">
                <tr style="background-color:%s;">
                    <td width="55%%"><b>Total Score:</b> %d / %d &nbsp;(%d%%)</td>
                    <td width="45%%" align="center" style="font-weight:bold; color:%s; font-size:13pt;">%s</td>
                </tr>
            </table>',
            $isC ? '#f0fdf4' : '#fef2f2',
            $totalAwarded, $totalMax, $pctTotal,
            $isC ? '#166534' : '#991b1b',
            $isC ? '✓ COMPETENT' : '✗ NOT YET COMPETENT'
        ), true, false, true, false, '');

        $pdf->Ln(6);

        // ── Assessor sign-off block (signature + ETQA + stamp) ──────────────
        $this->drawAssessorSignOff($pdf, $d);

        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetY(285);
        $pdf->Cell(0, 5, 'CONFIDENTIAL — For assessment and moderation purposes only.', 0, 1, 'C');
    }

    /**
     * Compact sign-off block for the bottom of the Marking Report:
     * Assessor name + ETQA registration on the left, signature image over a
     * line in the middle, official stamp on the right.  Falls back to plain
     * underlines when the assessor has not yet uploaded a signature/stamp.
     */
    public function drawAssessorSignOff(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        $y       = $pdf->GetY();
        // Keep the block on the same page as the totals — push to bottom area
        // if there's already very little room left.
        if ($y > 240) { $pdf->AddPage(); $y = 25; }

        $lm = 20;
        $w  = 170;

        $pdf->SetDrawColorArray(self::BORDER);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($lm, $y, $lm + $w, $y);
        $y += 4;

        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetXY($lm, $y);
        $pdf->Cell($w, 5, 'ASSESSOR SIGN-OFF', 0, 1, 'L');
        $y += 6;

        $colW   = $w / 3;
        $rowH   = 22;
        $labelY = $y + $rowH - 4;

        // ── Left: Name + ETQA ────────────────────────────────────────────────
        $pdf->SetTextColorArray(self::BLACK);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetXY($lm, $y);
        $pdf->Cell($colW - 4, 5, $d['assessor_name'] ?? '', 0, 2, 'L');
        if (! empty($d['etqa_registration'])) {
            $pdf->SetFont('dejavusans', 'I', 8);
            $pdf->SetTextColorArray(self::GRAY);
            $pdf->Cell($colW - 4, 4, 'ETQA: ' . $d['etqa_registration'], 0, 1, 'L');
        }
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetXY($lm, $labelY);
        $pdf->Cell($colW - 4, 4, 'Assessor', 0, 0, 'L');

        // ── Middle: Signature ────────────────────────────────────────────────
        $sigX  = $lm + $colW;
        $sigW  = $colW - 4;
        $sigBl = $y + $rowH - 7;

        $pdf->SetDrawColorArray(self::BORDER);
        $pdf->Line($sigX, $sigBl, $sigX + $sigW, $sigBl);

        $sigPath = $d['signature_path'] ?? null;
        if ($sigPath && is_file($sigPath)) {
            try {
                $pdf->Image($sigPath, $sigX, $y, 0, $rowH - 9,
                    '', '', '', false, 300, '', false, false, 0, 'LM');
            } catch (\Throwable $e) { /* leave blank line */ }
        }
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetXY($sigX, $labelY);
        $pdf->Cell($sigW, 4, 'Signature', 0, 0, 'L');

        // ── Right: Stamp ─────────────────────────────────────────────────────
        $stX  = $lm + 2 * $colW;
        $stW  = $colW - 4;
        $stH  = $rowH - 4;
        $pdf->SetDrawColorArray(self::BORDER);
        $pdf->SetLineStyle(['width' => 0.25, 'dash' => '2,2']);
        $pdf->Rect($stX, $y, $stW, $stH, 'D');
        $pdf->SetLineStyle(['width' => 0.2, 'dash' => '']);

        $stampGen  = $d['stamp_generated'] ?? null;
        $stampPath = $d['stamp_path'] ?? null;
        $dateStr   = is_string($d['date'] ?? '') ? $d['date']
                   : (($d['date'] ?? null) ? $d['date']->format('d F Y') : date('d F Y'));

        if (is_array($stampGen) && ($stampGen['holder_name'] ?? '') !== '') {
            // Fit a square stamp inside the (slightly wider) box.
            $sz = min($stW, $stH) - 1;
            $sx = $stX + ($stW - $sz) / 2;
            $sy = $y   + ($stH - $sz) / 2;
            $this->drawRubberStamp($pdf, $sx + $sz / 2, $sy + $sz / 2, $sz / 2 - 1, $stampGen, $dateStr);
        } elseif ($stampPath && is_file($stampPath)) {
            try {
                $pdf->Image($stampPath, $stX + 1.5, $y + 1.5, $stW - 3, $stH - 3,
                    '', '', '', false, 300, '', false, false, 0, 'CM');
            } catch (\Throwable $e) {
                $pdf->SetTextColorArray(self::GRAY);
                $pdf->SetFont('dejavusans', 'I', 7);
                $pdf->SetXY($stX, $y + $stH / 2 - 2);
                $pdf->Cell($stW, 4, 'Official Stamp', 0, 0, 'C');
            }
        } else {
            $pdf->SetTextColorArray(self::GRAY);
            $pdf->SetFont('dejavusans', 'I', 7);
            $pdf->SetXY($stX, $y + $stH / 2 - 2);
            $pdf->Cell($stW, 4, 'Official Stamp', 0, 0, 'C');
        }
    }

    /**
     * Dashed-box "Official Stamp" placeholder (used when no stamp asset exists).
     */
    public function drawStampPlaceholder(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetDrawColorArray(self::BORDER);
        $pdf->SetLineStyle(['width' => 0.3, 'dash' => '3,2']);
        $pdf->Rect($x, $y, $w, $h, 'D');
        $pdf->SetLineStyle(['width' => 0.2, 'dash' => '']);
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetFont('dejavusans', 'I', 8);
        $pdf->SetXY($x, $y + $h / 2 - 3);
        $pdf->Cell($w, 6, 'Official Stamp', 0, 0, 'C');
    }

    /**
     * Draw a vintage circular rubber stamp at ($cx, $cy) with outer radius $R.
     * Two concentric red rings, curved top + bottom text, centre block with the
     * role label, the dynamic date (large), holder name and ETQA. Slightly
     * tilted (-7°) for an old-school "thumped on" look.
     *
     * $stamp keys: org_top, org_bottom, role, holder_name, etqa_registration
     */
    public function drawRubberStamp(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $cx, float $cy, float $R, array $stamp, string $dateStr): void
    {
        $RED = [168, 29, 29];
        $r   = $R - 2.3;

        $pdf->StartTransform();
        $pdf->Rotate(-7, $cx, $cy);

        // Two concentric rings
        $pdf->SetDrawColorArray($RED);
        $pdf->SetLineWidth(0.9);
        $pdf->Circle($cx, $cy, $R);
        $pdf->SetLineWidth(0.45);
        $pdf->Circle($cx, $cy, $r);

        // Arc text
        $arcR = ($R + $r) / 2;
        $this->drawArcText($pdf, $cx, $cy, $arcR, (string)($stamp['org_top']    ?? ''), 200, false, $arcR > 18 ? 4.0 : 3.4, $RED);
        $this->drawArcText($pdf, $cx, $cy, $arcR, (string)($stamp['org_bottom'] ?? ''), 200, true,  $arcR > 18 ? 4.0 : 3.4, $RED);

        // Centre block — divider lines around the date for that classic look.
        $cw = $r - 3;
        $pdf->SetLineWidth(0.3);
        $pdf->Line($cx - $cw, $cy - 2.6, $cx + $cw, $cy - 2.6);
        $pdf->Line($cx - $cw, $cy + 3.6, $cx + $cw, $cy + 3.6);

        $pdf->SetTextColorArray($RED);
        $pdf->SetFont('helvetica', 'B', 5.5);
        $pdf->SetXY($cx - $cw, $cy - 8);
        $pdf->Cell($cw * 2, 3, mb_strtoupper((string)($stamp['role'] ?? 'ASSESSOR')), 0, 0, 'C');

        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY($cx - $cw, $cy - 1.6);
        $pdf->Cell($cw * 2, 5, mb_strtoupper($dateStr), 0, 0, 'C');

        $pdf->SetFont('helvetica', 'B', 6.2);
        $pdf->SetXY($cx - $cw, $cy + 4);
        $pdf->Cell($cw * 2, 3, (string)($stamp['holder_name'] ?? ''), 0, 0, 'C');

        if (! empty($stamp['etqa_registration'])) {
            $pdf->SetFont('helvetica', '', 5.2);
            $pdf->SetXY($cx - $cw, $cy + 7.5);
            $pdf->Cell($cw * 2, 3, 'ETQA: ' . $stamp['etqa_registration'], 0, 0, 'C');
        }

        $pdf->StopTransform();
        // Reset
        $pdf->SetDrawColorArray([0, 0, 0]);
        $pdf->SetTextColorArray(self::BLACK);
        $pdf->SetLineWidth(0.2);
    }

    /**
     * Draw text along a circular arc.  $arcDeg is the total span in degrees.
     * When $bottom is true, the text sits along the bottom of the circle and
     * reads upright (feet-toward-centre); otherwise it sits along the top.
     */
    private function drawArcText(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $cx, float $cy, float $r, string $text, float $arcDeg, bool $bottom, float $fontSize, array $color): void
    {
        $text = mb_strtoupper(trim($text));
        if ($text === '') return;

        // Because angle increases clockwise from the top, walking the bottom
        // arc from (180 − padDeg/2) → (180 + padDeg/2) goes right-to-left in
        // screen space.  Reverse the string so it reads left-to-right to a
        // viewer looking at the stamp.
        if ($bottom) {
            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $text  = implode('', array_reverse($chars));
        }

        $pdf->SetFont('helvetica', 'B', $fontSize);
        $pdf->SetTextColorArray($color);

        $len = mb_strlen($text);
        // Width-aware spread so longer words don't overlap
        $totalW   = 0; $widths = [];
        for ($i = 0; $i < $len; $i++) {
            $widths[$i] = $pdf->GetStringWidth(mb_substr($text, $i, 1));
            $totalW += $widths[$i];
        }
        $padDeg     = min($arcDeg, max(20.0, ($totalW / max($r, 1)) * (180 / M_PI) + 12));
        $startA     = $bottom ? (180 - $padDeg / 2) : (-$padDeg / 2);
        $perCharDeg = $padDeg / max($len, 1);

        for ($i = 0; $i < $len; $i++) {
            $ch  = mb_substr($text, $i, 1);
            $a   = $startA + $perCharDeg * ($i + 0.5);
            $rad = deg2rad($a);
            $x   = $cx + $r * sin($rad);
            $y   = $cy - $r * cos($rad);
            // TCPDF Rotate is CCW in PDF coords (which is CW in screen y-down).
            // Top arc: characters tangent, "up" radially outward → rotate by  a (degrees).
            // Bottom arc: characters with feet inward, readable upright       → rotate by  a + 180.
            $rot = $bottom ? ($a + 180) : $a;
            $cw  = $widths[$i];

            $pdf->StartTransform();
            $pdf->Rotate(-$rot, $x, $y);
            $pdf->Text($x - $cw / 2, $y - $fontSize * 0.5, $ch);
            $pdf->StopTransform();
        }
    }

    /**
     * Draw a red vector tick or cross stamp at (x, y) in mm.
     * Matches the SVG path style the assessor sees in the browser — no background circle,
     * clean stroked lines, red for both types.
     */
    private function drawStampOnPage(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $x, float $y, string $type): void
    {
        $s  = 5.0;  // half-size in mm  (≈ the browser's 16px STAMP_SIZE at 96 dpi)
        $sw = 0.9;  // stroke width in mm

        $pdf->SetDrawColor(220, 38, 38); // red — same as browser (#dc2626)
        $pdf->SetLineWidth($sw);

        if ($type === 'tick') {
            $pdf->Line($x - $s * 0.55, $y + $s * 0.06, $x - $s * 0.06, $y + $s * 0.55);
            $pdf->Line($x - $s * 0.06, $y + $s * 0.55, $x + $s * 0.62, $y - $s * 0.50);
        } else {
            $pdf->Line($x - $s * 0.50, $y - $s * 0.50, $x + $s * 0.50, $y + $s * 0.50);
            $pdf->Line($x + $s * 0.50, $y - $s * 0.50, $x - $s * 0.50, $y + $s * 0.50);
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
    }


    // ─── Drawing ─────────────────────────────────────────────────────────────────

    private function drawPage(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        $lm = 20;  // left margin (mm)
        $w  = 170; // content width (mm)
        $y  = 20;

        // ── Blue header banner ──────────────────────────────────────────────────
        $pdf->SetFillColorArray(self::NAVY);
        $pdf->SetTextColorArray(self::WHITE);
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->SetXY($lm, $y);
        $pdf->Cell($w, 14, 'ASSESSOR DECLARATION', 0, 1, 'C', true);
        $y += 14;

        // ── Provider / organisation sub-header ─────────────────────────────────
        $pdf->SetFillColorArray(self::NAVY);
        $pdf->SetTextColorArray(self::WHITE);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetXY($lm, $y);
        $provider = $d['assessment_provider'] ?? 'Assessment Provider';
        $pdf->Cell($w, 6, $provider, 0, 1, 'C', true);
        $y += 8;

        // ── Assignment info box ─────────────────────────────────────────────────
        $y = $this->drawInfoBox($pdf, $lm, $y, $w, [
            ['Assignment',    $d['assignment_name']   ?? '—'],
            ['Qualification', ($d['qualification_name'] ?? '—')
                . ($d['saqa_id'] ? '  |  SAQA ID: ' . $d['saqa_id'] : '')],
        ]);
        $y += 4;

        // ── Learner info box ────────────────────────────────────────────────────
        $y = $this->drawInfoBox($pdf, $lm, $y, $w, [
            ['Learner',     $d['learner_name'] ?? '—'],
            ['Student No',  $d['student_no']   ?? '—'],
        ]);
        $y += 6;

        // ── Declaration text ────────────────────────────────────────────────────
        $pdf->SetTextColorArray(self::BLACK);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetXY($lm, $y);

        $assessorName = $d['assessor_name']    ?? 'the assessor';
        $etqa         = $d['etqa_registration'] ?? '—';

        $declaration  = "I, {$assessorName}, with ETQA registration number {$etqa}, hereby declare that "
            . "I have assessed the evidence contained in this Portfolio of Evidence against the relevant "
            . "assessment criteria and that the above-named learner has been found:";

        $pdf->SetFont('dejavusans', 'I', 9);
        $pdf->MultiCell($w, 5, $declaration, 0, 'L');
        $y = $pdf->GetY() + 8;

        // ── Verdict checkboxes ──────────────────────────────────────────────────
        $verdict      = $d['verdict'] ?? '';
        $isCompetent  = $verdict === 'COMPETENT';
        $colW         = $w / 2;

        // Competent
        $pdf->SetXY($lm, $y);
        $this->drawCheckbox($pdf, $lm + 8, $y + 1, $isCompetent);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->SetTextColorArray($isCompetent ? self::GREEN : self::GRAY);
        $pdf->SetXY($lm + 18, $y);
        $pdf->Cell($colW - 18, 8, 'COMPETENT', 0, 0, 'L');

        // Not Yet Competent
        $this->drawCheckbox($pdf, $lm + $colW + 8, $y + 1, !$isCompetent);
        $pdf->SetTextColorArray($isCompetent ? self::GRAY : self::RED);
        $pdf->SetXY($lm + $colW + 18, $y);
        $pdf->Cell($colW - 18, 8, 'NOT YET COMPETENT', 0, 1, 'L');

        $y += 16;

        // ── Sign-off zone: facts (left) + signature box (centre) + stamp (right) ─
        // Redesigned layout — gives signature a dedicated 90×26mm box and the
        // stamp a square 55×55mm zone where a circular rubber stamp fits well.
        $pdf->SetTextColorArray(self::BLACK);
        $pdf->SetFont('dejavusans', '', 9);
        $dateStr = is_string($d['date']) ? $d['date'] : ($d['date'] ? $d['date']->format('d F Y') : date('d F Y'));

        $factsW   = 60;
        $sigW     = 50;
        $stampSz  = 55;
        $gap      = 5;
        // facts (60) + gap + sig (50) + gap + stamp (55) = 175 (just over 170 — tighten)
        // Re-balance so total = 170 exactly:
        $factsW = 58; $sigW = 50; $stampSz = 52; $gap = 5;

        $factsX = $lm;
        $sigX   = $lm + $factsW + $gap;
        $stampX = $sigX + $sigW + $gap;

        // Facts column
        $factsRows = [
            ['Name',  $d['assessor_name']     ?? ''],
            ['ETQA',  $d['etqa_registration'] ?? ''],
            ['Date',  $dateStr],
        ];
        $cy = $y;
        foreach ($factsRows as [$label, $value]) {
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetTextColorArray(self::GRAY);
            $pdf->SetXY($factsX, $cy);
            $pdf->Cell($factsW, 4, strtoupper($label), 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 9.5);
            $pdf->SetTextColorArray(self::BLACK);
            $pdf->SetXY($factsX, $cy + 4);
            $pdf->Cell($factsW, 5, $value !== '' ? $value : '—', 0, 1, 'L');
            $cy += 12;
        }

        // Signature box
        $sigH = 30;
        $pdf->SetDrawColorArray(self::BORDER);
        $pdf->SetLineStyle(['width' => 0.3, 'dash' => '']);
        $pdf->RoundedRect($sigX, $y, $sigW, $sigH, 1.5, '1111', 'D');

        // baseline inside the box
        $sigBl = $y + $sigH - 6;
        $pdf->SetLineStyle(['width' => 0.4, 'dash' => '']);
        $pdf->Line($sigX + 4, $sigBl, $sigX + $sigW - 4, $sigBl);

        $sigPath = $d['signature_path'] ?? null;
        if ($sigPath && is_file($sigPath)) {
            try {
                // Centred horizontally inside the box, sitting just above the baseline.
                $imgH = 18;
                $pdf->Image($sigPath, $sigX + 3, $y + 3, $sigW - 6, $imgH,
                    '', '', '', false, 300, '', false, false, 0, 'CB');
            } catch (\Throwable $e) { /* leave blank */ }
        }
        // Caption
        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetXY($sigX, $y + $sigH + 0.5);
        $pdf->Cell($sigW, 4, 'SIGNATURE', 0, 0, 'C');

        // Stamp zone (square)
        $stampGen  = $d['stamp_generated'] ?? null;
        $stampPath = $d['stamp_path']     ?? null;

        if (is_array($stampGen) && ($stampGen['holder_name'] ?? '') !== '') {
            $this->drawRubberStamp(
                $pdf,
                $stampX + $stampSz / 2,
                $y + $stampSz / 2,
                $stampSz / 2 - 1,
                $stampGen,
                $dateStr
            );
        } elseif ($stampPath && is_file($stampPath)) {
            try {
                $pdf->Image($stampPath, $stampX + 1.5, $y + 1.5, $stampSz - 3, $stampSz - 3,
                    '', '', '', false, 300, '', false, false, 0, 'CM');
            } catch (\Throwable $e) {
                $this->drawStampPlaceholder($pdf, $stampX, $y, $stampSz, $stampSz);
            }
        } else {
            $this->drawStampPlaceholder($pdf, $stampX, $y, $stampSz, $stampSz);
        }
        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetXY($stampX, $y + $stampSz + 0.5);
        $pdf->Cell($stampSz, 4, 'OFFICIAL STAMP', 0, 0, 'C');

        $y += $sigH + 8;

        // ── Footer ──────────────────────────────────────────────────────────────
        $pdf->SetTextColorArray(self::GRAY);
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetXY($lm, 280);
        $pdf->Cell($w, 5, 'CONFIDENTIAL — For assessment and moderation purposes only.  QCTO Assessor Declaration', 0, 0, 'C');
    }

    private function drawInfoBox(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $x, float $y, float $w, array $rows): float
    {
        $rowH   = 6.5;
        $height = count($rows) * $rowH + 5;
        $labelW = 45;

        $pdf->SetFillColorArray(self::LIGHT);
        $pdf->SetDrawColorArray(self::BORDER);
        $pdf->RoundedRect($x, $y, $w, $height, 2, '1111', 'DF');

        $cy = $y + 3;
        foreach ($rows as [$label, $value]) {
            $pdf->SetFont('dejavusans', 'B', 8.5);
            $pdf->SetTextColorArray(self::BLACK);
            $pdf->SetXY($x + 4, $cy);
            $pdf->Cell($labelW, $rowH, $label . ':', 0, 0, 'L');

            $pdf->SetFont('dejavusans', '', 8.5);
            $pdf->SetTextColorArray([50, 50, 50]);
            $pdf->Cell($w - $labelW - 8, $rowH, $value, 0, 1, 'L');

            $cy += $rowH;
        }

        $pdf->SetTextColorArray(self::BLACK);
        $pdf->SetDrawColorArray([0, 0, 0]);

        return $y + $height;
    }

    private function drawCheckbox(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $x, float $y, bool $checked): void
    {
        $size = 6;
        $pdf->SetDrawColorArray([60, 60, 60]);
        $pdf->SetFillColorArray(self::WHITE);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect($x, $y, $size, $size, 'DF');
        $pdf->SetLineWidth(0.2);

        if ($checked) {
            $pdf->SetDrawColorArray([0, 120, 0]);
            $pdf->SetLineWidth(1.2);
            // Draw a tick inside the box
            $pdf->Line($x + 1, $y + 3, $x + 2.5, $y + 5);
            $pdf->Line($x + 2.5, $y + 5, $x + 5.5, $y + 1);
            $pdf->SetLineWidth(0.2);
            $pdf->SetDrawColorArray([0, 0, 0]);
        }
    }
}
