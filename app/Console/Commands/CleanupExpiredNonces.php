<?php

namespace App\Console\Commands;

use App\Services\Security\NonceService;
use Illuminate\Console\Command;

class CleanupExpiredNonces extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nonce:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired nonces from the database';

    private NonceService $nonceService;

    public function __construct(NonceService $nonceService)
    {
        parent::__construct();
        $this->nonceService = $nonceService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired nonces...');

        $deletedCount = $this->nonceService->cleanup();

        if ($deletedCount > 0) {
            $this->info("Successfully cleaned up {$deletedCount} expired nonces.");
        } else {
            $this->info('No expired nonces found to clean up.');
        }

        return self::SUCCESS;
    }
}
