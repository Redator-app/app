<?php

declare(strict_types=1);

namespace Ai\Infrastruture\Services;

use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class DocumentReader
{
    public function read(string $contents, string $ext): ?string
    {
        if ($ext === 'pdf') {
            return $this->readPdf($contents);
        }

        if (in_array($ext, ['docx', 'doc', 'odt'])) {
            return $this->readDoc($contents, $ext);
        }

        if (
            in_array($ext, ['json', 'txt'])
            || ctype_print(str_replace(["\n", "\r", "\t"], '', $contents))
        ) {
            // If the content is already text, return it as is
            return $contents;
        }

        return null;
    }

    private function readPdf(string $contents): string
    {
        $parser = new Parser();
        $pdf = $parser->parseContent($contents);
        return $pdf->getText();
    }

    private function readDoc(string $contents, string $ext): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'PHPWord');
        file_put_contents($temporaryFile, $contents);

        $type = match (true) {
            $ext == 'docx' => 'Word2007',
            $ext == 'doc' => 'MsDoc',
            $ext == 'odt' => 'ODText',
            default => 'Word2007',
        };

        $doc = IOFactory::load($temporaryFile, $type);
        $fullText = '';
        foreach ($doc->getSections() as $section) {
            $fullText .= $this->extractTextFromDocxNode($section);
        }

        unlink($temporaryFile);
        return $fullText;
    }

    private function extractTextFromDocxNode(Section|AbstractElement $section): string
    {
        $text = '';
        if (method_exists($section, 'getElements')) {
            foreach ($section->getElements() as $childSection) {
                $text = $this->concatenate($text, $this->extractTextFromDocxNode($childSection));
            }
        } elseif (method_exists($section, 'getText')) {
            $text = $this->concatenate($text, $this->toString($section->getText()));
        }

        return $text;
    }

    private function concatenate(string $text1, string $text2): string
    {
        if ($text1 === '') {
            return $text1 . $text2;
        }

        if (str_ends_with($text1, ' ')) {
            return $text1 . $text2;
        }

        if (str_starts_with($text2, ' ')) {
            return $text1 . $text2;
        }

        return $text1 . ' ' . $text2;
    }

    /**
     * @param  array<string>|string|null  $text
     */
    private function toString(array|null|string $text): string
    {
        if ($text === null) {
            return '';
        }

        if (is_array($text)) {
            return implode(' ', $text);
        }

        return $text;
    }
}
