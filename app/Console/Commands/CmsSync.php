<?php

namespace App\Console\Commands;

use App\Cms\CmsProjection;
use App\Cms\CmsUnavailable;
use App\Models\Tenant;
use App\Tenancy\TenantRunner;
use Illuminate\Console\Command;

class CmsSync extends Command
{
    protected $signature = 'cms:sync {tenant : Clinic subdomain}';

    protected $description = 'Publish safe staff, service and aggregate report projections to Directus';

    public function handle(TenantRunner $runner, CmsProjection $projection): int
    {
        $tenant = Tenant::where('subdomain', $this->argument('tenant'))->first();
        if ($tenant === null) {
            $this->error('Clinic not found.');

            return self::FAILURE;
        }
        try {
            $counts = $runner->run($tenant, fn (): array => $projection->sync());
        } catch (CmsUnavailable $exception) {
            $this->error($exception->getMessage().' Some earlier projections may have been saved; rerun safely after restoring CMS access.');

            return self::FAILURE;
        }
        $this->info('CMS projections published: '.json_encode($counts, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
