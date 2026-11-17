<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class XlsxWriter
{
    public const S_NORMAL = 0;
    public const S_HEADER = 1;
    public const S_MONEY = 2;
    public const S_TITLE = 3;
    public const S_BOLD = 4;
    public const S_BOLD_MONEY = 5;
    public const S_PERCENT = 6;
    public const S_BOLD_PERCENT = 7;

    private array $sheets = [];

    /**
     * @param array<int, array<int, mixed>> $rows   sel: nilai skalar atau [nilai, gaya]
     * @param array<int, float|int>         $widths lebar kolom
     */
    public function addSheet(string $name, array $rows, array $widths = []): static
    {
        $clean = trim(mb_substr((string) preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name), 0, 31));

        $this->sheets[] = [
            'name' => $clean !== '' ? $clean : 'Sheet'.(count($this->sheets) + 1),
            'rows' => $rows,
            'widths' => $widths,
        ];

        return $this;
    }

    /** Menyimpan ke berkas sementara dan mengembalikan path-nya. */
    public function save(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP "zip" belum aktif. Aktifkan extension=zip di php.ini lalu restart server.');
        }
        if (!$this->sheets) {
            $this->addSheet('Sheet1', []);
        }

        $path = tempnam(sys_get_temp_dir(), 'hbx');
        if ($path === false) {
            throw new RuntimeException('Gagal membuat berkas sementara untuk export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Gagal membuat berkas Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->sheetXml($sheet['rows'], $sheet['widths']));
        }

        if ($zip->close() !== true) {
            @unlink($path);
            throw new RuntimeException('Gagal menulis berkas Excel.');
        }

        return $path;
    }

    /* ------------------------------------------------------------------ */

    private const HEAD = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    private function contentTypes(): string
    {
        $xml = self::HEAD.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach (array_keys($this->sheets) as $i) {
            $xml .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml.'</Types>';
    }

    private function rootRels(): string
    {
        return self::HEAD.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $xml = self::HEAD.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<sheet name="'.$this->esc($sheet['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return $xml.'</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $xml = self::HEAD.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_keys($this->sheets) as $i) {
            $xml .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }

        $xml .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return $xml.'</Relationships>';
    }

    private function styles(): string
    {
        $border = '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right>'
            .'<top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>';

        return self::HEAD.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'.$border.'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="10" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function sheetXml(array $rows, array $widths): string
    {
        $xml = self::HEAD.'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($widths) {
            $xml .= '<cols>';
            foreach (array_values($widths) as $i => $w) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach (array_values($rows) as $ri => $row) {
            $xml .= '<row r="'.($ri + 1).'">';

            foreach (array_values($row) as $ci => $cell) {
                $style = 0;
                $value = $cell;
                if (is_array($cell)) {
                    $value = $cell[0] ?? null;
                    $style = (int) ($cell[1] ?? 0);
                }
                if ($value === null || $value === '') {
                    continue;
                }

                $ref = $this->col($ci).($ri + 1);

                if (is_int($value) || is_float($value)) {
                    if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                        continue;
                    }
                    $num = is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"><v>'.$num.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->esc((string) $value).'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function col(int $index): string
    {
        $letters = '';
        $n = $index + 1;

        while ($n > 0) {
            $m = ($n - 1) % 26;
            $letters = chr(65 + $m).$letters;
            $n = intdiv($n - 1, 26);
        }

        return $letters;
    }

    private function esc(string $s): string
    {
        $s = mb_scrub($s, 'UTF-8');
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
