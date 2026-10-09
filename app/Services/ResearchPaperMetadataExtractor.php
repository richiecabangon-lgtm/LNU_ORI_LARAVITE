<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class ResearchPaperMetadataExtractor
{
    /**
     * @return array{fields: array<string, string|array<int, string>>, extracted_fields: list<string>}
     */
    public function extract(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();

        if ($mimeType === 'application/pdf') {
            $text = $this->extractPdfText($file->getPathname());
        } elseif ($mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $text = $this->extractDocxText($file->getPathname());
        } elseif (str_starts_with((string) $mimeType, 'image/')) {
            $text = $this->extractImageText($file->getPathname());
        } else {
            throw new RuntimeException('Upload a PDF, DOCX, or image file to extract research details.');
        }

        $metadata = $this->parseText($text);

        if ($metadata['fields'] === []) {
            throw new RuntimeException('No research details could be identified. Enter the details manually.');
        }

        return $metadata;
    }

    /**
     * @return array{fields: array<string, string|array<int, string>>, extracted_fields: list<string>}
     */
    public function parseText(string $text): array
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
        $lines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line))
            ->filter()
            ->values();
        $fields = [];

        $title = $this->extractTitle($lines->all());
        if ($title !== null) {
            $fields['title'] = $title;
        }

        $authors = $this->extractAuthors($lines->all(), $title);
        if ($authors !== []) {
            $fields['authors'] = $authors;
        }

        $abstract = $this->extractAbstract($text);
        if ($abstract !== null) {
            $fields['abstract'] = $abstract;
        }

        if (preg_match('/\b(10\.\d{4,9}\/[\-._;()\/:A-Z0-9]+)\b/i', $text, $matches) === 1) {
            $fields['doi'] = rtrim($matches[1], '.,;:)');
        }

        if (preg_match('/\b(?:publication date|published)\s*:?\s*(\d{4}-\d{2}-\d{2})\b/i', $text, $matches) === 1) {
            $fields['publication_date'] = $matches[1];
        }

        return [
            'fields' => $fields,
            'extracted_fields' => array_keys($fields),
        ];
    }

    private function extractPdfText(string $path): string
    {
        $ghostscript = (new ExecutableFinder)->find('gs');
        if ($ghostscript === null) {
            throw new RuntimeException('PDF extraction is unavailable on this server. Install Ghostscript or enter the details manually.');
        }

        $directory = $this->makeTemporaryDirectory();

        try {
            $textPath = $directory.'/paper.txt';
            $textProcess = new Process([
                $ghostscript,
                '-q',
                '-sDEVICE=txtwrite',
                '-dFirstPage=1',
                '-dLastPage=5',
                '-dBATCH',
                '-dNOPAUSE',
                '-sOutputFile='.$textPath,
                $path,
            ]);
            $textProcess->setTimeout(25);
            $textProcess->run();
            $text = is_file($textPath) ? (string) file_get_contents($textPath) : '';

            if (mb_strlen(trim($text)) >= 120) {
                return mb_substr($text, 0, 50000);
            }

            $pattern = $directory.'/page-%03d.png';
            $renderProcess = new Process([
                $ghostscript,
                '-q',
                '-sDEVICE=pnggray',
                '-r180',
                '-dFirstPage=1',
                '-dLastPage=5',
                '-dBATCH',
                '-dNOPAUSE',
                '-sOutputFile='.$pattern,
                $path,
            ]);
            $renderProcess->setTimeout(35);
            $renderProcess->run();

            if (! $renderProcess->isSuccessful()) {
                throw new RuntimeException('The PDF could not be read. Try a text-based PDF or a clear image scan.');
            }

            $images = glob($directory.'/page-*.png') ?: [];
            if ($images === []) {
                throw new RuntimeException('No readable pages were found in the PDF.');
            }

            $text = '';
            foreach ($images as $image) {
                $text .= $this->runTesseract($image)."\n";
            }

            return mb_substr($text, 0, 50000);
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    private function extractImageText(string $path): string
    {
        return mb_substr($this->runTesseract($path), 0, 50000);
    }

    private function extractDocxText(string $path): string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The DOCX file could not be opened.');
        }

        try {
            $document = $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
        }

        if (! is_string($document)) {
            throw new RuntimeException('The DOCX file does not contain readable document text.');
        }

        $xml = new DOMDocument;
        if (! $xml->loadXML($document, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('The DOCX document text could not be read.');
        }

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = $xpath->query('//w:p');

        if ($paragraphs === false) {
            return '';
        }

        $text = [];
        foreach ($paragraphs as $paragraph) {
            $paragraphText = trim($paragraph->textContent);
            if ($paragraphText !== '') {
                $text[] = $paragraphText;
            }
        }

        return implode("\n", $text);
    }

    private function runTesseract(string $path): string
    {
        $tesseract = (new ExecutableFinder)->find('tesseract');
        if ($tesseract === null) {
            throw new RuntimeException('Image OCR is unavailable on this server. Install Tesseract or enter the details manually.');
        }

        $process = new Process([$tesseract, $path, 'stdout', '-l', 'eng', '--psm', '3']);
        $process->setTimeout(25);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('The paper image could not be read. Try a clearer image or enter the details manually.');
        }

        return $process->getOutput();
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractTitle(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 20) as $line) {
            if (preg_match('/^(?:research\s+)?title\s*:\s*(.+)$/i', $line, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        $authorLabelIndex = null;
        foreach (array_slice($lines, 0, 20, true) as $index => $line) {
            if (preg_match('/^(abstract|keywords?|introduction|references|acknowledg(?:e)?ments?)\b/i', $line) === 1) {
                break;
            }

            if (preg_match('/^authors?\s*:?/i', $line) === 1) {
                $authorLabelIndex = $index;
                break;
            }
        }

        if ($authorLabelIndex !== null) {
            for ($index = $authorLabelIndex - 1; $index >= 0; $index--) {
                $line = $lines[$index];
                if ($this->isMastheadLine($line)) {
                    continue;
                }

                if (mb_strlen($line) >= 8 && mb_strlen($line) <= 500) {
                    return $line;
                }
            }
        }

        foreach (array_slice($lines, 0, 12) as $line) {
            if (mb_strlen($line) < 8 || mb_strlen($line) > 500) {
                continue;
            }

            if ($this->isMastheadLine($line)
                || preg_match('/^(abstract|keywords?|doi|issn|isbn|authors?|journal of|vol\.?\s*\d|received|published)\b/i', $line) === 1) {
                continue;
            }

            return $line;
        }

        return null;
    }

    private function isMastheadLine(string $line): bool
    {
        return preg_match('/^(?:leyte normal university|office of research|office of research and innovation|university of|college of|department of|faculty of|school of|institute of|tacloban city|philippines|https?:\/\/|www\.)\b/i', $line) === 1
            || preg_match('/^(?:email|telephone|phone|tel\.?|website)\s*:/i', $line) === 1;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function extractAuthors(array $lines, ?string $title): array
    {
        if ($title === null) {
            return [];
        }

        $titleIndex = null;
        foreach ($lines as $index => $line) {
            $normalizedLine = trim(preg_replace('/^(?:research\s+)?title\s*:\s*/i', '', $line) ?? $line);
            if (mb_strtolower($normalizedLine) === mb_strtolower($title)) {
                $titleIndex = $index;
                break;
            }
        }

        if ($titleIndex === null) {
            return [];
        }

        $authors = [];
        foreach (array_slice($lines, $titleIndex + 1, 8) as $line) {
            if (preg_match('/^(abstract|keywords?|introduction|references|acknowledg(?:e)?ments?)\b/i', $line) === 1
                || preg_match('/@|\b(?:university|department|faculty|school|college|institute|corresponding author|received|published|\d{4})\b/i', $line) === 1) {
                break;
            }

            if (preg_match('/^authors?\s*:\s*(.+)$/i', $line, $matches) === 1) {
                return $this->splitAuthorLine($matches[1]);
            }

            if (preg_match('/^authors?\s*:?$/i', $line) === 1) {
                continue;
            }

            if (! $this->isLikelyAuthorLine($line, $title)) {
                if ($authors !== []) {
                    break;
                }

                continue;
            }

            array_push($authors, ...$this->splitAuthorLine($line));
        }

        return array_values(array_unique($authors));
    }

    private function isLikelyAuthorLine(string $line, ?string $title): bool
    {
        return mb_strlen($line) <= 240
            && preg_match('/[A-Za-z]{2,}/', $line) === 1
            && mb_strtolower($line) !== mb_strtolower((string) $title)
            && preg_match('/@|university|department|faculty|school|\b(19|20)\d{2}\b|^abstract\b|^keywords?\b/i', $line) !== 1;
    }

    /**
     * @return list<string>
     */
    private function splitAuthorLine(string $line): array
    {
        $line = trim(preg_replace('/^authors?\s*:\s*/i', '', $line) ?? $line);
        $line = preg_replace('/(?<=[\p{L}])\d{1,2}\b/u', '', $line) ?? $line;
        $line = preg_replace('/[⁰¹²³⁴⁵⁶⁷⁸⁹]+/u', '', $line) ?? $line;
        $authors = preg_split('/\s*;\s*|\s+and\s+|\s*&\s*/i', $line) ?: [];

        if (count($authors) === 1) {
            $commaSeparatedAuthors = preg_split('/\s*,\s*/', $line) ?: [];
            $hasFullNameInEverySegment = count($commaSeparatedAuthors) > 1
                && collect($commaSeparatedAuthors)->every(fn (string $author): bool => preg_match('/[\p{L}]{2,}.*\s+[\p{L}.\'-]{1,}/u', trim($author)) === 1);

            if ($hasFullNameInEverySegment) {
                $authors = $commaSeparatedAuthors;
            }
        }

        return array_values(array_filter(array_map(
            fn (string $author): string => trim($author, " \t\n\r\0\x0B,*"),
            $authors,
        ), fn (string $author): bool => mb_strlen($author) >= 3 && mb_strlen($author) <= 255));
    }

    private function extractAbstract(string $text): ?string
    {
        if (preg_match('/(?:^|\R)\s*abstract\s*[:.]?\s*(.*?)(?=\R\s*(?:keywords?|index terms|\d+\.?\s+introduction|introduction)\b|\z)/is', $text, $matches) !== 1) {
            return null;
        }

        $abstract = trim(preg_replace('/\s+/u', ' ', $matches[1]) ?? $matches[1]);

        return mb_strlen($abstract) >= 40 ? mb_substr($abstract, 0, 5000) : null;
    }

    private function makeTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/ori-paper-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Temporary paper processing could not be initialized.');
        }

        return $directory;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
