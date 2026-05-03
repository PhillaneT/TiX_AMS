<?php
/**
 * SAQA Qualification Fetcher — Updated to support BOTH KM/PM/WM and Unit Standard layouts.
 * 2026 Update — Unified module parser
 *
 * @package    local_poeexport
 */
namespace local_poeexport;

use \DOMDocument;
use \DOMXPath;

defined('MOODLE_INTERNAL') || die();

class saqa_fetcher {

    const BASE_URL = 'https://regqs.saqa.org.za/showQualification.php';
    const FETCH_TIMEOUT = 30;

    // -----------------------------------------------------------
    // Public Fetch
    // -----------------------------------------------------------
    public static function fetch(string $saqa_id): array {
        $saqa_id = trim($saqa_id);

        if (!preg_match('/^\d+$/', $saqa_id)) {
            return ['ok' => false, 'error' => 'SAQA ID must be numeric.', 'data' => null];
        }

        $url = self::BASE_URL . '?id=' . urlencode($saqa_id);
        $html = self::curl_get($url);

        if ($html === false || strlen($html) < 500) {
            return [
                'ok' => false,
                'error' => 'Could not reach SAQA website.',
                'data' => null
            ];
        }

        try {
            $data = self::parse($html, $saqa_id);
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => 'Parsing failed: ' . $e->getMessage(),
                'data' => null
            ];
        }

        if (empty($data['title'])) {
            return ['ok' => false, 'error' => 'Qualification not found.', 'data' => null];
        }

        return ['ok' => true, 'error' => null, 'data' => $data];
    }

    // -----------------------------------------------------------
    // HTTP GET Helper
    // -----------------------------------------------------------
    private static function curl_get(string $url): string|false {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => self::FETCH_TIMEOUT,
                    'user_agent' => 'Mozilla/5.0 (POE Export Moodle plugin)',
                ]
            ]);
            return @file_get_contents($url, false, $ctx);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::FETCH_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (POE Export Moodle plugin)',
            CURLOPT_ENCODING       => ''
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    // -----------------------------------------------------------
    // Main Parser
    // -----------------------------------------------------------
    private static function parse(string $html, string $saqa_id): array {

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $modules       = self::extract_modules($xpath, $html);
        $total_credits = self::extract_column_meta($xpath, 'MINIMUM CREDITS');

        // regqs pages often don't show MINIMUM CREDITS in metadata — sum from modules instead
        if (($total_credits === '' || $total_credits === '0') && !empty($modules)) {
            $sum = 0;
            foreach ($modules as $m) { $sum += (int)($m['credits'] ?? 0); }
            if ($sum > 0) { $total_credits = (string)$sum; }
        }

        return [
            'saqa_id'       => $saqa_id,
            'title'         => self::extract_title($xpath),
            'nqf_level'     => self::extract_column_meta($xpath, 'NQF LEVEL'),
            'total_credits' => $total_credits,
            'modules'       => $modules,
        ];
    }

    // -----------------------------------------------------------
    // Title Extractor
    // -----------------------------------------------------------
    private static function extract_title(DOMXPath $xpath): string {

        // Pattern 1 — cell containing a qualification type keyword (most reliable on SAQA regqs)
        $nodes = $xpath->query(
            '//*[contains(text(),"Certificate") or contains(text(),"Diploma") or ' .
            'contains(text(),"Degree") or contains(text(),"Occupational") or contains(text(),"Skills Programme")]'
        );
        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                // Must look like a real title: has a qualification keyword and some length
                if (strlen($text) > 10 && strlen($text) < 500) {
                    return $text;
                }
            }
        }

        // Pattern 2 — H1 / H2
        foreach (['//h1', '//h2'] as $tag) {
            $nodes = $xpath->query($tag);
            if ($nodes && $nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if (strlen($text) > 5) {
                    return $text;
                }
            }
        }

        // Pattern 3 — <title> tag (least reliable — often "SAQA - Registered Qualifications System")
        $nodes = $xpath->query('//title');
        if ($nodes && $nodes->length > 0) {
            $raw = trim($nodes->item(0)->textContent);
            // Strip common SAQA page title prefixes
            $raw = preg_replace('/^SAQA\s*[-–]\s*/i', '', $raw);
            $raw = preg_replace('/^(Qualification|Registered Qualification)[:\s]+/i', '', $raw);
            $raw = trim($raw);
            if (strlen($raw) > 5 && stripos($raw, 'Qualifications System') === false) {
                return $raw;
            }
        }

        return '';
    }

    // -----------------------------------------------------------
    // Metadata Extractor
    // Supports both allqs (<td><b>LABEL</b></td>) and regqs (<th>LABEL</th>) formats.
    // -----------------------------------------------------------
    private static function extract_column_meta(DOMXPath $xpath, string $upper_label): string {

        // Build XPath that matches either <td><b>LABEL</b></td> OR <th>LABEL</th>
        $translate = 'translate(normalize-space(text()),' .
                     '"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")';
        $header_cells = $xpath->query(
            '//td[b[contains(' . $translate . ',"' . $upper_label . '")]] | ' .
            '//th[contains(' . $translate . ',"' . $upper_label . '")]'
        );

        if (!$header_cells || $header_cells->length === 0) {
            return '';
        }

        foreach ($header_cells as $header_cell) {
            $parent_row = $header_cell->parentNode;
            if (!$parent_row || $parent_row->nodeName !== 'tr') {
                continue;
            }

            // find column index
            $col_index = 0;
            $idx = 0;
            foreach ($parent_row->childNodes as $sibling) {
                if ($sibling->nodeType === XML_ELEMENT_NODE &&
                    ($sibling->nodeName === 'td' || $sibling->nodeName === 'th')) {
                    if ($sibling->isSameNode($header_cell)) {
                        $col_index = $idx;
                        break;
                    }
                    $idx++;
                }
            }

            // Walk to the next <tr>
            $next_row = $parent_row->nextSibling;
            while ($next_row && ($next_row->nodeType !== XML_ELEMENT_NODE || $next_row->nodeName !== 'tr')) {
                $next_row = $next_row->nextSibling;
            }
            if (!$next_row) {
                continue;
            }

            $data_cells = $xpath->query('td', $next_row);
            if (!$data_cells || $data_cells->length <= $col_index) {
                continue;
            }

            $val = str_replace("\xc2\xa0", ' ', $data_cells->item($col_index)->textContent);
            $val = trim($val);
            // Strip "NQF Level " prefix if present (regqs stores "NQF Level 04")
            $val = preg_replace('/^NQF\s+Level\s+0*/i', '', $val);
            $val = trim($val);

            if ($val === '' || strtolower($val) === 'not applicable' || strtolower($val) === 'undefined') {
                continue;
            }

            return $val;
        }

        return '';
    }

    // -----------------------------------------------------------
    // Combined Module Extractor (KM/PM/WM OR Unit Standards OR Generic prose)
    // -----------------------------------------------------------
    private static function extract_modules(DOMXPath $xpath, string $html = ''): array {

        // Try new-format KM/PM/WM first
        $km_modules = self::extract_km_pm_wm_modules($xpath);
        if (!empty($km_modules)) {
            return $km_modules;
        }

        // Try Unit Standards (older SAQA / regqs anchor links)
        $us_modules = self::extract_unit_standards($xpath);
        if (!empty($us_modules)) {
            return $us_modules;
        }

        // Fall back to generic prose <li> format (HEQSF/CHE qualifications)
        return self::extract_generic_modules($xpath);
    }

    // -----------------------------------------------------------
    // Parser for KM / PM / WM format (existing SAQA v2)
    // -----------------------------------------------------------
    private static function extract_km_pm_wm_modules(DOMXPath $xpath): array {

        $modules = [];
        $order = 0;

        $li_nodes = $xpath->query('//li');
        if (!$li_nodes) {
            return [];
        }

        foreach ($li_nodes as $li) {

            $text = trim($li->textContent);
            if (strlen($text) < 10) {
                continue;
            }

            // Example patterns:
            // 251102-001-00-KM-01 Introduction to Data Science, Level 4, 6 Credits.   (space separator)
            // 351201001-KM-01, Introduction to Data Communication..., Level 5, 15 Credits.  (comma separator)
            if (!preg_match(
                '/^([A-Z0-9][\w\-]{5,}),?\s+(.+?),\s*Level\s+(\d+),\s*(\d+)\s+Credits?/i',
                $text,
                $m
            )) {
                continue;
            }

            $raw_code = strtoupper(trim($m[1]));
            $title    = trim($m[2]);
            $nqf      = trim($m[3]);
            $credits  = (int)$m[4];

            // Determine module type
            if (strpos($raw_code, '-KM-') !== false) {
                $type = 'KM';
            } elseif (strpos($raw_code, '-PM-') !== false) {
                $type = 'PM';
            } elseif (strpos($raw_code, '-WM-') !== false) {
                $type = 'WM';
            } else {
                continue;
            }

            $order++;

            $modules[] = [
                'module_type' => $type,
                'module_code' => $raw_code,
                'title'       => $title,
                'nqf_level'   => $nqf,
                'credits'     => $credits,
                'sortorder'   => $order
            ];
        }

        return $modules;
    }

    // -----------------------------------------------------------
    // Parser for Unit Standard Layout (older SAQA / regqs format)
    //
    // Rather than parsing table headers (which vary between SAQA subsystems),
    // we anchor on the unambiguous <a href="showUnitStandard.php?id=NNNNN"> links
    // that SAQA always emits for each unit standard. We then read sibling <td>
    // cells in the same <tr> to get title, NQF level, and credits.
    //
    // Typical regqs row (5 columns):
    //   <td><a href="showUnitStandard.php?id=114636">114636</a></td>
    //   <td>Title text</td>
    //   <td>Level 3</td>          ← PRE-2009 NQF (ignored)
    //   <td>NQF Level 03</td>     ← actual NQF level
    //   <td>6</td>                ← credits
    // -----------------------------------------------------------
    private static function extract_unit_standards(DOMXPath $xpath): array {

        $modules = [];
        $order   = 0;

        // Find every link that points to showUnitStandard.php
        $us_links = $xpath->query('//a[contains(@href,"showUnitStandard.php")]');
        if (!$us_links || $us_links->length === 0) {
            return [];
        }

        // Deduplicate by US ID (same US can appear in Core/Elective sections)
        $seen = [];

        foreach ($us_links as $link) {

            $us_id = trim($link->textContent);
            if (!preg_match('/^\d+$/', $us_id)) {
                // Try extracting from the href query string
                $href = $link->getAttribute('href');
                if (preg_match('/[?&]id=(\d+)/i', $href, $hm)) {
                    $us_id = $hm[1];
                } else {
                    continue;
                }
            }

            if (isset($seen[$us_id])) {
                continue; // skip duplicates
            }
            $seen[$us_id] = true;

            // Walk up to the enclosing <tr>
            $tr = $link->parentNode;
            while ($tr && $tr->nodeName !== 'tr') {
                $tr = $tr->parentNode;
            }
            if (!$tr) {
                continue;
            }

            // Get all <td> cells in this row
            $tds = $xpath->query('./td', $tr);
            if (!$tds || $tds->length < 2) {
                continue;
            }

            // Locate which cell contains this link (= the ID cell)
            $id_col = -1;
            for ($i = 0; $i < $tds->length; $i++) {
                $cell_links = $xpath->query('.//a', $tds->item($i));
                foreach ($cell_links as $cl) {
                    if ($cl->isSameNode($link)) {
                        $id_col = $i;
                        break 2;
                    }
                }
            }
            if ($id_col < 0) {
                $id_col = 0; // fallback: first column
            }

            // Extract remaining fields relative to the ID column
            // regqs layout after ID col: TITLE | PRE-2009 NQF | NQF LEVEL | CREDITS
            $title   = '';
            $nqf     = '';
            $credits = 0;

            $after_id = $id_col + 1; // next column = title

            if ($after_id < $tds->length) {
                $title = trim($tds->item($after_id)->textContent);
            }

            // Scan remaining columns for NQF and Credits
            // NQF column contains "NQF Level N" or "Level N"; Credits is a plain integer
            for ($i = $after_id + 1; $i < $tds->length; $i++) {
                $cell_text = trim($tds->item($i)->textContent);
                if (preg_match('/NQF\s+Level\s+(\d+)/i', $cell_text, $nm)) {
                    $nqf = $nm[1]; // extract just the number
                } elseif ($credits === 0 && preg_match('/^\d+$/', $cell_text) && (int)$cell_text > 0) {
                    $credits = (int)$cell_text;
                }
            }

            $order++;
            $modules[] = [
                'module_type' => 'US',
                'module_code' => $us_id,
                'title'       => $title,
                'nqf_level'   => $nqf,
                'credits'     => $credits,
                'sortorder'   => $order,
            ];
        }

        return $modules;
    } // ← closes extract_unit_standards()

    // -----------------------------------------------------------
    // Parser for Generic Prose Module Format (HEQSF / CHE qualifications)
    //
    // These pages list modules as malformed <li> tags inside a prose <td>
    // in the QUALIFICATION RULES section — no SAQA IDs, no KM/PM/WM codes.
    //
    // Typical raw HTML fragment:
    //   <li align: center;> Strategic Management, 20 Credits.
    //   <li align: center;> Organisational Behaviour, 20 Credits.
    //
    // We anchor on ALL <li> elements whose trimmed text matches
    //   "Title, N Credits."  (period required — distinguishes from articulation
    //   items which end with "NQF Level N." and section headers which end with
    //   "N Credits:" with a colon).
    //
    // NQF level is read from the parent <td> text (the intro sentence usually
    // says "Level N"); falls back to empty string if not detectable.
    // -----------------------------------------------------------
    private static function extract_generic_modules(\DOMXPath $xpath): array {

        $modules = [];
        $order   = 0;
        $seen    = [];

        $li_nodes = $xpath->query('//li');
        if (!$li_nodes) {
            return [];
        }

        foreach ($li_nodes as $li) {

            $text = trim($li->textContent);
            // Must end with ", N Credits."  — period is the key discriminator
            if (!preg_match('/^(.+),\s*(\d+)\s+Credits?\.\s*$/i', $text, $m)) {
                continue;
            }

            $title   = trim($m[1]);
            $credits = (int)$m[2];

            if ($credits <= 0 || $credits > 250) {
                continue; // skip obvious section-header totals
            }

            // Check if title itself encodes an NQF level: "Title, Level N"
            $nqf = '';
            if (preg_match('/^(.+?),\s*(?:NQF\s+)?Level\s+(\d+)$/i', $title, $lm)) {
                $title = trim($lm[1]);
                $nqf   = $lm[2];
            }

            // If no per-module NQF, read it from the parent <td> intro sentence
            if ($nqf === '') {
                $td = $li->parentNode;
                while ($td && $td->nodeName !== 'td' && $td->nodeName !== 'body') {
                    $td = $td->parentNode;
                }
                if ($td && $td->nodeName === 'td') {
                    $td_text = $td->textContent;
                    if (preg_match('/(?:NQF\s+)?Level\s+(\d+)/i', $td_text, $nm)) {
                        $nqf = $nm[1];
                    }
                }
            }

            if (isset($seen[$title])) {
                continue; // deduplicate
            }
            $seen[$title] = true;

            $order++;
            $modules[] = [
                'module_type' => 'MOD',
                'module_code' => 'MOD-' . str_pad($order, 2, '0', STR_PAD_LEFT),
                'title'       => $title,
                'nqf_level'   => $nqf,
                'credits'     => $credits,
                'sortorder'   => $order,
            ];
        }

        return $modules;
    } // ← closes extract_generic_modules()

} // ← closes class saqa_fetcher
