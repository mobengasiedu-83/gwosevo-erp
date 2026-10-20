<?php

namespace App\Domain\Knowledge;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class DocumentTextExtractor
{
    /** @return array{text:string,status:string,note:?string} */
    public function extract(string $path, string $extension): array
    {
        $absolute = Storage::disk('local')->path($path);
        try {
            if ($extension === 'txt') return ['text' => mb_substr(file_get_contents($absolute) ?: '', 0, 2000000), 'status' => 'indexed', 'note' => null];
            if ($extension === 'docx') {
                $zip = new ZipArchive();
                if ($zip->open($absolute) !== true) return $this->unavailable('The Word document could not be opened for text extraction.');
                $xml = $zip->getFromName('word/document.xml') ?: '';
                $zip->close();
                $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</w:p>', '</w:tr>', '</w:tc>'], "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8')) ?: '';
                return ['text' => mb_substr(trim($text), 0, 2000000), 'status' => trim($text) === '' ? 'unavailable' : 'indexed', 'note' => trim($text) === '' ? 'No readable text was found in the document.' : null];
            }
            if (in_array($extension, ['pdf', 'doc'], true)) {
                $command = $extension === 'pdf' ? 'pdftotext' : 'antiword';
                $binary = (new ExecutableFinder())->find($command);
                if (! $binary) return $this->unavailable('Install '.$command.' on the server to index this file; it remains securely stored and downloadable.');
                $process = $extension === 'pdf' ? new Process([$binary, '-layout', $absolute, '-']) : new Process([$binary, $absolute]);
                $process->setTimeout(20); $process->run();
                if (! $process->isSuccessful()) return $this->unavailable('Text extraction did not complete; the file remains securely stored and downloadable.');
                return ['text' => mb_substr(trim($process->getOutput()), 0, 2000000), 'status' => 'indexed', 'note' => null];
            }
        } catch (Throwable) {
            return $this->unavailable('Text extraction failed; the file remains securely stored and downloadable.');
        }

        return $this->unavailable('This file type is stored but not indexed for text search.');
    }

    private function unavailable(string $note): array
    {
        return ['text' => '', 'status' => 'unavailable', 'note' => $note];
    }
}
