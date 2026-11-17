<?php

namespace App\Support;

use Illuminate\Support\Str;

class PdfWriter
{
    /** Lebar Helvetica untuk karakter ASCII 32..126 (per 1000 satuan). */
    private const REG = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015,
        667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611,
        278, 278, 278, 469, 556, 333,
        556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500,
        334, 260, 334, 584,
    ];

    /** Lebar Helvetica-Bold untuk karakter ASCII 32..126. */
    private const BOLD = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975,
        722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611,
        333, 278, 333, 584, 556, 333,
        556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500,
        389, 280, 389, 584,
    ];

    public float $width;
    public float $height;
    public float $margin = 36.0;

    private array $pages = [];
    private string $buf = '';
    private float $y = 0.0;
    private bool $started = false;

    public function __construct(bool $landscape = false)
    {
        $this->width = $landscape ? 841.89 : 595.28;
        $this->height = $landscape ? 595.28 : 841.89;
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->started) {
            $this->pages[] = $this->buf;
        }
        $this->buf = '';
        $this->started = true;
        $this->y = $this->height - $this->margin;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function moveDown(float $d): void
    {
        $this->y -= $d;
    }

    public function contentWidth(): float
    {
        return $this->width - 2 * $this->margin;
    }

    public function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < $this->margin + 24) {
            $this->addPage();
        }
    }

    public function clean(string $s): string
    {
        $s = Str::ascii($s);

        return (string) preg_replace('/[^\x20-\x7E]/', '', $s);
    }

    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $s = $this->clean($s);
        $table = $bold ? self::BOLD : self::REG;
        $w = 0;

        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $w += $table[ord($s[$i]) - 32] ?? 556;
        }

        return $w * $size / 1000;
    }

    public function fit(string $s, float $maxWidth, float $size, bool $bold = false): string
    {
        $s = $this->clean($s);
        if ($this->textWidth($s, $size, $bold) <= $maxWidth) {
            return $s;
        }

        while ($s !== '' && $this->textWidth($s.'...', $size, $bold) > $maxWidth) {
            $s = substr($s, 0, -1);
        }

        return $s === '' ? '' : rtrim($s).'...';
    }

    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0, 0, 0]): void
    {
        $this->buf .= $this->textOp($x, $y, $s, $size, $bold, $rgb);
    }

    public function textAligned(float $x, float $w, float $y, string $s, float $size, bool $bold, string $align = 'L'): void
    {
        $tw = $this->textWidth($s, $size, $bold);
        $px = $align === 'R' ? $x + $w - $tw : ($align === 'C' ? $x + ($w - $tw) / 2 : $x);
        $this->text($px, $y, $s, $size, $bold);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5, float $gray = 0.75): void
    {
        $this->buf .= sprintf("%s G %s w %s %s m %s %s l S\n", $this->n($gray), $this->n($width), $this->n($x1), $this->n($y1), $this->n($x2), $this->n($y2));
    }

    public function rect(float $x, float $y, float $w, float $h, float $fillGray): void
    {
        $this->buf .= sprintf("%s g %s %s %s %s re f\n", $this->n($fillGray), $this->n($x), $this->n($y), $this->n($w), $this->n($h));
    }

    public function heading(string $s, float $size = 14): void
    {
        $this->ensureSpace($size + 10);
        $this->y -= $size;
        $this->text($this->margin, $this->y, $this->fit($s, $this->contentWidth(), $size, true), $size, true);
        $this->y -= 6;
    }

    public function paragraph(string $s, float $size = 9, bool $bold = false, array $rgb = [0.3, 0.3, 0.3]): void
    {
        $this->ensureSpace($size + 6);
        $this->y -= $size + 2;
        $this->text($this->margin, $this->y, $this->fit($s, $this->contentWidth(), $size, $bold), $size, $bold, $rgb);
    }

    public function rule(): void
    {
        $this->y -= 4;
        $this->line($this->margin, $this->y, $this->margin + $this->contentWidth(), $this->y, 0.6, 0.6);
        $this->y -= 6;
    }

    public function keyValue(string $label, string $value, float $labelW = 110, float $size = 9): void
    {
        $this->ensureSpace($size + 6);
        $this->y -= $size + 3;
        $this->text($this->margin, $this->y, $label, $size, false, [0.4, 0.4, 0.4]);
        $this->text($this->margin + $labelW, $this->y, $this->fit($value, $this->contentWidth() - $labelW, $size, true), $size, true);
    }

    /**
     * @param array<int, array{label:string, w:float|int, align?:string}> $cols
     * @param array<int, array<int, string>|array{cells:array<int,string>, bold?:bool}> $rows
     */
    public function table(array $cols, array $rows, float $size = 8): void
    {
        $total = array_sum(array_column($cols, 'w'));
        $cw = $this->contentWidth();
        $widths = array_map(fn ($c) => $c['w'] / $total * $cw, $cols);
        $rowH = $size + 7;

        $this->ensureSpace($rowH * 3);
        $this->tableHeader($cols, $widths, $size, $rowH);

        foreach ($rows as $i => $row) {
            $cells = $row['cells'] ?? $row;
            $bold = (bool) ($row['bold'] ?? false);

            if ($this->y - $rowH < $this->margin + 24) {
                $this->addPage();
                $this->tableHeader($cols, $widths, $size, $rowH);
            }

            $top = $this->y;
            if ($bold) {
                $this->rect($this->margin, $top - $rowH, $cw, $rowH, 0.9);
            } elseif ($i % 2 === 1) {
                $this->rect($this->margin, $top - $rowH, $cw, $rowH, 0.965);
            }

            $x = $this->margin;
            foreach ($cols as $ci => $col) {
                $txt = $this->fit((string) ($cells[$ci] ?? ''), $widths[$ci] - 6, $size, $bold);
                $this->textAligned($x + 3, $widths[$ci] - 6, $top - $rowH + ($rowH - $size) / 2 + 1, $txt, $size, $bold, $col['align'] ?? 'L');
                $x += $widths[$ci];
            }

            $this->line($this->margin, $top - $rowH, $this->margin + $cw, $top - $rowH, 0.3, 0.88);
            $this->y -= $rowH;
        }
    }

    /** Menghasilkan isi berkas PDF sebagai string. */
    public function output(string $footerLeft = ''): string
    {
        $pages = $this->pages;
        $pages[] = $this->buf;
        $count = count($pages);

        $out = "%PDF-1.4\n";
        $offsets = [];
        $obj = function (int $id, string $body) use (&$out, &$offsets): void {
            $offsets[$id] = strlen($out);
            $out .= $id." 0 obj\n".$body."\nendobj\n";
        };

        $kids = [];
        for ($i = 0; $i < $count; $i++) {
            $kids[] = (5 + 2 * $i).' 0 R';
        }

        $obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $obj(2, '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$count.' >>');
        $obj(3, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $obj(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

        foreach ($pages as $i => $content) {
            $content .= $this->textOp($this->margin, 20, $footerLeft, 7, false, [0.5, 0.5, 0.5]);
            $label = 'Halaman '.($i + 1).' dari '.$count;
            $content .= $this->textOp($this->width - $this->margin - $this->textWidth($label, 7), 20, $label, 7, false, [0.5, 0.5, 0.5]);

            $pageId = 5 + 2 * $i;
            $contentId = 6 + 2 * $i;

            $obj($pageId, sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->n($this->width),
                $this->n($this->height),
                $contentId
            ));
            $obj($contentId, '<< /Length '.strlen($content).' >>'."\nstream\n".$content."\nendstream");
        }

        $size = 5 + 2 * $count;
        $xref = strlen($out);
        $out .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $out .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $out;
    }

    /* ------------------------------------------------------------------ */

    private function tableHeader(array $cols, array $widths, float $size, float $rowH): void
    {
        $top = $this->y;
        $this->rect($this->margin, $top - $rowH, $this->contentWidth(), $rowH, 0.84);

        $x = $this->margin;
        foreach ($cols as $ci => $col) {
            $txt = $this->fit($col['label'], $widths[$ci] - 6, $size, true);
            $this->textAligned($x + 3, $widths[$ci] - 6, $top - $rowH + ($rowH - $size) / 2 + 1, $txt, $size, true, $col['align'] ?? 'L');
            $x += $widths[$ci];
        }

        $this->y -= $rowH;
    }

    private function textOp(float $x, float $y, string $s, float $size, bool $bold, array $rgb): string
    {
        $s = $this->clean($s);
        if ($s === '') {
            return '';
        }

        $esc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);

        return sprintf(
            "BT /%s %s Tf %s %s %s rg %s %s Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1',
            $this->n($size),
            $this->n($rgb[0]), $this->n($rgb[1]), $this->n($rgb[2]),
            $this->n($x), $this->n($y),
            $esc
        );
    }

    private function n(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    }
}
