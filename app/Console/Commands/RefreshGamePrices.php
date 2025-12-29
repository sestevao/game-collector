<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Game;
use App\Services\GamePriceManager;
use Illuminate\Support\Facades\Log;

class RefreshGamePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:refresh-prices {--user= : The ID of the user to refresh prices for} {--sleep=2 : Seconds to sleep between requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh prices for all games or a specific user\'s games';

    protected $gamePriceManager;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(GamePriceManager $gamePriceManager)
    {
        parent::__construct();
        $this->gamePriceManager = $gamePriceManager;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->option('user');
        $sleep = (int) $this->option('sleep');

        $query = Game::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Only fetch games that are not manually set (optional logic, but usually user wants to refresh everything)
        // Or maybe prioritize games that haven't been updated in a while. 
        // For now, just all games matching the filter.
        
        $count = $query->count();

        if ($count === 0) {
            $this->info("No games found to update.");
            return 0;
        }

        $this->info("Found {$count} games to update.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // Chunking to avoid memory issues with large collections
        $query->chunk(50, function ($games) use ($bar, $sleep) {
            foreach ($games as $game) {
                try {
                    // Log to console detail
                    // $this->line(" Updating: {$game->title}...");
                    
                    $result = $this->gamePriceManager->updateGamePrice($game);
                    
                    if ($result) {
                        // $this->info(" Updated: {$game->title} - £{$result['price']} ({$result['source']})");
                    } else {
                        // $this->warn(" Failed: {$game->title}");
                    }

                } catch (\Exception $e) {
                    Log::error("Error updating price for game ID {$game->id}: " . $e->getMessage());
                    $this->error("Error updating {$game->title}: " . $e->getMessage());
                }

                $bar->advance();
                
                if ($sleep > 0) {
                    sleep($sleep);
                }
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Price refresh completed.");

        return 0;
    }
}
