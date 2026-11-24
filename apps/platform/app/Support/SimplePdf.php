<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A plain text PDF, page by page (Helvetica, US Letter), for samples and
 * Simulators: the sample TREC contract (A12.2) and DocuSign's demo files.
 * Long lines wrap; text is ASCII.
 */
final class SimplePdf
{
    /** @param  list<string>  $pages  each page's text, lines separated by "\n" */
    public static function pages(array $pages, int $size = 10): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', ''];
        $kids = [];
        $font = 3 + count($pages) * 2;

        foreach ($pages as $i => $text) {
            $page = 3 + $i * 2;
            $kids[] = "{$page} 0 R";
            $lines = [];

            foreach (explode("\n", $text) as $line) {
                foreach (explode("\n", wordwrap(Str::ascii($line), 95, "\n", true)) as $wrapped) {
                    $lines[] = '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $wrapped).') Tj T*';
                }
            }

            $leading = $size + 4;
            $stream = "BT /F1 {$size} Tf {$leading} TL 54 744 Td ".implode(' ', $lines).' ET';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.($page + 1).' 0 R /Resources << /Font << /F1 '.$font.' 0 R >> >> >>';
            $objects[] = '<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream";
        }

        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
