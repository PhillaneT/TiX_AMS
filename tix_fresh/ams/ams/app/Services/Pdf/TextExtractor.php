<?php

namespace App\Services\Pdf;

class TextExtractor
{
    /**
     * Extract text per page. Returns [pageNumber => textString].
     * Pages with no extractable text are omitted from the map but still
     * counted in countPages() so the suggester can distribute stamps correctly.
     */
    public function extractPerPage(string $absolutePath): array
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return [];
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return [];
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($absolutePath);
            $result = [];

            foreach ($pdf->getPages() as $i => $page) {
                $text = $page->getText();
                if (trim($text) !== '') {
                    $result[$i + 1] = $text;
                }
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Count pages without caring about text content.
     * Used as a fallback when extractPerPage() yields nothing (scanned/image PDFs).
     * Falls back to a fast regex count on the raw binary before trying the parser.
     */
    public function countPages(string $absolutePath): int
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return 1;
        }

        // Fast path: count /Type /Page dictionary entries in raw PDF bytes.
        // This works even for image-only PDFs and avoids a full parse.
        $raw = file_get_contents($absolutePath, false, null, 0, 65536); // first 64 KB
        if ($raw !== false) {
            // Try to read /N from the xref trailer's /Count entry
            if (preg_match_all('/\/Type\s*\/Page[^s]/', $raw, $m)) {
                $fastCount = count($m[0]);
                if ($fastCount > 0) return $fastCount;
            }
        }

        // Slower fallback: full parse
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return 1;
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($absolutePath);
            return count($pdf->getPages());
        } catch (\Throwable) {
            return 1;
        }
    }
}
