<?php

namespace App\Services\Pdf;

/**
 * Competency Tracking Document — server-rendered PDF for a learner's PoE.
 *
 * Mirrors the row/info layout of the standard SETA tracking sheet
 * (Code | Type | Module Title | NQF | Credits | Moodle Activity | Grade |
 *  Result | Sign-off) and appends an Assessor sign-off block (signature,
 * stamp, ETQA registration) pulled from the assessor's profile.
 */
class TrackingDocumentGenerator
{
    private const NAVY   = [30,  58,  95];
    private const GOLD   = [227, 182, 77];
    private const WHITE  = [255, 255, 255];
    private const GRAY   = [110, 110, 110];
    private const LIGHT  = [245, 247, 250];
    private const BORDER = [200, 210, 220];
    private const GREEN  = [16,  122, 56];
    private const RED    = [185, 28,  28];

    /**
     * @param array $d {
     *   qualification: ['name', 'saqa_id', 'nqf_level', 'credits'],
     *   learner:       ['full_name', 'student_no'],
     *   modules:       [ ['code', 'type', 'title', 'nqf_level', 'credits',
     *                     'activities' => [ ['name','grade','percent','result'] ],
     *                     'status' ('C'|'NYC'|'partial'|'pending'|'unmapped'),
     *                     'status_label'] ],
     *   date:          Carbon|string,
     *   // Assessor sign-off (same shape AssessorDeclarationGenerator uses)
     *   assessor_name, etqa_registration,
     *   signature_path, stamp_path, stamp_generated,
     * }
     */
    public function generate(array $d): string
    {
        if (! class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            throw new \RuntimeException('setasign/fpdi-tcpdf must be installed.');
        }

        $prev = set_error_handler(null);
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetCreator('AjanaNova AMS');
            $pdf->SetTitle('Competency Tracking Document');
            $pdf->SetMargins(10, 12, 10);
            $pdf->SetAutoPageBreak(true, 14);
            $pdf->AddPage();

            $this->drawHeader($pdf, $d);
            $this->drawTable($pdf, $d);
            $this->drawSummary($pdf, $d);
            $this->drawSignOff($pdf, $d);

            $tmp = sys_get_temp_dir() . '/tracking_' . uniqid() . '.pdf';
            $pdf->Output($tmp, 'F');
        } finally {
            set_error_handler($prev);
        }

        return $tmp;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Drawing
    // ──────────────────────────────────────────────────────────────────────

    private function drawHeader(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        $w = 277; // landscape A4 content width

        // Title bar
        $pdf->SetFillColorArray(self::NAVY);
        $pdf->SetTextColorArray(self::WHITE);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell($w, 9, 'COMPETENCY TRACKING DOCUMENT', 0, 1, 'C', true);
        $pdf->Ln(2);

        // Two-line meta (matches the source layout)
        $dateStr = is_string($d['date'] ?? '') ? $d['date']
                 : (($d['date'] ?? null) ? $d['date']->format('d F Y') : date('d F Y'));

        $learner = $d['learner']       ?? [];
        $qual    = $d['qualification'] ?? [];

        $line1 = sprintf(
            '<b>Learner:</b> %s &nbsp;|&nbsp; <b>Student No:</b> %s &nbsp;|&nbsp; <b>Date:</b> %s',
            htmlspecialchars($learner['full_name']  ?? '—'),
            htmlspecialchars($learner['student_no'] ?? '—'),
            htmlspecialchars($dateStr)
        );
        $line2parts = [
            '<b>Qualification:</b> ' . htmlspecialchars($qual['name'] ?? '—'),
        ];
        if (! empty($qual['saqa_id']))   $line2parts[] = '<b>SAQA ID:</b> '   . htmlspecialchars($qual['saqa_id']);
        if (! empty($qual['nqf_level'])) $line2parts[] = '<b>NQF Level:</b> ' . htmlspecialchars((string)$qual['nqf_level']);
        if (! empty($qual['credits']))   $line2parts[] = htmlspecialchars((string)$qual['credits']) . ' Credits';
        $line2 = implode(' &nbsp;|&nbsp; ', $line2parts);

        $pdf->SetTextColorArray(self::NAVY);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->writeHTML(
            "<div style=\"font-size:9pt;line-height:1.5;\">{$line1}<br>{$line2}</div>",
            true, false, true, false, ''
        );
        $pdf->Ln(1);
    }

    private function drawTable(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        $pdf->SetTextColorArray(self::NAVY);

        // Column widths as % of page (sum to 100). Order matches the source layout.
        $cols = [
            ['Code',            12, 'left'],
            ['Type',             5, 'center'],
            ['Module Title',    28, 'left'],
            ['NQF',              4, 'center'],
            ['Credits',          6, 'center'],
            ['Moodle Activity', 20, 'left'],
            ['Grade',           12, 'center'],
            ['Result',           6, 'center'],
            ['Sign-off',         7, 'center'],
        ];

        $thead = '<tr style="background-color:#1e3a5f;color:#ffffff;font-weight:bold;font-size:8pt;">';
        foreach ($cols as [$label, $w, $align]) {
            $thead .= sprintf('<td width="%d%%" align="%s">%s</td>', $w, $align, htmlspecialchars($label));
        }
        $thead .= '</tr>';

        $rows = '';
        foreach ($d['modules'] ?? [] as $i => $m) {
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';

            $activities = $m['activities'] ?? [];
            if (empty($activities)) {
                $actHtml   = '<span style="color:#9ca3af;font-style:italic;">— not mapped —</span>';
                $gradeHtml = '<span style="color:#9ca3af;">N/A</span>';
                $resultTxt = '—';
                $resultColor = '#6b7280';
            } else {
                $actHtml = ''; $gradeHtml = '';
                foreach ($activities as $a) {
                    $actHtml   .= '<div>' . htmlspecialchars($a['name']  ?? '—') . '</div>';
                    if (! isset($a['grade']) || $a['grade'] === null || $a['grade'] === '') {
                        $gradeHtml .= '<div style="color:#9ca3af;">N/A</div>';
                    } else {
                        $pct = isset($a['percent']) ? sprintf(' (%.1f%%)', $a['percent']) : '';
                        $gradeHtml .= '<div>' . htmlspecialchars($a['grade']) . $pct . '</div>';
                    }
                }
                $resultTxt = match ($m['status'] ?? '') {
                    'C'       => 'C',
                    'NYC'     => 'NYC',
                    'partial' => htmlspecialchars($m['status_label'] ?? 'Partial'),
                    default   => '—',
                };
                $resultColor = match ($m['status'] ?? '') {
                    'C'       => '#166534',
                    'NYC'     => '#991b1b',
                    'partial' => '#92400e',
                    default   => '#6b7280',
                };
            }

            $rows .= sprintf(
                '<tr style="background-color:%s;font-size:8pt;">
                    <td width="12%%"><code style="font-size:7.5pt;">%s</code></td>
                    <td width="5%%"  align="center"><b>%s</b></td>
                    <td width="28%%">%s</td>
                    <td width="4%%"  align="center">%s</td>
                    <td width="6%%"  align="center">%s</td>
                    <td width="20%%">%s</td>
                    <td width="12%%" align="center">%s</td>
                    <td width="6%%"  align="center" style="font-weight:bold;color:%s;">%s</td>
                    <td width="7%%"  align="center" style="color:#9ca3af;">&nbsp;</td>
                </tr>',
                $bg,
                htmlspecialchars($m['code'] ?? ''),
                htmlspecialchars(strtoupper($m['type'] ?? '')),
                htmlspecialchars($m['title'] ?? ''),
                htmlspecialchars((string)($m['nqf_level'] ?? '—')),
                htmlspecialchars((string)($m['credits'] ?? '—')),
                $actHtml,
                $gradeHtml,
                $resultColor,
                $resultTxt
            );
        }

        $html = '<table border="0.3" cellpadding="3" cellspacing="0" style="width:100%;">'
              . $thead . $rows . '</table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(2);
    }

    private function drawSummary(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        $modules        = $d['modules'] ?? [];
        $totalCredits   = (int) ($d['qualification']['credits'] ?? array_sum(array_map(fn($m) => (int)($m['credits'] ?? 0), $modules)));
        $earnedCredits  = 0;
        $competentCount = 0;
        foreach ($modules as $m) {
            if (($m['status'] ?? '') === 'C') {
                $competentCount++;
                $earnedCredits += (int) ($m['credits'] ?? 0);
            }
        }
        $totalCount = count($modules);
        $overallC   = $totalCount > 0 && $competentCount === $totalCount;

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColorArray(self::NAVY);

        $left = sprintf('<b>Credits achieved:</b> %d / %d &nbsp;|&nbsp; <b>%d / %d modules</b>',
            $earnedCredits, $totalCredits, $competentCount, $totalCount);
        $verdictHtml = $overallC
            ? '<span style="color:#166534;font-weight:bold;">OVERALL: COMPETENT</span>'
            : '<span style="color:#991b1b;font-weight:bold;">OVERALL: NOT YET COMPETENT</span>';

        $pdf->writeHTML(
            '<table cellpadding="4" border="0" style="width:100%;font-size:9pt;">'
            . '<tr><td width="60%">' . $left . '</td>'
            . '<td width="40%" align="right">' . $verdictHtml . '</td></tr></table>',
            true, false, true, false, ''
        );
    }

    /**
     * Reuse the assessor sign-off block from AssessorDeclarationGenerator
     * (signature box + ETQA + rubber stamp / uploaded image).
     */
    private function drawSignOff(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $d): void
    {
        $pdf->Ln(2);

        // Add a new page if not enough room for the sign-off block (~45mm).
        if ($pdf->GetY() > 160) {
            $pdf->AddPage();
        }

        // Lock down auto-page-break so the sign-off helper never spills onto
        // a phantom extra page, and reset font state before the call.
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColorArray(self::NAVY);

        (new AssessorDeclarationGenerator())->drawAssessorSignOff($pdf, [
            'assessor_name'     => $d['assessor_name']     ?? '',
            'etqa_registration' => $d['etqa_registration'] ?? '',
            'signature_path'    => $d['signature_path']    ?? null,
            'stamp_path'        => $d['stamp_path']        ?? null,
            'stamp_generated'   => $d['stamp_generated']   ?? null,
            'date'              => $d['date']              ?? null,
        ]);
    }

}
