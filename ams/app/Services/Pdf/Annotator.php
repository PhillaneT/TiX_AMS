<?php

namespace App\Services\Pdf;

class Annotator
{
    private const STAMP_HALF_MM = 5.0;
    private const STAMP_SW_MM   = 0.9;

    /**
     * Import each page of $sourcePath, draw stamps, and write to $outputPath.
     * Automatically attempts a GhostScript PDF-1.4 downgrade if FPDI cannot
     * parse the source (common with PDF 1.5+ compressed cross-reference tables).
     */
    public function annotate(string $sourcePath, array $stamps, string $outputPath): void
    {
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            throw new \RuntimeException(
                'setasign/fpdi and tecnickcom/tcpdf must be installed. Run: composer install'
            );
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        // Try direct import; fall back to GhostScript downgrade for PDF 1.5+
        $gsTemp    = null;
        $importSrc = $sourcePath;
        try {
            $pageCount = $pdf->setSourceFile($importSrc);
        } catch (\Throwable $e) {
            $importSrc = $this->tryGhostscriptDowngrade($sourcePath);
            if (!$importSrc) throw $e;
            $gsTemp    = $importSrc;
            $pageCount = $pdf->setSourceFile($importSrc);
        }

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $pdf->importPage($i);
            $size  = $pdf->getTemplateSize($tplId);

            $pdf->AddPage(($size['width'] > $size['height']) ? 'L' : 'P',
                          [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            foreach (array_filter($stamps, fn($s) => (int)($s['page'] ?? 0) === $i) as $stamp) {
                $this->drawStamp(
                    $pdf,
                    (float)$stamp['x_pct'] * $size['width'],
                    (float)$stamp['y_pct'] * $size['height'],
                    $stamp['type'] ?? 'tick'
                );
            }
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $pdf->Output($outputPath, 'F');

        if ($gsTemp) @unlink($gsTemp);
    }

    // ─── Stamp drawing ────────────────────────────────────────────────────────

    private function drawStamp(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $x, float $y, string $type): void
    {
        $s  = self::STAMP_HALF_MM;
        $sw = self::STAMP_SW_MM;

        $pdf->SetDrawColor(220, 38, 38); // red — matches browser preview
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

    // ─── GhostScript helpers ──────────────────────────────────────────────────

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

    private function findGhostscript(): ?string
    {
        foreach (['gs', 'gswin64c', 'gswin32c', 'gsc'] as $bin) {
            exec(escapeshellcmd($bin) . ' --version 2>&1', $out, $code);
            if ($code === 0) return $bin;
        }
        foreach (glob('C:/Program Files/gs/gs*/bin/gswin64c.exe') ?: [] as $path) {
            if (file_exists($path)) return '"' . $path . '"';
        }
        foreach (glob('C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe') ?: [] as $path) {
            if (file_exists($path)) return '"' . $path . '"';
        }
        return null;
    }
}
