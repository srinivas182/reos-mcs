<?php

declare(strict_types=1);

namespace App\Content\Extraction;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/**
 * Reads the words in slides and documents (A5.3) so they can be searched:
 * PDF page by page, PowerPoint slide by slide (with speaker notes), Word with
 * its headings. Images of text aren't read (no OCR yet).
 */
final class TextExtractor
{
    /** No part of a PowerPoint or Word file may unpack to more than this (zip bombs). */
    private const MAX_PART_BYTES = 25 * 1024 * 1024;

    private const DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @return list<Section> */
    public function extract(string $path, string $extension): array
    {
        return match ($extension) {
            'pdf' => $this->pdf($path),
            'pptx' => $this->pptx($path),
            'docx' => $this->docx($path),
            default => [],
        };
    }

    /** @return list<Section> */
    private function pdf(string $path): array
    {
        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        try {
            $pages = (new Parser([], $config))->parseFile($path)->getPages();
        } catch (Throwable $e) {
            throw new RuntimeException('The PDF could not be read: '.$e->getMessage(), previous: $e);
        }

        $sections = [];

        foreach (array_values($pages) as $index => $page) {
            $sections[] = new Section($index + 1, self::tidy($page->getText()));
        }

        return $sections;
    }

    /** @return list<Section> */
    private function pptx(string $path): array
    {
        $zip = $this->open($path);
        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $match) === 1) {
                $slides[(int) $match[1]] = $name;
            }
        }

        ksort($slides);
        $sections = [];
        $page = 0;

        foreach ($slides as $number => $name) {
            $page++;
            $text = $this->paragraphs($this->part($zip, $name), self::DRAWING, 'p', 't');
            $notes = $zip->locateName("ppt/notesSlides/notesSlide{$number}.xml");

            if ($notes !== false) {
                $text .= "\n".$this->paragraphs($this->part($zip, "ppt/notesSlides/notesSlide{$number}.xml"), self::DRAWING, 'p', 't');
            }

            $sections[] = new Section($page, self::tidy($text));
        }

        $zip->close();

        return $sections;
    }

    /** @return list<Section> */
    private function docx(string $path): array
    {
        $zip = $this->open($path);
        $xml = $this->part($zip, 'word/document.xml');
        $zip->close();

        $dom = self::dom($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD);
        $lines = [];

        foreach ($xpath->query('//w:body//w:p') ?: [] as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $text = '';

            foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $run) {
                $text .= $run->nodeValue ?? '';
            }

            $text = trim($text);

            if ($text === '') {
                continue;
            }

            $styles = $xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph);
            $style = $styles === false ? '' : ($styles->item(0)->nodeValue ?? '');
            $lines[] = preg_match('/^(Heading\d|Title)$/i', $style) === 1 ? '# '.$text : $text;
        }

        return [new Section(null, implode("\n", $lines))];
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file could not be opened.');
        }

        return $zip;
    }

    private function part(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            throw new RuntimeException("The file has no {$name}.");
        }

        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException('The file unpacks to more than we read.');
        }

        $content = $zip->getFromName($name);

        if ($content === false) {
            throw new RuntimeException("{$name} could not be read.");
        }

        return $content;
    }

    /** Each paragraph on its own line. */
    private function paragraphs(string $xml, string $namespace, string $paragraph, string $text): string
    {
        $dom = self::dom($xml);
        $lines = [];

        foreach ($dom->getElementsByTagNameNS($namespace, $paragraph) as $node) {
            $line = '';

            foreach ($node->getElementsByTagNameNS($namespace, $text) as $run) {
                $line .= $run->textContent;
            }

            if (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }

        return implode("\n", $lines);
    }

    private static function dom(string $xml): DOMDocument
    {
        $dom = new DOMDocument;

        // No network, no entity expansion (XXE).
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('The file is damaged.');
        }

        return $dom;
    }

    private static function tidy(string $text): string
    {
        $text = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
