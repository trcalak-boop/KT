# API: ts-pdf-php 1.0.0 (tFPDF 1.34, LGPL)

```php
define('FPDF_FONTPATH', __DIR__ . '/../private/fonts/');     // obsahuje unifont/<písmo>.ttf, složka unifont zapisovatelná
require 'vendor/ts/ts-pdf-php/core/ttfonts.php';
require 'vendor/ts/ts-pdf-php/core/tfpdf.php';
$pdf = new tFPDF(); $pdf->AddPage();
$pdf->AddFont('Hanken', '', 'HankenGrotesk-Regular.ttf', true);   // true = UTF-8
$pdf->SetFont('Hanken', '', 12); $pdf->Write(6, 'Příliš žluťoučký kůň');
$pdf->Output('D', 'dokument.pdf');
```
Kompletní API viz dokumentace FPDF/tFPDF (Cell, MultiCell, Image, Line…). Knihovnu neupravovat (LGPL).
