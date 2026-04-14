<?php
// This file is part of the AjanaNova Grader local plugin for Moodle.

namespace local_ajananova\grading;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads rubric or marking guide criteria from Moodle's gradingform_ tables
 * and returns them as formatted plain text for the AI prompt.
 *
 * Priority:
 *   1. Moodle Rubric     (activemethod = 'rubric')
 *   2. Moodle Marking Guide (activemethod = 'guide')
 *
 * Returns an empty string if no advanced grading is configured so the
 * marking engine can fall back to the uploaded memo PDF.
 */
class criteria_reader {

    /**
     * Returns formatted criteria text for the given assignment, or '' if none.
     *
     * @param  int $assignid   The assign.id (not the course module id).
     * @param  int $contextid  The module context id for this assignment.
     * @return string
     */
    public function get_criteria_text(int $assignid, int $contextid): string {
        global $DB;

        // grading_areas links a context + component + areaname to a grading method.
        $area = $DB->get_record('grading_areas', [
            'contextid' => $contextid,
            'component' => 'mod_assign',
            'areaname'  => 'submissions',
        ]);

        if (!$area || empty($area->activemethod)) {
            return ''; // Simple direct grading — no rubric or guide.
        }

        // Status 20 = READY (draft definitions are status 10 and are skipped).
        $definition = $DB->get_record_select(
            'grading_definitions',
            'areaid = :areaid AND method = :method AND status = :status',
            [
                'areaid' => $area->id,
                'method' => $area->activemethod,
                'status' => 20,
            ]
        );

        if (!$definition) {
            return '';
        }

        if ($area->activemethod === 'rubric') {
            return $this->format_rubric($definition->id, $definition->name);
        }

        if ($area->activemethod === 'guide') {
            return $this->format_marking_guide($definition->id, $definition->name);
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Private formatters
    // -------------------------------------------------------------------------

    /**
     * Converts a Moodle rubric into a readable text block for the AI.
     *
     * Each criterion is listed with its scoring levels so the AI understands
     * what score to award at each performance level.
     */
    private function format_rubric(int $definitionid, string $name): string {
        global $DB;

        $criteria = $DB->get_records(
            'gradingform_rubric_criteria',
            ['definitionid' => $definitionid],
            'sortorder ASC'
        );

        if (empty($criteria)) {
            return '';
        }

        $lines   = [];
        $lines[] = 'MARKING RUBRIC: ' . $name;
        $lines[] = str_repeat('=', 50);
        $lines[] = '';

        foreach ($criteria as $criterion) {
            $lines[] = 'Criterion: ' . strip_tags($criterion->description);

            $levels = $DB->get_records(
                'gradingform_rubric_levels',
                ['criterionid' => $criterion->id],
                'score ASC'
            );

            foreach ($levels as $level) {
                $lines[] = '  [' . (int) $level->score . ' marks] '
                         . strip_tags($level->definition);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Converts a Moodle marking guide into a readable text block for the AI.
     *
     * Each criterion includes its shortname, description, and maximum score.
     */
    private function format_marking_guide(int $definitionid, string $name): string {
        global $DB;

        $criteria = $DB->get_records(
            'gradingform_guide_criteria',
            ['definitionid' => $definitionid],
            'sortorder ASC'
        );

        if (empty($criteria)) {
            return '';
        }

        $lines      = [];
        $lines[]    = 'MARKING GUIDE: ' . $name;
        $lines[]    = str_repeat('=', 50);
        $lines[]    = '';
        $totalmarks = 0;

        foreach ($criteria as $criterion) {
            $max         = (float) $criterion->maxscore;
            $totalmarks += $max;

            $lines[] = 'Criterion: ' . $criterion->shortname
                     . ' (max ' . $max . ' marks)';

            if (!empty(trim($criterion->description))) {
                $lines[] = strip_tags($criterion->description);
            }

            // Assessor-facing remarks field (guidance on what to look for).
            if (!empty(trim($criterion->descriptionmarkers ?? ''))) {
                $lines[] = 'Assessor guidance: '
                         . strip_tags($criterion->descriptionmarkers);
            }

            $lines[] = '';
        }

        $lines[] = 'Total marks available: ' . $totalmarks;

        return implode("\n", $lines);
    }
}
