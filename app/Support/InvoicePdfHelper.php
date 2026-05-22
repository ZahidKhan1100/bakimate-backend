<?php

namespace App\Support;

use NumberFormatter;

/**
 * Formatting helpers for formal SO-style PDFs (DomPDF).
 */
final class InvoicePdfHelper
{
    /**
     * E.g. "RINGGIT SIXTY FIVE AND CENTS SIXTY ONLY" — common trade-document style.
     * Empty string when intl extension is missing.
     */
    public static function amountSenToWordsUpper(int $sen): string
    {
        if (! extension_loaded('intl')) {
            return '';
        }

        $ringgit = intdiv(max(0, $sen), 100);
        $cents = max(0, $sen) % 100;

        $fmt = new NumberFormatter('en_US', NumberFormatter::SPELLOUT);
        $r = strtoupper(str_replace('-', ' ', (string) $fmt->format($ringgit)));
        $c = strtoupper(str_replace('-', ' ', (string) $fmt->format($cents)));

        return "RINGGIT {$r} AND CENTS {$c} ONLY";
    }

    /** d/m/Y for customer-facing docs (matches common local invoice scans). */
    public static function docDateDmY(\DateTimeInterface $at): string
    {
        return $at->format('d/m/Y');
    }
}
