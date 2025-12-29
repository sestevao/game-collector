<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CexService
{
    // Using the internal API endpoint identified for UK store
    protected $baseUrl = 'https://wss2.cex.uk.webuy.io/v3/boxes';

    public function getPrice($title, $platform = null)
    {
        try {
            $searchQuery = $title;
            // Append platform to query to narrow down results (CeX search handles this well)
            // But we keep the original title for fuzzy matching verification if needed
            if ($platform) {
                // Map common platform names to CeX friendly terms if needed
                // e.g. "PS2" -> "PlayStation 2"
                // $searchQuery .= " " . $platform;
            }

            Log::info("CexService: Searching for '{$searchQuery}' on '{$platform}' using Puppeteer");

            $scriptPath = base_path('app/Scripts/fetch_cex_price.js');
            $cmdTitle = escapeshellarg($title);
            $cmdPlatform = escapeshellarg($platform ?? '');
            
            // Run the Node script
            // Ensure node is in path or specify full path if needed. Assuming 'node' works.
            $command = "node {$scriptPath} {$cmdTitle} {$cmdPlatform}";
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar === 0 && !empty($output)) {
                // The output should be a JSON array of items
                $jsonStr = implode("\n", $output);
                $items = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($items) && count($items) > 0) {
                     // We have results.
                     // Since we searched with "Title Platform", the results should be relevant.
                     // We return the price of the first item (most relevant).
                     $first = $items[0];
                     Log::info("CexService: Found match: '{$first['title']}' - £{$first['price']}");
                     return (float) $first['price'];
                } else {
                     Log::warning("CexService: No valid JSON items returned or empty list. Output: " . substr($jsonStr, 0, 200));
                }
            } else {
                 Log::warning("CexService: Node script failed. Return code: {$returnVar}. Output: " . implode("\n", $output));
            }
            
        } catch (\Exception $e) {
            Log::error("CexService: Exception: " . $e->getMessage());
        }

        return null;
    }
}
