<?php
namespace local_poeexport\pdf;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the Competency Tracking Document PDF.
 *
 * Renders a table of all KM/PM/WM modules mapped to course activities,
 * showing the learner's grade and competency status per module, with
 * credit accumulation and an overall result.
 *
 * @package   local_poeexport
 * @copyright 2026 POE Export
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracking_document {

    const COMPETENCY_THRESHOLD = 70; // percent

    /**
     * Generate the tracking document PDF.
     *
     * @param  object|null $settings  local_poeexport_course_settings row
     * @param  object|null $qual      local_poeexport_qualifications row
     * @param  array       $modules   Rows from local_poeexport_qual_modules (ordered by sortorder)
     * @param  object      $user      Moodle learner record
     * @param  object      $course    Moodle course record
     * @param  string      $tempdir   Writable temp directory
     * @return string|null  Path to generated PDF
     */
    public static function generate(
        ?object $settings,
        ?object $qual,
        array   $modules,
        object  $user,
        object  $course,
        string  $tempdir,
        array   $assessor_signoffs = [],   // [moduleid => signer_initials_string]
        array   $module_sig_paths  = []    // [moduleid => local_png_path]
    ): ?string {
        global $DB, $CFG;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->libdir . '/gradelib.php');

        if (empty($modules)) { return null; }

        // ── Enrich modules with grades + signoff status ────
        $rows = self::enrich_with_grades($modules, $user->id, $course->id);
        foreach ($rows as &$row) {
            $mid = (int)($row['module_id'] ?? 0);
            $row['assessor_initials'] = $assessor_signoffs[$mid] ?? '';
        }
        unset($row);

        // All modules must have assessor sign-off AND every mapped activity must have a grade
        // before the overall verdict is meaningful.
        $all_complete = true;
        foreach ($rows as $row) {
            if ($row['assessor_initials'] === '') {
                $all_complete = false;
                break;
            }
            foreach ($row['activities'] as $act) {
                if ($act['name'] !== '— not mapped —'
                    && ($act['grade_display'] === 'N/A' || $act['grade_display'] === '')) {
                    $all_complete = false;
                    break 2;
                }
            }
        }

        // ── Build PDF ──────────────────────────────────────
        $pdf = new \pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);   // v3 — manual page breaks
        $pdf->AddPage('L', 'A4');  // Landscape for wide table

        $pageW = $pdf->getPageWidth();   // 297mm landscape
        $pageH = $pdf->getPageHeight();  // 210mm landscape

        // ── Page header ────────────────────────────────────
        $pdf->SetFillColor(0, 82, 147);
        $pdf->Rect(10, 10, $pageW - 20, 14, 'F');
        $pdf->SetFont('dejavusans', 'B', 13);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(10, 11);
        $pdf->Cell($pageW - 20, 12, 'COMPETENCY TRACKING DOCUMENT', 0, 1, 'C');

        $y = 28;

        // ── Learner & qualification info ───────────────────
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(50, 50, 50);

        $qual_title = $qual ? trim((string)$qual->title) : format_string($course->fullname);
        $saqa_str   = ($qual && $qual->saqa_id) ? ' | SAQA ID: ' . $qual->saqa_id : '';
        $nqf_str    = ($qual && $qual->nqf_level) ? ' | NQF Level ' . $qual->nqf_level : '';
        $cred_str   = ($qual && $qual->total_credits) ? ' | ' . $qual->total_credits . ' Credits' : '';

        $pdf->SetXY(10, $y);
        $pdf->Cell($pageW - 20, 5, 'Learner: ' . fullname($user) . '   |   Student No: ' . s($user->username) . '   |   Date: ' . userdate(time(), get_string('strftimedate', 'langconfig')), 0, 1, 'L');
        $y = $pdf->GetY() + 1;
        $pdf->SetXY(10, $y);
        $pdf->Cell($pageW - 20, 5, 'Qualification: ' . $qual_title . $saqa_str . $nqf_str . $cred_str, 0, 1, 'L');
        $y = $pdf->GetY() + 3;

        // ── Table ──────────────────────────────────────────
        // Column widths (landscape A4 = 277mm usable)
        $usable  = $pageW - 20;
        $col_w   = [
            'code'       =>  38,
            'type'       =>  12,
            'title'      =>  80,
            'nqf'        =>  10,
            'credits'    =>  12,
            'activity'   =>  55,
            'grade'      =>  28,
            'competent'  =>  14,
            'initials'   =>  20,
        ];
        // Stretch title to fill remaining space
        $fixed = array_sum($col_w) - $col_w['title'];
        $col_w['title'] = $usable - $fixed;

        // Header row
        self::table_header($pdf, $y, $col_w);
        $y = $pdf->GetY();

        // Data rows
        $total_credits_required  = 0;
        $total_credits_achieved  = 0;
        $competent_count         = 0;
        $row_num                 = 0;

        foreach ($rows as $row) {
            $total_credits_required += (int)$row['credits'];
            if ($row['competent']) {
                $total_credits_achieved += (int)$row['credits'];
                $competent_count++;
            }

            $fill = ($row_num % 2 === 0) ? [245, 248, 252] : [255, 255, 255];
            $pdf->SetFillColor(...$fill);

            // Manual page break: if the next row won't fit, start a new page
            $row_h = self::calc_row_height($pdf, $row, $col_w);
            if ($y + $row_h > $pageH - 12) {
                $pdf->AddPage('L', 'A4');
                $pdf->SetFillColor(0, 82, 147);
                $pdf->Rect(10, 10, $pageW - 20, 8, 'F');
                $pdf->SetFont('dejavusans', 'B', 9);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY(10, 10);
                $pdf->Cell($pageW - 20, 8, 'COMPETENCY TRACKING DOCUMENT (continued)', 0, 1, 'C');
                self::table_header($pdf, 20, $col_w);
                $y = $pdf->GetY();
            }

            $sig_path = $module_sig_paths[$row['module_id']] ?? '';
            self::table_row($pdf, $y, $col_w, $row, $fill, $sig_path);
            $y = $pdf->GetY();
            $row_num++;
        }

        // ── Summary row ────────────────────────────────────
        $y = $pdf->GetY() + 4;
        if ($y + 8 > $pageH - 12) {
            $pdf->AddPage('L', 'A4');
            $pdf->SetFillColor(0, 82, 147);
            $pdf->Rect(10, 10, $pageW - 20, 8, 'F');
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(10, 10);
            $pdf->Cell($pageW - 20, 8, 'COMPETENCY TRACKING DOCUMENT (continued)', 0, 1, 'C');
            $y = 22;
        }
        $overall_competent = ($total_credits_achieved >= $total_credits_required && $total_credits_required > 0);

        $pdf->SetFillColor(230, 244, 234);
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetTextColor(22, 101, 52);
        $pdf->SetXY(10, $y);
        $pdf->Cell($usable - 80, 8, 'Credits achieved: ' . $total_credits_achieved . ' / ' . $total_credits_required, 'LTB', 0, 'L', true);
        $pdf->Cell(30, 8, $competent_count . '/' . count($rows) . ' modules', 'TBR', 0, 'C', true);
        if (!$all_complete) {
            $pdf->SetFillColor(224, 231, 255);
            $pdf->SetTextColor(67, 56, 202);
            $pdf->Cell(50, 8, 'IN PROGRESS', 'TBR', 1, 'C', true);
        } elseif ($overall_competent) {
            $pdf->SetFillColor(220, 252, 231);
            $pdf->SetTextColor(22, 101, 52);
            $pdf->Cell(50, 8, 'OVERALL: COMPETENT', 'TBR', 1, 'C', true);
        } else {
            $pdf->SetFillColor(254, 226, 226);
            $pdf->SetTextColor(153, 27, 27);
            $pdf->Cell(50, 8, 'OVERALL: NOT YET COMPETENT', 'TBR', 1, 'C', true);
        }

        $outpath = $tempdir . '/tracking_' . $user->id . '_' . time() . '.pdf';
        $pdf->Output($outpath, 'F');
        return file_exists($outpath) ? $outpath : null;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Pre-calculate the total height a row will occupy, using the same
     * wrapping logic as table_row(), so we can decide before drawing whether
     * a new page is needed.
     */
    private static function calc_row_height(\pdf $pdf, array $row, array $col_w): float {
        $pdf->SetFont('dejavusans', '', 8);  // must match the font used in table_row
        $line_h  = 5;
        $min_sub = 7;

        $sub_heights = [];
        foreach ($row['activities'] as $act) {
            $lines         = $act['name'] !== '' ? $pdf->getNumLines($act['name'], $col_w['activity']) : 1;
            $sub_heights[] = max($min_sub, $lines * $line_h);
        }
        $activities_h = empty($sub_heights) ? $min_sub : array_sum($sub_heights);

        $title_lines = $pdf->getNumLines($row['title'], $col_w['title']);
        $title_h     = max($min_sub, $title_lines * $line_h);

        return max($activities_h, $title_h);
    }

    private static function table_header(\pdf $pdf, float $y, array $col_w): void {
        $headers = [
            'code'      => 'Code',
            'type'      => 'Type',
            'title'     => 'Module Title',
            'nqf'       => 'NQF',
            'credits'   => 'Credits',
            'activity'  => 'Moodle Activity',
            'grade'     => 'Grade',
            'competent' => 'Result',
            'initials'  => 'Sign-off',
        ];

        $pdf->SetFillColor(0, 82, 147);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetXY(10, $y);

        foreach ($headers as $key => $label) {
            $pdf->Cell($col_w[$key], 7, $label, 1, 0, 'C', true);
        }
        $pdf->Ln();
    }

    private static function table_row(\pdf $pdf, float $y, array $col_w, array $row, array $fill_rgb, string $sig_path = ''): void {
        $line_h  = 5;
        $min_sub = 7;

        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFillColor(...$fill_rgb);

        // Per-activity sub-row heights (each activity name may wrap)
        $sub_heights = [];
        foreach ($row['activities'] as $act) {
            $lines         = $act['name'] !== '' ? $pdf->getNumLines($act['name'], $col_w['activity']) : 1;
            $sub_heights[] = max($min_sub, $lines * $line_h);
        }
        $activities_h = array_sum($sub_heights);

        // Spanning left cells must be at least as tall as the title wrapping needs
        $title_lines = $pdf->getNumLines($row['title'], $col_w['title']);
        $title_h     = max($min_sub, $title_lines * $line_h);

        $row_h = max($activities_h, $title_h);

        // ── Spanning cells (Code / Type / Title / NQF / Credits) ──
        $x = 10;

        $pdf->SetFillColor(...$fill_rgb);
        $pdf->Rect($x, $y, $col_w['code'], $row_h, 'DF');
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($col_w['code'], $line_h, $row['module_code'], 0, 'C', false, 1, '', '', true, 0, false, true, $row_h, 'M');
        $x += $col_w['code'];
        $pdf->SetXY($x, $y);

        $pdf->SetXY($x, $y);
        $pdf->Cell($col_w['type'], $row_h, $row['module_type'], 1, 0, 'C', true);
        $x += $col_w['type'];

        $pdf->SetFillColor(...$fill_rgb);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Rect($x, $y, $col_w['title'], $row_h, 'DF');
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($col_w['title'], $line_h, $row['title'], 0, 'L', false, 1, '', '', true, 0, false, true, $row_h, 'M');
        $x += $col_w['title'];

        $pdf->SetFillColor(...$fill_rgb);
        $pdf->SetXY($x, $y);
        $pdf->Cell($col_w['nqf'], $row_h, 'L' . $row['nqf_level'], 1, 0, 'C', true);
        $x += $col_w['nqf'];

        $pdf->SetXY($x, $y);
        $pdf->Cell($col_w['credits'], $row_h, (string)$row['credits'], 1, 0, 'C', true);
        $x += $col_w['credits'];

        // ── Activity sub-rows (Activity / Grade / Result / Sign-off) ──
        $x_act    = $x;
        $y_sub    = $y;
        $initials = $row['assessor_initials'] ?? '';
        $has_sig  = ($sig_path !== '' && file_exists($sig_path) && $initials !== '');

        foreach ($row['activities'] as $i => $act) {
            $sub_h = $sub_heights[$i];
            $x     = $x_act;

            // Activity name — pre-draw fill+border via Rect so both span the full sub_h,
            // then overlay text with MultiCell (no fill, no border).
            $pdf->SetFillColor(...$fill_rgb);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Rect($x, $y_sub, $col_w['activity'], $sub_h, 'DF');
            $pdf->SetXY($x, $y_sub);
            $pdf->MultiCell($col_w['activity'], $line_h, $act['name'], 0, 'L', false, 1, '', '', true, 0, false, true, $sub_h);
            $x += $col_w['activity'];

            // Grade
            $pdf->SetFillColor(...$fill_rgb);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($x, $y_sub);
            $pdf->Cell($col_w['grade'], $sub_h, $act['grade_display'], 1, 0, 'C', true);
            $x += $col_w['grade'];

            // Result badge
            $pdf->SetXY($x, $y_sub);
            $grade = $act['grade_display'];
            if ($grade !== 'N/A' && $grade !== '') {
                if ($act['competent']) {
                    $pdf->SetFillColor(220, 252, 231);
                    $pdf->SetTextColor(22, 101, 52);
                    $pdf->Cell($col_w['competent'], $sub_h, 'C', 1, 0, 'C', true);
                } else {
                    $pdf->SetFillColor(254, 226, 226);
                    $pdf->SetTextColor(153, 27, 27);
                    $pdf->Cell($col_w['competent'], $sub_h, 'NYC', 1, 0, 'C', true);
                }
            } else {
                $pdf->SetFillColor(...$fill_rgb);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->Cell($col_w['competent'], $sub_h, '—', 1, 0, 'C', true);
            }
            $x += $col_w['competent'];

            // ── Per-activity Sign-off cell ──────────────────────────
            $pdf->SetDrawColor(0, 0, 0);
            if ($has_sig) {
                $pdf->SetFillColor(...$fill_rgb);
                $pdf->Rect($x, $y_sub, $col_w['initials'], $sub_h, 'DF');
                $pdf->Image($sig_path, $x + 1, $y_sub + 1,
                            $col_w['initials'] - 2, $sub_h - 2,
                            'PNG', '', '', true, 150, '', false, false, 0, 'A');
            } elseif ($initials !== '') {
                $pdf->SetFillColor(220, 252, 231);
                $pdf->SetTextColor(22, 101, 52);
                $pdf->SetFont('dejavusans', 'B', 9);
                $pdf->SetXY($x, $y_sub);
                $pdf->Cell($col_w['initials'], $sub_h, $initials, 1, 0, 'C', true);
            } else {
                $pdf->SetFillColor(...$fill_rgb);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->SetXY($x, $y_sub);
                $pdf->Cell($col_w['initials'], $sub_h, '', 1, 0, 'C', true);
            }
            // Reset font/colour for next sub-row
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetTextColor(30, 30, 30);

            $y_sub += $sub_h;
        }

        // If the title made the row taller than the activities, fill the gap
        if ($y_sub < $y + $row_h) {
            $remaining = ($y + $row_h) - $y_sub;
            $pdf->SetFillColor(...$fill_rgb);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($x_act, $y_sub);
            $right_w = $col_w['activity'] + $col_w['grade'] + $col_w['competent'] + $col_w['initials'];
            $pdf->Cell($right_w, $remaining, '', 1, 0, 'C', true);
        }

        // Advance cursor to start of next row
        $pdf->SetXY(10, $y + $row_h);
    }

    /**
     * Enrich module records with grade data from the learner's Moodle grades.
     *
     * Returns one row per module. Each row contains an 'activities' array
     * where every mapped activity has its own name, grade, and competency flag,
     * so the PDF can render one sub-row per activity.
     */
    private static function enrich_with_grades(array $modules, int $userid, int $courseid): array {
        global $DB;

        $modinfo = get_fast_modinfo(get_course($courseid));

        // Load all cmid mappings for these modules from the junction table
        $modids = array_map(fn($m) => (int)$m->id, $modules);
        $module_cmids = []; // moduleid => [cmid, ...]
        if (!empty($modids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($modids);
            $mappings = $DB->get_records_select('local_poeexport_module_cms', "moduleid $insql", $inparams, 'moduleid, sortorder');
            foreach ($mappings as $m) {
                $module_cmids[(int)$m->moduleid][] = (int)$m->cmid;
            }
        }

        $rows = [];
        foreach ($modules as $mod) {
            $cmids = $module_cmids[(int)$mod->id] ?? [];

            // Legacy fallback: if nothing in junction table, try the old cmid column
            if (empty($cmids) && !empty($mod->cmid) && (int)$mod->cmid > 0) {
                $cmids = [(int)$mod->cmid];
            }

            $activities    = [];
            $best_pct      = -1;
            $mod_competent = false;

            // Deduplicate: duplicate junction-table rows would produce phantom blank sub-rows
            $cmids = array_values(array_unique(array_filter($cmids)));

            foreach ($cmids as $cmid) {
                try {
                    $cm            = $modinfo->get_cm($cmid);
                    $act_grade     = 'N/A';
                    $act_competent = false;

                    $gi = \grade_item::fetch([
                        'courseid'     => $courseid,
                        'itemtype'     => 'mod',
                        'itemmodule'   => $cm->modname,
                        'iteminstance' => $cm->instance,
                    ]);

                    if ($gi) {
                        $gg = \grade_grade::fetch(['itemid' => $gi->id, 'userid' => $userid]);
                        if ($gg && $gg->finalgrade !== null && (float)$gg->finalgrade >= 0) {
                            $pct     = 0;
                            $display = '';
                            if ((int)$gi->gradetype === GRADE_TYPE_VALUE && $gi->grademax > 0) {
                                $pct     = round(($gg->finalgrade / $gi->grademax) * 100, 1);
                                $display = round((float)$gg->finalgrade, 1) . '/' . round($gi->grademax, 0) . ' (' . $pct . '%)';
                            } elseif ((int)$gi->gradetype === GRADE_TYPE_SCALE) {
                                $scale = $DB->get_record('scale', ['id' => $gi->scaleid]);
                                if ($scale) {
                                    $items   = array_map('trim', explode(',', $scale->scale));
                                    $count   = count($items);
                                    $val     = max(1, min((int)round($gg->finalgrade), $count));
                                    $pct     = $count > 1 ? round(($val - 1) / ($count - 1) * 100, 1) : 0;
                                    $display = $items[$val - 1] . ' (' . $pct . '%)';
                                }
                            }
                            if ($display !== '') {
                                $act_grade     = $display;
                                $act_competent = ($pct >= self::COMPETENCY_THRESHOLD);
                                // Module is competent if any activity reaches the threshold
                                if ($pct > $best_pct) {
                                    $best_pct      = $pct;
                                    $mod_competent = $act_competent;
                                }
                            }
                        }
                    }

                    // Strip HTML (Moodle labels store their content as HTML in the name field)
                    // then check if any visible text remains before adding the sub-row.
                    $act_name = trim(strip_tags(html_entity_decode((string)$cm->name, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    if ($act_name === '') {
                        continue;  // skip labels / blank-named items — they produce empty sub-rows
                    }
                    $activities[] = [
                        'name'          => $act_name,
                        'grade_display' => $act_grade,
                        'competent'     => $act_competent,
                    ];
                } catch (\Throwable $e) {
                    $activities[] = ['name' => '[activity removed]', 'grade_display' => 'N/A', 'competent' => false];
                }
            }

            // If no activities mapped, show one placeholder sub-row
            if (empty($activities)) {
                $activities = [['name' => '— not mapped —', 'grade_display' => 'N/A', 'competent' => false]];
            }

            $rows[] = [
                'module_id'   => (int)$mod->id,
                'module_type' => $mod->module_type ?? '',
                'module_code' => $mod->module_code ?? '',
                'title'       => $mod->title ?? '',
                'nqf_level'   => $mod->nqf_level ?? '',
                'credits'     => (int)($mod->credits ?? 0),
                'activities'  => $activities,
                'competent'   => $mod_competent,
                'assessor_initials' => '',  // filled in by generate() after signoff lookup
            ];
        }

        return $rows;
    }
}
