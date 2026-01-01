<?php

namespace App\Services\Ocr;

use InvalidArgumentException;

class OcrManager
{
    public function driver(string $driverName = null): OcrDriverInterface
    {
        $driverName = $driverName ?? config('services.ocr.default', 'tesseract');

        return match ($driverName) {
            'tesseract' => new TesseractDriver(),
            'ocr_space' => new OcrSpaceDriver(),
            default => throw new InvalidArgumentException("OCR driver [{$driverName}] is not supported."),
        };
    }
}
