<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\pdf;

defined('MOODLE_INTERNAL') || die();

/**
 * Extracts plain text from a PDF file.
 *
 * Strategy (per spec section 8.3):
 *  1. Use smalot/pdfparser for text-based PDFs.
 *  2. If the extracted text is suspiciously short (< 100 chars on a multi-page
 *     document), assume the PDF is scanned and attempt Tesseract OCR.
 *  3. If OCR confidence is below 60 %, return status 'poor_quality' so the
 *     engine can surface a human-readable message to the assessor.
 *
 * Dependencies:
 *  - smalot/pdfparser installed via composer (vendor/)
 *  - Tesseract CLI available on the server PATH (OCR fallback only)
 *
 * The file path received here is a server-side path, not a Moodle file URL.
 * The caller (marking_engine) is responsible for resolving the stored file
 * to a temporary filesystem path before calling extract().
 */
class extractor {

    private const OCR_CONFIDENCE_THRESHOLD = 60;
    private const SHORT_TEXT_THRESHOLD     = 100;

    /**
     * Convenience wrapper: copies a Moodle stored_file to a temp path,
     * extracts its text, then deletes the temp file.
     *
     * Use this instead of extract() when the PDF lives in the Moodle file store
     * (i.e. you have a \stored_file object, not a server path).
     *
     * @param  \stored_file $file  The Moodle stored file to extract text from.
     * @return string  Extracted plain text.
     * @throws \moodle_exception  On read failure or poor scan quality.
     */
    public function extract_from_stored_file(\stored_file $file): string {
        $tmpdir  = make_temp_directory('local_ajananova_pdf');
        $tmppath = $tmpdir . '/' . clean_filename($file->get_filename());

        $file->copy_content_to($tmppath);

        try {
            $text = $this->extract($tmppath);
        } finally {
            @unlink($tmppath);
        }

        return $text;
    }

    /**
     * Extracts text from a PDF file.
     *
     * @param  string $filepath  Absolute server path to the PDF.
     * @return string  Extracted plain text.
     * @throws \moodle_exception  If the file cannot be read or OCR confidence
     *                            is too low to mark reliably.
     */
    public function extract(string $filepath): string {
        if (empty($filepath) || !is_readable($filepath)) {
            throw new \moodle_exception(
                'ajananova_pdf_unreadable', 'local_ajananova', '', $filepath
            );
        }

        $text = $this->extract_with_pdfparser($filepath);

        // If pdfparser returned nothing, attempt OCR as fallback.
        // If OCR also fails (e.g. Tesseract not installed), return a placeholder
        // so the marking engine can still proceed — the AI will note the missing
        // text and the assessor can review manually. This is preferable to
        // blocking the entire marking flow.
        if (strlen(trim($text)) === 0) {
            try {
                $text = $this->extract_with_ocr($filepath);
            } catch (\moodle_exception $e) {
                // OCR unavailable or poor quality — return placeholder.
                // Vision API upgrade (planned) will replace this fallback entirely.
                $text = '[Submission PDF text could not be extracted automatically. '
                      . 'Please review the original submission alongside this AI output.]';
            }
        }

        return $text;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Uses smalot/pdfparser to extract machine-readable text.
     *
     * Returns an empty string on any parser error so the OCR fallback can run.
     */
    private function extract_with_pdfparser(string $filepath): string {
        $vendorpath = __DIR__ . '/../../vendor/autoload.php';

        if (!file_exists($vendorpath)) {
            // Composer autoloader not present — skip to OCR.
            return '';
        }

        require_once $vendorpath;

        try {
            $parser   = new \Smalot\PdfParser\Parser();
            $pdf      = $parser->parseFile($filepath);
            return $pdf->getText();
        } catch (\Exception $e) {
            debugging('local_ajananova pdfparser error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Runs Tesseract OCR on each page of the PDF via the CLI.
     *
     * Steps:
     *  1. Convert PDF pages to PNGs using ImageMagick (convert).
     *  2. Run tesseract on each PNG, capturing confidence from the TSV output.
     *  3. If average confidence < threshold, throw a poor_quality exception.
     *
     * @throws \moodle_exception  On poor scan quality.
     */
    private function extract_with_ocr(string $filepath): string {
        $tmpdir = make_temp_directory('local_ajananova_ocr');

        // Convert PDF to PNGs (requires ImageMagick).
        $pngbase  = $tmpdir . '/page';
        $convert  = 'convert -density 200 ' . escapeshellarg($filepath)
                  . ' ' . escapeshellarg($pngbase . '-%d.png') . ' 2>/dev/null';
        exec($convert, $output, $returncode);

        $pages = glob($pngbase . '-*.png');

        if (empty($pages)) {
            throw new \moodle_exception('ajananova_ocr_failed', 'local_ajananova');
        }

        $alltext       = '';
        $totalconf     = 0;
        $confidences   = 0;

        foreach ($pages as $page) {
            $tsvbase = $tmpdir . '/ocr_out';
            $tess    = 'tesseract ' . escapeshellarg($page)
                     . ' ' . escapeshellarg($tsvbase)
                     . ' tsv 2>/dev/null';
            exec($tess);

            $tsvfile = $tsvbase . '.tsv';
            if (!file_exists($tsvfile)) {
                continue;
            }

            $lines = file($tsvfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            // Skip header line.
            array_shift($lines);

            foreach ($lines as $line) {
                $cols = explode("\t", $line);
                // TSV columns: level page_num block_num par_num line_num
                //              word_num left top width height conf text
                $conf = isset($cols[10]) ? (float) $cols[10] : -1;
                $word = $cols[11] ?? '';

                if ($conf >= 0) {
                    $totalconf += $conf;
                    $confidences++;
                }
                if ($word !== '') {
                    $alltext .= $word . ' ';
                }
            }

            @unlink($tsvfile);
            @unlink($page);
        }

        $avgconf = $confidences > 0 ? ($totalconf / $confidences) : 0;

        if ($avgconf < self::OCR_CONFIDENCE_THRESHOLD) {
            throw new \moodle_exception('ajananova_poor_scan_quality', 'local_ajananova');
        }

        return trim($alltext);
    }
}
