<?php

namespace App\Services\Pdf;

class TextExtractor
{
    /**
     * Extract text per page from a PDF file.
     * Returns [pageNumber => textString]. Empty array if the file can't be parsed
     * (non-PDF, lib not installed, encrypted, etc.).
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
}
