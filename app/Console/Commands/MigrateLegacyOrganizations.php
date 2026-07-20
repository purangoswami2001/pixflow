<?php

namespace App\Console\Commands;

use App\Services\Organizations\LegacyOrganizationMigrator;
use Illuminate\Console\Command;

class MigrateLegacyOrganizations extends Command
{
    protected $signature = 'organizations:migrate-legacy
                            {--dry-run : Preview the migration without writing changes}';

    protected $description = 'Migrate old single-tenant accounts into organization-scoped firms (one firm per admin / social-account owner)';

    public function handle(LegacyOrganizationMigrator $migrator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be saved.');
        } else {
            $this->info('Migrating legacy accounts into organization firms...');
        }

        $stats = $migrator->migrate($dryRun);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Organizations created', $stats['organizations_created']],
                ['Memberships created', $stats['memberships_created']],
                ['Users updated (current org)', $stats['users_updated']],
                ['Tenant rows remapped', $stats['rows_moved']],
                ['Users skipped (already owners)', $stats['skipped']],
            ]
        );

        if ($dryRun) {
            $this->comment('Re-run without --dry-run to apply these changes.');
        } else {
            $this->info('Legacy organization migration complete.');
            $this->line('Tip: turn on automation per firm to seed prompts + one sample category.');
        }

        return self::SUCCESS;
    }
}
