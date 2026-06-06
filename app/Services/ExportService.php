<?php
declare(strict_types=1);

namespace App\Services;

/**
 * CSV + SpreadsheetML 2003 (.xls) export — no external deps.
 */
final class ExportService
{
    public static function csv(array $rows, array $headers, string $filename = 'export.csv'): void
    {
        $fp = fopen('php://temp', 'w+');
        // BOM for Excel compatibility with UTF-8
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $key = self::keyFromHeader($h);
                $val = $row[$key] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $line[] = (string)$val;
            }
            fputcsv($fp, $line);
        }
        rewind($fp);
        $body = stream_get_contents($fp);
        fclose($fp);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"','',$filename) . '"');
        header('Cache-Control: no-store');
        echo $body;
        exit;
    }

    public static function excel(array $rows, array $headers, string $filename = 'export.xls'): void
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' .
                ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Styles><Style ss:ID="hdr"><Font ss:Bold="1"/>' .
                '<Interior ss:Color="#1F2937" ss:Pattern="Solid"/>' .
                '<Font ss:Color="#FFFFFF" ss:Bold="1"/></Style></Styles>' . "\n";
        $xml .= '<Worksheet ss:Name="Export"><Table>' . "\n";

        $xml .= '<Row>';
        foreach ($headers as $h) {
            $xml .= '<Cell ss:StyleID="hdr"><Data ss:Type="String">' . self::xmlEscape($h) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($headers as $h) {
                $key = self::keyFromHeader($h);
                $val = $row[$key] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $val = (string)$val;
                $type = is_numeric($val) && !preg_match('/^0[0-9]+$/', $val) ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . self::xmlEscape($val) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"','',$filename) . '"');
        header('Cache-Control: no-store');
        echo $xml;
        exit;
    }

    private static function keyFromHeader(string $header): string
    {
        return strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $header));
    }

    private static function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
