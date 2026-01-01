<?php

namespace App\Services\Ocr;

interface OcrDriverInterface
{
    /**
     * Extract text from an image file.
     *
     * @param string $imagePath
     * @return array Array of structured lines [['id' => 0, 'text' => '...']]
     */
    public function extract(string $imagePath): array;
}
