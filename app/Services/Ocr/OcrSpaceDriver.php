<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;
use Exception;

class OcrSpaceDriver implements OcrDriverInterface
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.ocr_space.key', 'helloworld'); // 'helloworld' is the free demo key
    }

    public function extract(string $imagePath): array
    {
        // OCR.space allows base64 or URL. Since we have a local file, base64 is best.
        $imageData = file_get_contents($imagePath);
        $base64 = 'data:image/png;base64,' . base64_encode($imageData);

        $response = Http::asForm()->post('https://api.ocr.space/parse/image', [
            'apikey' => $this->apiKey,
            'base64Image' => $base64,
            'language' => 'eng',
            'isOverlayRequired' => 'false',
            'detectOrientation' => 'true',
            'scale' => 'true',
            'OCREngine' => '2', // Engine 2 is often better for text on images
        ]);

        if ($response->failed()) {
            throw new Exception('OCR.space API request failed: ' . $response->body());
        }

        $result = $response->json();

        if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing'] === true) {
             throw new Exception('OCR.space API error: ' . ($result['ErrorMessage'][0] ?? 'Unknown error'));
        }

        $text = '';
        if (isset($result['ParsedResults']) && count($result['ParsedResults']) > 0) {
            $text = $result['ParsedResults'][0]['ParsedText'];
        }

        return $this->processText($text);
    }

    protected function processText(string $text): array
    {
        // Re-use similar cleaning logic for consistency
        $cleanText = preg_replace('/[^a-zA-Z0-9\s\-\:\'\!\&]/', ' ', $text);
        
        $lines = array_filter(array_map('trim', explode("\r\n", $cleanText)), function($line) {
            return strlen($line) > 2 && !preg_match('/^(only on|rated|esrb|pegi|ntsc|pal|dvd|video|game|bluray|disc)$/i', $line);
        });

        // Fallback for different newline types
        if (empty($lines)) {
             $lines = array_filter(array_map('trim', explode("\n", $cleanText)), function($line) {
                return strlen($line) > 2;
            });
        }

        $lines = array_values($lines);
        
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
