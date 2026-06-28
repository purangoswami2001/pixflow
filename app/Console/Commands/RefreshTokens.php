<?php

namespace App\Console\Commands;

use App\Services\Social\SocialAccountManager;
use Illuminate\Console\Command;

class RefreshTokens extends Command
{
    protected $signature = 'social:refresh-tokens';
    protected $description = 'Refresh expired OAuth access tokens';

    public function handle(SocialAccountManager $manager): int
    {
        $this->info('Refreshing expired OAuth tokens...');
        $manager->refreshTokens();
        $this->info('OAuth tokens refresh complete.');
        return 0;
    }
}
