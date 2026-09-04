<?php

namespace Modules\Essentials\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Services\WorkforceMetricsService;

class WorkforceAnalyticsController extends Controller
{
    use AuthorizesHrmRequests;

    protected ModuleUtil $moduleUtil;
    private WorkforceMetricsService $metrics;

    public function __construct(ModuleUtil $moduleUtil, WorkforceMetricsService $metrics)
    {
        $this->moduleUtil = $moduleUtil;
        $this->metrics = $metrics;
    }

    public function index(Request $request)
    {
        [$businessId, $start, $end] = $this->context($request);
        $metrics = $this->metrics->calculate($businessId, $start, $end);
        $departments = $this->metrics->departmentBreakdown($businessId);
        $history = DB::table('hrm_metric_snapshots')->where('business_id', $businessId)->whereBetween('snapshot_date', [$start, $end])->orderBy('snapshot_date')->get()->groupBy('metric_key');

        return view('essentials::analytics.index', compact('metrics', 'departments', 'history', 'start', 'end'));
    }

    public function export(Request $request)
    {
        [$businessId, $start, $end] = $this->context($request);
        $metrics = $this->metrics->calculate($businessId, $start, $end);

        return response()->streamDownload(function () use ($metrics, $start, $end) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Metric', 'Value', 'Population', 'Period start', 'Period end']);
            foreach ($metrics as $key => $metric) fputcsv($stream, [$key, $metric['value'], $metric['population'], $start, $end]);
            fclose($stream);
        }, 'workforce-metrics-'.$start.'-'.$end.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function context(Request $request): array
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, 'essentials.view_workforce_analytics');
        $data = $request->validate(['start' => ['nullable', 'date'], 'end' => ['nullable', 'date', 'after_or_equal:start']]);

        return [$businessId, $data['start'] ?? now()->startOfYear()->toDateString(), $data['end'] ?? now()->toDateString()];
    }
}
