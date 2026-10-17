<?php

namespace App\Domain\Administration;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class TabularFile
{
    public function read(UploadedFile $file): array
    {
        $rows = [];
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            try {
                while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    if ($row === [null]) {
                        continue;
                    }
                    $rows[] = $row;
                    if (count($rows) > 1001) {
                        $this->fail('Maximum 1,000 data rows per import.');
                    }
                }
            } finally {
                fclose($handle);
            }
        } else {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->fail('Invalid XLSX workbook.');
            }
            try {
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $size += $zip->statIndex($i)['size'];
                }
                if ($size > 20 * 1024 * 1024) {
                    $this->fail('Expanded workbook is too large.');
                }
                $strings = [];
                if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
                    foreach ($this->xml($xml)->xpath('//*[local-name()="si"]') as $si) {
                        $strings[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
                    }
                }
                $sheetPath = 'xl/worksheets/sheet1.xml';
                if (($workbook = $zip->getFromName('xl/workbook.xml')) && ($relationships = $zip->getFromName('xl/_rels/workbook.xml.rels'))) {
                    $firstSheet = $this->xml($workbook)->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]')[0] ?? null;
                    $relationshipId = $firstSheet ? (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
                    foreach ($this->xml($relationships)->xpath('//*[local-name()="Relationship"]') as $relationship) {
                        if ((string) $relationship['Id'] !== $relationshipId) {
                            continue;
                        }
                        $target = (string) $relationship['Target'];
                        if ((string) $relationship['TargetMode'] === 'External' || ! str_ends_with((string) $relationship['Type'], '/worksheet')) {
                            $this->fail('The first sheet must be an internal worksheet.');
                        }
                        $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                        if (! preg_match('~^xl/worksheets/[A-Za-z0-9_.-]+\.xml$~', $sheetPath)) {
                            $this->fail('Unsupported worksheet path.');
                        }
                        break;
                    }
                }
                $xml = $zip->getFromName($sheetPath);
                if ($xml === false) {
                    $this->fail('The first worksheet could not be read.');
                }
                foreach ($this->xml($xml)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                    $values = [];
                    foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                        if ($cell->xpath('./*[local-name()="f"]')) {
                            $this->fail('Formulas are not supported. Upload values only.');
                        }
                        preg_match('/^([A-Z]+)[0-9]+$/', (string) $cell['r'], $matches);
                        if (! $matches) {
                            $this->fail('Invalid worksheet cell reference.');
                        }
                        $index = 0;
                        foreach (str_split($matches[1]) as $letter) {
                            $index = $index * 26 + ord($letter) - 64;
                        }
                        if ($index > 50) {
                            $this->fail('Maximum 50 columns per import.');
                        }
                        $value = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
                        $values[$index - 1] = match ((string) $cell['t']) {
                            's' => $strings[(int) $value] ?? '',
                            'inlineStr' => implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]'))),
                            default => $value,
                        };
                    }
                    if ($values) {
                        $dense = array_fill(0, max(array_keys($values)) + 1, '');
                        $rows[] = array_replace($dense, $values);
                    }
                    if (count($rows) > 1001) {
                        $this->fail('Maximum 1,000 data rows per import.');
                    }
                }
            } finally {
                $zip->close();
            }
        }
        if (count($rows) < 2) {
            $this->fail('Provide a header and at least one data row.');
        }
        $header = array_map(fn ($value) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)), array_shift($rows));
        if (count($header) > 50 || count(array_unique($header)) !== count($header) || in_array('', $header, true)) {
            $this->fail('Provide up to 50 unique, nonempty column headings.');
        }
        foreach ($rows as $row) {
            if (count($row) > count($header)) {
                $this->fail('A row has more columns than the header.');
            }
        }

        return ['header' => $header, 'rows' => $rows];
    }

    private function xml(string $text): SimpleXMLElement
    {
        if (stripos($text, '<!DOCTYPE') !== false || stripos($text, '<!ENTITY') !== false) {
            $this->fail('XML entities are not supported.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            $this->fail('Invalid worksheet XML.');
        }

        return $xml;
    }

    public function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'erp-export-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reference data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $i => $row) {
            $xml .= '<row r="'.($i + 1).'">';
            foreach (array_values($row) as $j => $value) {
                $xml .= '<c r="'.chr(65 + $j).($i + 1).'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
