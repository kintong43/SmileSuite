<?php

namespace App\Cms;

use App\Models\Service;
use App\Models\User;
use App\Reports\OperationalReportService;
use App\Tenancy\TenantContext;

final class CmsProjection
{
    public function __construct(private DirectusClient $cms, private OperationalReportService $reports) {}

    /** @return array{services: int, directory: int, reports: int} */
    public function sync(): array
    {
        $context = TenantContext::current();
        $counts = ['services' => 0, 'directory' => 0, 'reports' => 0];
        foreach (Service::where('active', true)->get() as $service) {
            $this->cms->upsertProjection('ss_services', 'service_ref', (string) $service->id, ['title' => $service->name]);
            $counts['services']++;
        }
        foreach (User::with('roles')->get() as $user) {
            $roles = array_values(array_intersect($user->roleNames(), ['owner', 'receptionist', 'practitioner']));
            if ($roles !== []) {
                $this->cms->upsertProjection('ss_directory', 'resource_id', (string) $user->id, ['display_name' => $user->name, 'roles' => $roles]);
                $counts['directory']++;
            }
        }
        $today = now($context->tenant->timezone);
        $from = $today->copy()->startOfMonth()->toDateString();
        $to = $today->toDateString();
        $report = $this->reports->generate($from, $to);
        $totals = array_intersect_key($report['totals'], array_flip(['appointments', 'completed', 'scheduled_value_minor']));
        $this->cms->upsertProjection('ss_reports', 'period', $from.'/'.$to, ['aggregates' => ['totals' => $totals, 'currency' => $report['currency']]]);
        $counts['reports']++;

        return $counts;
    }
}
