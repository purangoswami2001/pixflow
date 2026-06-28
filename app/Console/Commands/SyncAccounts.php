<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Social\SocialAccountManager;
use Illuminate\Console\Command;

class SyncAccounts extends Command
{
    protected $signature = 'social:sync-accounts';
    protected $description = 'Sync pages and boards for all connected social accounts';

    public function handle(SocialAccountManager $manager): int
    {
        $this->info('Starting social accounts destination sync...');
        
        $accounts = SocialAccount::all();
        $this->info("Found {$accounts->count()} connected accounts.");

        foreach ($accounts as $account) {
            $this->info("Syncing {$account->provider->value} account: {$account->name}");
            $manager->syncDestinations($account);
        }

        $this->info('Sync complete.');
        return 0;
    }
}
