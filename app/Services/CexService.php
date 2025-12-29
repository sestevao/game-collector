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

            // Sanitize title for search query (remove special chars that might confuse search or WAF)
            // CeX search works better with simple text
            $searchTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
            
            Log::info("CexService: Searching for '{$searchTitle}' on '{$platform}' using Puppeteer");

            $scriptPath = base_path('app/Scripts/fetch_cex_price.js');
            $cmdTitle = escapeshellarg($searchTitle);
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
                     // Find the best match
                     $match = $this->findBestMatch($items, $title);
                     
                     if ($match) {
                         Log::info("CexService: Found match: '{$match['title']}' - £{$match['price']}");
                         return (float) $match['price'];
                     } else {
                         Log::warning("CexService: No suitable match found for '{$title}' in results.");
                         // Fallback to first item if we are desperate? No, better to be safe.
                     }
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

    private function findBestMatch($items, $targetTitle)
    {
        $bestMatch = null;
        $highestSimilarity = 0;
        $targetTitleNorm = $this->normalizeTitle($targetTitle);

        foreach ($items as $item) {
            $itemTitleNorm = $this->normalizeTitle($item['title']);
            
            // Exact match check (after normalization)
            if ($itemTitleNorm === $targetTitleNorm) {
                return $item;
            }

            // Check if one contains the other (helps with subtitles)
            if (str_contains($itemTitleNorm, $targetTitleNorm) || str_contains($targetTitleNorm, $itemTitleNorm)) {
                 // Calculate similarity to be sure it's not a tiny substring match
                 similar_text($targetTitleNorm, $itemTitleNorm, $percent);
                 if ($percent > $highestSimilarity) {
                     $highestSimilarity = $percent;
                     $bestMatch = $item;
                 }
                 continue;
            }

            similar_text($targetTitleNorm, $itemTitleNorm, $percent);
            
            if ($percent > $highestSimilarity) {
                $highestSimilarity = $percent;
                $bestMatch = $item;
            }
        }

        // Require at least 85% similarity or containment
        // If the similarity is lower, we risk matching "Dragon Ball Z" to "Dragon Ball Z Kakarot"
        if ($highestSimilarity >= 80) {
            return $bestMatch;
        }

        return null;
    }

    private function normalizeTitle($title)
    {
        // Remove "The", punctuation, spaces, and lowercase
        $title = strtolower($title);
        $title = str_replace(['the ', 'a '], '', $title); // simple stop words
        return preg_replace('/[^a-z0-9]/', '', $title);
    }
}
