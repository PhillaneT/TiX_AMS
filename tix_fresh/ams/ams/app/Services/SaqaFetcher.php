<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class SaqaFetcher
{
    const BASE_URL    = 'https://regqs.saqa.org.za/showQualification.php';
    const FETCH_TIMEOUT = 30;

    public static function fetch(string $saqa_id): array
    {
        $saqa_id = trim($saqa_id);

        if (!preg_match('/^\d+$/', $saqa_id)) {
            return ['ok' => false, 'error' => 'SAQA ID must be numeric.', 'data' => null];
        }

        $url  = self::BASE_URL . '?id=' . urlencode($saqa_id);
        $html = self::curlGet($url);

        if ($html === false || strlen($html) < 500) {
            return ['ok' => false, 'error' => 'Could not reach SAQA website.', 'data' => null];
        }

        try {
            $data = self::parse($html, $saqa_id);
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => 'Parsing failed: ' . $e->getMessage(), 'data' => null];
        }

        if (empty($data['title'])) {
            return ['ok' => false, 'error' => 'Qualification not found on SAQA.', 'data' => null];
        }

        return ['ok' => true, 'error' => null, 'data' => $data];
    }

    private static function curlGet(string $url): string|false
    {
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(['http' => [
                'timeout'    => self::FETCH_TIMEOUT,
                'user_agent' => 'Mozilla/5.0 (TiX-AMS)',
            ]]);
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
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (TiX-AMS)',
            CURLOPT_ENCODING       => '',
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private static function parse(string $html, string $saqa_id): array
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xpath   = new DOMXPath($doc);
        $modules = self::extractModules($xpath);

        $totalCredits = self::extractColumnMeta($xpath, 'MINIMUM CREDITS');
        if (($totalCredits === '' || $totalCredits === '0') && !empty($modules)) {
            $sum = array_sum(array_column($modules, 'credits'));
            if ($sum > 0) {
                $totalCredits = (string)$sum;
            }
        }

        return [
            'saqa_id'       => $saqa_id,
            'title'         => self::extractTitle($xpath),
            'nqf_level'     => self::extractColumnMeta($xpath, 'NQF LEVEL'),
            'total_credits' => $totalCredits,
            'modules'       => $modules,
        ];
    }

    private static function extractTitle(DOMXPath $xpath): string
    {
        $nodes = $xpath->query(
            '//*[contains(text(),"Certificate") or contains(text(),"Diploma") or ' .
            'contains(text(),"Degree") or contains(text(),"Occupational") or contains(text(),"Skills Programme")]'
        );
        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (strlen($text) > 10 && strlen($text) < 500) {
                    return $text;
                }
            }
        }

        foreach (['//h1', '//h2'] as $tag) {
            $nodes = $xpath->query($tag);
            if ($nodes && $nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if (strlen($text) > 5) {
                    return $text;
                }
            }
        }

        $nodes = $xpath->query('//title');
        if ($nodes && $nodes->length > 0) {
            $raw = trim($nodes->item(0)->textContent);
            $raw = preg_replace('/^SAQA\s*[-–]\s*/i', '', $raw);
            $raw = preg_replace('/^(Qualification|Registered Qualification)[:\s]+/i', '', $raw);
            $raw = trim($raw);
            if (strlen($raw) > 5 && stripos($raw, 'Qualifications System') === false) {
                return $raw;
            }
        }

        return '';
    }

    private static function extractColumnMeta(DOMXPath $xpath, string $upperLabel): string
    {
        $translate = 'translate(normalize-space(text()),' .
            '"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")';
        $headerCells = $xpath->query(
            '//td[b[contains(' . $translate . ',"' . $upperLabel . '")]] | ' .
            '//th[contains(' . $translate . ',"' . $upperLabel . '")]'
        );

        if (!$headerCells || $headerCells->length === 0) {
            return '';
        }

        foreach ($headerCells as $headerCell) {
            $parentRow = $headerCell->parentNode;
            if (!$parentRow || $parentRow->nodeName !== 'tr') {
                continue;
            }

            $colIndex = 0;
            $idx      = 0;
            foreach ($parentRow->childNodes as $sibling) {
                if ($sibling->nodeType === XML_ELEMENT_NODE &&
                    in_array($sibling->nodeName, ['td', 'th'])) {
                    if ($sibling->isSameNode($headerCell)) {
                        $colIndex = $idx;
                        break;
                    }
                    $idx++;
                }
            }

            $nextRow = $parentRow->nextSibling;
            while ($nextRow && ($nextRow->nodeType !== XML_ELEMENT_NODE || $nextRow->nodeName !== 'tr')) {
                $nextRow = $nextRow->nextSibling;
            }
            if (!$nextRow) {
                continue;
            }

            $dataCells = $xpath->query('td', $nextRow);
            if (!$dataCells || $dataCells->length <= $colIndex) {
                continue;
            }

            $val = str_replace("\xc2\xa0", ' ', $dataCells->item($colIndex)->textContent);
            $val = trim($val);
            $val = preg_replace('/^NQF\s+Level\s+0*/i', '', $val);
            $val = trim($val);

            if ($val === '' || strtolower($val) === 'not applicable' || strtolower($val) === 'undefined') {
                continue;
            }

            return $val;
        }

        return '';
    }

    private static function extractModules(DOMXPath $xpath): array
    {
        $km = self::extractKmPmWm($xpath);
        if (!empty($km)) {
            return $km;
        }

        $us = self::extractUnitStandards($xpath);
        if (!empty($us)) {
            return $us;
        }

        return self::extractGeneric($xpath);
    }

    private static function extractKmPmWm(DOMXPath $xpath): array
    {
        $modules = [];
        $order   = 0;
        $liNodes = $xpath->query('//li');

        if (!$liNodes) {
            return [];
        }

        foreach ($liNodes as $li) {
            $text = trim($li->textContent);
            if (strlen($text) < 10) {
                continue;
            }

            if (!preg_match(
                '/^([A-Z0-9][\w\-]{5,}),?\s+(.+?),\s*Level\s+(\d+),\s*(\d+)\s+Credits?/i',
                $text,
                $m
            )) {
                continue;
            }

            $rawCode = strtoupper(trim($m[1]));
            $title   = trim($m[2]);
            $nqf     = trim($m[3]);
            $credits = (int)$m[4];

            if (strpos($rawCode, '-KM-') !== false) {
                $type = 'KM';
            } elseif (strpos($rawCode, '-PM-') !== false) {
                $type = 'PM';
            } elseif (strpos($rawCode, '-WM-') !== false) {
                $type = 'WM';
            } else {
                continue;
            }

            $order++;
            $modules[] = [
                'module_type' => $type,
                'module_code' => $rawCode,
                'title'       => $title,
                'nqf_level'   => $nqf,
                'credits'     => $credits,
                'sortorder'   => $order,
            ];
        }

        return $modules;
    }

    private static function extractUnitStandards(DOMXPath $xpath): array
    {
        $modules = [];
        $order   = 0;
        $seen    = [];

        $usLinks = $xpath->query('//a[contains(@href,"showUnitStandard.php")]');
        if (!$usLinks || $usLinks->length === 0) {
            return [];
        }

        foreach ($usLinks as $link) {
            $usId = trim($link->textContent);
            if (!preg_match('/^\d+$/', $usId)) {
                $href = $link->getAttribute('href');
                if (preg_match('/[?&]id=(\d+)/i', $href, $hm)) {
                    $usId = $hm[1];
                } else {
                    continue;
                }
            }

            if (isset($seen[$usId])) {
                continue;
            }
            $seen[$usId] = true;

            $tr = $link->parentNode;
            while ($tr && $tr->nodeName !== 'tr') {
                $tr = $tr->parentNode;
            }
            if (!$tr) {
                continue;
            }

            $tds = $xpath->query('./td', $tr);
            if (!$tds || $tds->length < 2) {
                continue;
            }

            $idCol = -1;
            for ($i = 0; $i < $tds->length; $i++) {
                $cellLinks = $xpath->query('.//a', $tds->item($i));
                foreach ($cellLinks as $cl) {
                    if ($cl->isSameNode($link)) {
                        $idCol = $i;
                        break 2;
                    }
                }
            }
            if ($idCol < 0) {
                $idCol = 0;
            }

            $title   = '';
            $nqf     = '';
            $credits = 0;
            $afterId = $idCol + 1;

            if ($afterId < $tds->length) {
                $title = trim($tds->item($afterId)->textContent);
            }

            for ($i = $afterId + 1; $i < $tds->length; $i++) {
                $cellText = trim($tds->item($i)->textContent);
                if (preg_match('/NQF\s+Level\s+(\d+)/i', $cellText, $nm)) {
                    $nqf = $nm[1];
                } elseif ($credits === 0 && preg_match('/^\d+$/', $cellText) && (int)$cellText > 0) {
                    $credits = (int)$cellText;
                }
            }

            $order++;
            $modules[] = [
                'module_type' => 'US',
                'module_code' => $usId,
                'title'       => $title,
                'nqf_level'   => $nqf,
                'credits'     => $credits,
                'sortorder'   => $order,
            ];
        }

        return $modules;
    }

    private static function extractGeneric(DOMXPath $xpath): array
    {
        $modules = [];
        $order   = 0;
        $seen    = [];

        $liNodes = $xpath->query('//li');
        if (!$liNodes) {
            return [];
        }

        foreach ($liNodes as $li) {
            $text = trim($li->textContent);
            if (!preg_match('/^(.+),\s*(\d+)\s+Credits?\.\s*$/i', $text, $m)) {
                continue;
            }

            $title   = trim($m[1]);
            $credits = (int)$m[2];

            if ($credits <= 0 || $credits > 250) {
                continue;
            }

            $nqf = '';
            if (preg_match('/^(.+?),\s*(?:NQF\s+)?Level\s+(\d+)$/i', $title, $lm)) {
                $title = trim($lm[1]);
                $nqf   = $lm[2];
            }

            if ($nqf === '') {
                $td = $li->parentNode;
                while ($td && $td->nodeName !== 'td' && $td->nodeName !== 'body') {
                    $td = $td->parentNode;
                }
                if ($td && $td->nodeName === 'td') {
                    if (preg_match('/(?:NQF\s+)?Level\s+(\d+)/i', $td->textContent, $nm)) {
                        $nqf = $nm[1];
                    }
                }
            }

            if (isset($seen[$title])) {
                continue;
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
    }
}
