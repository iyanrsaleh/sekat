<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ekspor data ke CSV atau XLSX.
 *
 *   Exporter::csv($rows, $headers)
 *   Exporter::xlsx($rows, $headers)
 *
 * $headers: array key => label (kolom yang diekspor, urutan sesuai key).
 */
final class Exporter
{
    public static function csv(array $rows, array $headers): string
    {
        $output = "\xEF\xBB\xBF"; // BOM agar Excel mengenali UTF-8
        $output .= self::csvRow(array_values($headers));

        foreach ($rows as $row) {
            $output .= self::csvRow(self::pick($row, array_keys($headers)));
        }

        return $output;
    }

    /**
     * Menghasilkan .xlsx minimal (zip) memakai ZipArchive.
     */
    public static function xlsx(array $rows, array $headers): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi zip diperlukan untuk format xlsx; gunakan format=csv.');
        }

        $columns = array_keys($headers);
        $labels = array_values($headers);

        $xml = self::sheetXml($rows, $columns, $labels);

        $zip = new \ZipArchive();
        $file = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak bisa membuat file xlsx.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        $data = (string) file_get_contents($file);
        unlink($file);

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function pick(array $row, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[] = $row[$key] ?? null;
        }

        return $values;
    }

    private static function csvRow(array $fields): string
    {
        $escaped = array_map(static function ($value): string {
            $value = (string) $value;
            if (strpbrk($value, "\",\n\r") !== false) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }

    private static function sheetXml(array $rows, array $columns, array $labels): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        // baris header
        $xml .= '<row r="1">';
        foreach ($labels as $index => $label) {
            $xml .= self::cell(1, $index + 1, (string) $label, true);
        }
        $xml .= '</row>';

        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 2) . '">';
            foreach ($columns as $colIndex => $key) {
                $xml .= self::cell($rowIndex + 2, $colIndex + 1, (string) ($row[$key] ?? ''));
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private static function cell(int $row, int $col, string $value, bool $bold = false): string
    {
        $ref = self::columnName($col) . $row;
        $style = $bold ? ' s="1"' : '';

        return '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
            . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '</t></is></c>';
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - 1, 26);
        }
        return $name;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}