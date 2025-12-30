<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AmazonService
{
    public function getPrice($title, $platform = null)
    {
        try {
            // Sanitize title
            $searchTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
            
            Log::info("AmazonService: Searching for '{$searchTitle}' on '{$platform}' using Puppeteer");

            $scriptPath = base_path('app/Scripts/fetch_amazon_price.js');
            $cmdTitle = escapeshellarg($searchTitle);
            $cmdPlatform = escapeshellarg($platform ?? '');
            
            $command = "node {$scriptPath} {$cmdTitle} {$cmdPlatform}";
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                $jsonStr = implode("\n", $output);
                $result = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($result['price'])) {
                     Log::info("AmazonService: Found match: '{$result['title']}' - £{$result['price']}");
                     return (float) $result['price'];
                } else {
                     Log::warning("AmazonService: No valid price returned. Output: " . substr($jsonStr, 0, 200));
                }
            } else {
                 Log::warning("AmazonService: Node script failed. Return code: {$returnVar}. Output: " . implode("\n", $output));
            }
            
        } catch (\Exception $e) {
            Log::error("AmazonService: Exception: " . $e->getMessage());
        }

        return null;
    }
}
