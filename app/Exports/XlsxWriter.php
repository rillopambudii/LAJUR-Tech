<?php

namespace App\Exports;

use RuntimeException;
use ZipArchive;

/**
 * Penulis .xlsx minimalis tanpa dependensi: file xlsx = arsip zip berisi XML
 * (SpreadsheetML). Cukup untuk tabel datar — string inline, sel numerik, dan
 * baris judul tebal. Sengaja tidak memakai phpspreadsheet yang berat.
 */
class XlsxWriter
{
    /**
     * Tulis satu sheet ke file sementara; kembalikan path-nya.
     *
     * @param list<string> $headings
     * @param iterable<int, array<int, mixed>> $rows
     */
    public function write(array $headings, iterable $rows, string $sheetName = 'Data'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk xlsx.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuka arsip xlsx.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($headings, $rows));

        $zip->close();

        return $path;
    }

    /**
     * @param list<string> $headings
     * @param iterable<int, array<int, mixed>> $rows
     */
    private function sheetXml(array $headings, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $xml .= $this->rowXml(1, $headings, headerStyle: true);

        $r = 2;
        foreach ($rows as $row) {
            $xml .= $this->rowXml($r++, $row);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /** @param array<int, mixed> $cells */
    private function rowXml(int $r, array $cells, bool $headerStyle = false): string
    {
        $xml = '<row r="'.$r.'">';
        $c = 0;
        foreach (array_values($cells) as $value) {
            $ref = $this->columnRef($c++).$r;
            $style = $headerStyle ? ' s="1"' : '';

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
            } else {
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    /** 0 → A, 25 → Z, 26 → AA, dst. */
    private function columnRef(int $index): string
    {
        $ref = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $ref = chr(65 + $mod).$ref;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $ref;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        $name = htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        // Dua gaya sel: 0 = normal, 1 = tebal (baris judul). Struktur fills/borders
        // minimum yang dianggap valid oleh Excel.
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf xfId="0"/><xf fontId="1" applyFont="1" xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }
}
