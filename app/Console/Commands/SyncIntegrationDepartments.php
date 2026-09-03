<?php

namespace App\Console\Commands;

use App\Integrations\Contracts\DepartmentIntegrationInterface;
use App\Models\Department;
use App\Models\Institution;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SyncIntegrationDepartments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integration:sync-departments 
                            {--institution= : Specific Institution ID or code to sync departments for}
                            {--refresh : Force fresh fetch ignoring cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync departments from the active school integration (e.g. UNIZIK CIRS) into the local database';

    /**
     * Execute the console command.
     */
    public function handle(DepartmentIntegrationInterface $integration)
    {
        $this->info('==================================================');
        $this->info('    Starting School Departments Synchronization   ');
        $this->info('==================================================');

        $activeIntegration = config('services.integration.active', env('ACTIVE_INTEGRATION', 'default'));
        $this->line("Active Integration: <comment>{$activeIntegration}</comment>");

        // Resolve target institution
        $institutionIdOption = $this->option('institution');
        $institution = null;

        if ($institutionIdOption) {
            $institution = Institution::where('id', $institutionIdOption)
                ->orWhere('code', $institutionIdOption)
                ->first();
        }

        if (!$institution) {
            $institution = Institution::where('code', 'UNIZIK')
                ->orWhere('name', 'like', '%Unizik%')
                ->orWhere('name', 'like', '%Nnamdi Azikiwe%')
                ->first() ?? Institution::first();
        }

        if (!$institution) {
            $this->error('No institution found in the database! Please create an institution first.');
            return Command::FAILURE;
        }

        $this->line("Target Institution: <info>{$institution->name}</info> (ID: {$institution->id})");
        $this->newLine();

        $this->info('Fetching departments from integration source...');

        $request = new Request([
            'per_page' => 1000,
            'refresh' => $this->option('refresh') ? '1' : '0',
        ]);

        $paginator = $integration->getDepartments($request);
        $departments = $paginator->items();
        $total = $paginator->total();

        if (empty($departments)) {
            $this->warn('No departments returned from the integration source.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} departments. Syncing into local database...");
        $bar = $this->output->createProgressBar(count($departments));
        $bar->start();

        $now = now();
        $records = [];
        $syncedCount = 0;

        foreach ($departments as $dept) {
            $deptId = $dept['id'] ?? $dept['department_code'] ?? null;
            if (!$deptId) {
                $bar->advance();
                continue;
            }

            $records[] = [
                'id' => (string) $deptId,
                'name' => $dept['name'] ?? 'Unknown Department',
                'department_code' => $dept['department_code'] ?? $deptId,
                'institution_id' => $institution->id,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $syncedCount++;
            $bar->advance();
        }

        // Chunked upsert to prevent query payload limits
        foreach (array_chunk($records, 100) as $chunk) {
            Department::upsert(
                $chunk,
                ['id'],
                ['name', 'department_code', 'institution_id', 'is_active', 'updated_at']
            );
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("==================================================");
        $this->info(" Successfully synced {$syncedCount} departments into local DB! ");
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}
