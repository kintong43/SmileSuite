<?php

namespace App\Http\Controllers;

use App\Reports\OperationalReportService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, OperationalReportService $reports): View
    {
        abort_unless($request->user()->hasRole('owner', 'receptionist'), 403);
        [$fromDate, $toDate] = $this->dates($request);

        return view('reports', ['report' => $reports->generate($fromDate, $toDate)]);
    }

    public function export(Request $request, OperationalReportService $reports): StreamedResponse
    {
        abort_unless($request->user()->hasRole('owner', 'receptionist'), 403);
        [$fromDate, $toDate] = $this->dates($request);
        $report = $reports->generate($fromDate, $toDate);

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['group', 'label', 'currency', 'appointments', 'completed', 'scheduled_value_minor', 'from_date', 'to_date', 'timezone'], ',', '"', '');
            foreach (['by_status', 'by_service', 'by_practitioner', 'by_date'] as $group) {
                foreach ($report[$group] as $row) {
                    $cells = [$group, $row['label'], $row['currency'], $row['appointments'], $row['completed'], $row['scheduled_value_minor'], $report['filters']['from_date'], $report['filters']['to_date'], $report['filters']['timezone']];
                    fputcsv($stream, array_map($this->safeCsvCell(...), $cells), ',', '"', '');
                }
            }
            fclose($stream);
        }, 'operational-report-'.$fromDate.'-'.$toDate.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{string, string} */
    private function dates(Request $request): array
    {
        $data = $request->validate(['from_date' => ['sometimes', 'required', 'string', 'date_format:Y-m-d'], 'to_date' => ['sometimes', 'required', 'string', 'date_format:Y-m-d']]);
        $today = CarbonImmutable::today(TenantContext::current()->tenant->timezone);

        return [$data['from_date'] ?? $today->startOfMonth()->format('Y-m-d'), $data['to_date'] ?? $today->format('Y-m-d')];
    }

    private function safeCsvCell(string|int $value): string|int
    {
        if (is_string($value) && preg_match('/^[\s\x00-\x1F]*[=+\-@]|^[\t\r\n]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
