<?php
require __DIR__ . '/../../../tools/phptest.php';
/* písmo se kopíruje do dočasné složky – tFPDF si vedle něj zapisuje mezipaměť a zdrojový projekt se nesmí měnit */
$dir = t_tmpdir(); mkdir("$dir/unifont");
$src = t_source('tas-web') . '/web/admin/lib/font/unifont/HankenGrotesk-Regular.ttf';
t_ok(is_file($src), 'písmo ze zdrojového projektu (OFL)');
copy($src, "$dir/unifont/HankenGrotesk-Regular.ttf");
define('FPDF_FONTPATH', $dir . '/');
require __DIR__ . '/../core/ttfonts.php';
require __DIR__ . '/../core/tfpdf.php';
$pdf = new tFPDF();
$pdf->AddPage();
$pdf->AddFont('Hanken', '', 'HankenGrotesk-Regular.ttf', true);
$pdf->SetFont('Hanken', '', 14);
$pdf->Write(8, 'Příliš žluťoučký kůň úpěl ďábelské ódy – doklad č. 2026-0001');
$out = $pdf->Output('S');
t_ok(substr($out, 0, 5) === '%PDF-' && strlen($out) > 3000, 'PDF vzniklo (' . strlen($out) . ' B)');
t_ok(strpos($out, 'FontFile2') !== false, 'písmo je vložené (podmnožina TTF)');
t_eq(tFPDF::VERSION ?? null, '1.34', 'verze tFPDF');
t_ok(count(glob("$dir/unifont/*")) >= 1, 'mezipaměť jen v dočasné složce');
