<?php

namespace App\Exports\Writers;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Central mPDF configuration for Arabic reports.
 *
 * - Embeds the Cairo font (resources/fonts, OFL) with OpenType layout so
 *   Arabic letters are joined/shaped correctly.
 * - Document direction is RTL; dates, emails and percentages should be
 *   wrapped with self::ltr() so the bidi algorithm keeps them intact.
 * - Stars use EmcStars, a 1 KB subset of DejaVu Sans holding only ★/☆
 *   (Cairo has no star glyph; the full DejaVu font costs ~80 MB of RAM).
 * - Temp/font cache lives in storage/, never in vendor/ (Hostinger safe).
 */
class PdfFactory
{
    public static function make(string $orientation = 'P', array $overrides = []): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $defaults = (new ConfigVariables())->getDefaults();
        $fonts    = (new FontVariables())->getDefaults();

        $mpdf = new Mpdf(array_merge([
            'mode'             => 'utf-8',
            'format'           => $orientation === 'L' ? 'A4-L' : 'A4',
            'tempDir'          => $tempDir,
            'fontDir'          => array_merge($defaults['fontDir'], [resource_path('fonts')]),
            'fontdata'         => $fonts['fontdata'] + [
                'cairo' => [
                    'R'          => 'Cairo-Regular.ttf',
                    'B'          => 'Cairo-Bold.ttf',
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ],
                'emcstars' => [
                    'R' => 'EmcStars.ttf',
                ],
            ],
            'default_font'     => 'cairo',
            'autoScriptToLang' => false,
            'autoLangToFont'   => false,
            'margin_top'       => 0,
            'margin_bottom'    => 12,
            'margin_left'      => 0,
            'margin_right'     => 0,
            'margin_footer'    => 5,
            'packTableData'    => true,
            'use_kwt'          => true, // keep <h3> section titles with the table that follows
        ], $overrides));

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetCreator('EMC Course Reviews');

        return $mpdf;
    }

    /**
     * Small (200px) copy of the EMC logo for PDFs. The original
     * public/assets/logo/emcLogo.png is 2048x2560 and costs ~50 MB to decode.
     */
    public static function logoPath(): ?string
    {
        $path = resource_path('images/emc-logo-pdf.png');

        return is_file($path) ? $path : null;
    }

    /** Escaped left-to-right isolate for dates, emails, numbers with symbols. */
    public static function ltr(mixed $text): string
    {
        // mPDF reorders space-separated words even inside an LTR isolate, so
        // each token is isolated on its own ("2026-10-24 17:34" stays in
        // logical order). Keep single values space-free, e.g. "3.8/5".
        $tokens = preg_split('/\s+/u', trim((string) $text)) ?: [''];

        return implode(' ', array_map(fn ($t) => '<bdi dir="ltr">' . e($t) . '</bdi>', $tokens));
    }

    /**
     * Escaped free text where numeric runs (dates, times, 100%) are isolated
     * as LTR, so mixed Arabic/English/number text keeps its logical order.
     */
    public static function text(mixed $text): string
    {
        return preg_replace(
            '/(?<![#\w])(?:\d[\d\-\/:.,]*\d%?|\d%)/u',
            '<bdi dir="ltr">$0</bdi>',
            e((string) $text)
        );
    }

    /** Horizontal progress bar (nested table: mPDF ignores sized empty divs in cells). */
    public static function bar(float|int $percent, string $color = '#2691C2'): string
    {
        $percent = max(0, min(100, (int) round($percent)));
        $cell    = 'height:8px;font-size:1px;line-height:1px;padding:0;';
        $cells   = '';
        if ($percent > 0) {
            $cells .= '<td style="' . $cell . 'width:' . $percent . '%;background-color:' . $color . ';">&nbsp;</td>';
        }
        if ($percent < 100) {
            $cells .= '<td style="' . $cell . 'background-color:#e8ecf0;">&nbsp;</td>';
        }

        return '<table width="100%" style="border-collapse:collapse;"><tr>' . $cells . '</tr></table>';
    }

    /** ★★★☆☆ rendered with a font that has the star glyphs. */
    public static function stars(float|int $value, string $color = '#EC943C'): string
    {
        $full = max(0, min(5, (int) round($value)));

        return '<span style="font-family:emcstars;color:' . $color . ';">'
            . str_repeat('★', $full)
            . '<span style="color:#cbd5e1;">' . str_repeat('★', 5 - $full) . '</span></span>';
    }
}
