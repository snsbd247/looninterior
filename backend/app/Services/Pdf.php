<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * PDF documents (quotation, invoice, receipt, PO…) in English or Bangla.
 * mPDF is used because it shapes Bangla conjuncts correctly (OTL). Font: Hind Siliguri
 * (resources/fonts, SIL OFL), which also covers Latin, so both languages share one look.
 */
class Pdf
{
    public static function render(string $view, array $data, string $lang = 'en', string $title = 'Document'): string
    {
        $lang = $lang === 'bn' ? 'bn' : 'en';
        $html = view($view, $data + ['lang' => $lang])->render();

        $tmp = storage_path('app/mpdf-tmp');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $config = (new ConfigVariables())->getDefaults();
        $fonts = (new FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 18,
            'tempDir' => $tmp,
            'fontDir' => array_merge($config['fontDir'], [resource_path('fonts')]),
            'fontdata' => $fonts['fontdata'] + [
                'hindsiliguri' => [
                    'R' => 'HindSiliguri-Regular.ttf',
                    'B' => 'HindSiliguri-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'hindsiliguri',
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** Absolute file path for an uploaded image setting (logo, signature), or null. */
    public static function imagePath(?string $stored): ?string
    {
        if (! $stored || preg_match('#^(https?:)?//#i', $stored) || str_contains($stored, '..')) {
            return null;
        }
        $path = public_path(ltrim($stored, '/'));

        return is_file($path) ? $path : null;
    }
}
