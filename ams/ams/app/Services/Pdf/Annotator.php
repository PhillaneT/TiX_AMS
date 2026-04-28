<?php

namespace App\Services\Pdf;

class Annotator
{
    private const STAMP_RADIUS_MM  = 4.5;
    private const STAMP_FONT_SIZE  = 11;
    private const GREEN            = [22, 163, 74];
    private const RED              = [220, 38, 38];

    /**
     * Import each page of $sourcePath, draw the assessor's stamps, lock the result,
     * and write the final PDF to $outputPath.
     *
     * @param  string $sourcePath   Absolute path to the original submission PDF
     * @param  array  $stamps       Annotation stamps from annotations_json
     * @param  string $outputPath   Absolute path where the annotated PDF will be written
     * @param  string $ownerPass    Owner password (blocks editing/copying)
     * @throws \RuntimeException    If required libraries are not installed
     */
    public function annotate(string $sourcePath, array $stamps, string $outputPath, string $ownerPass = ''): void
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
        $pdf->SetFont('dejavusans', 'B', self::STAMP_FONT_SIZE);

        $pageCount = $pdf->setSourceFile($sourcePath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $pdf->importPage($i);
            $size  = $pdf->getTemplateSize($tplId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            $pageStamps = array_filter($stamps, fn($s) => (int)($s['page'] ?? 0) === $i);
            foreach ($pageStamps as $stamp) {
                $x = (float)$stamp['x_pct'] * $size['width'];
                $y = (float)$stamp['y_pct'] * $size['height'];
                $this->drawStamp($pdf, $x, $y, $stamp['type']);
            }
        }

        // Lock the PDF: allow printing only; owner password blocks editing/copy/annotate
        $ownerPass = $ownerPass ?: bin2hex(random_bytes(16));
        $pdf->SetProtection(['print'], '', $ownerPass, 128);

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->Output($outputPath, 'F');
    }

    private function drawStamp(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $x, float $y, string $type): void
    {
        $r     = self::STAMP_RADIUS_MM;
        $color = $type === 'tick' ? self::GREEN : self::RED;

        // Filled circle
        $pdf->SetFillColorArray($color);
        $pdf->Circle($x, $y, $r, 0, 360, 'F');

        // White border
        $pdf->SetDrawColor(255, 255, 255);
        $pdf->SetLineWidth(0.4);
        $pdf->Circle($x, $y, $r, 0, 360, 'D');

        // Symbol centred on the circle
        $symbol = $type === 'tick' ? "\xE2\x9C\x93" : "\xE2\x9C\x97"; // ✓ or ✗ (UTF-8)
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', self::STAMP_FONT_SIZE);
        $diameter = $r * 2;
        $pdf->SetXY($x - $r, $y - $r);
        $pdf->Cell($diameter, $diameter, $symbol, 0, 0, 'C');

        // Reset colours so subsequent page content is unaffected
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
    }
}
