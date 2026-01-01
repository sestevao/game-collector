<?php

namespace App\Services\Ocr;

use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractDriver implements OcrDriverInterface
{
    public function extract(string $imagePath): array
    {
        $ocr = new TesseractOCR($imagePath);
        $ocr->allowlist(range('a', 'z'), range('A', 'Z'), range(0, 9), '-:!&\'" .');
        $ocr->psm(6); // Page segmentation mode 6: Assume a single uniform block of text.

        $text = $ocr->run();
        
        return $this->processText($text);
    }

    protected function processText(string $text): array
    {
        // Post-processing
        $cleanText = preg_replace('/[^a-zA-Z0-9\s\-\:\'\!\&]/', ' ', $text);
        
        $lines = array_filter(array_map('trim', explode("\n", $cleanText)), function($line) {
            // Allow slightly shorter words but remove noise
            return strlen($line) > 2 && !preg_match('/^(only on|rated|esrb|pegi|ntsc|pal|dvd|video|game|bluray|disc)$/i', $line);
        });

        // Re-index array
        $lines = array_values($lines);
        
        // Return structured data
        $structuredLines = [];
        foreach ($lines as $index => $line) {
            $structuredLines[] = [
                'id' => $index,
                'text' => $line
            ];
        }

        return $structuredLines;
    }
}
