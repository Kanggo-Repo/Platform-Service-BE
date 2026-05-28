<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ServiceDashboardAggregator;
use Illuminate\Http\JsonResponse;

class PlatformDashboardController extends Controller
{
    public function __construct(
        private readonly ServiceDashboardAggregator $aggregator,
    ) {}

    public function __invoke(): JsonResponse
    {
        $supplySummary = $this->aggregator->fetchSupplySummary();
        $calculationSummary = $this->aggregator->fetchCalculationSummary();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_users' => (int) ($supplySummary['material_count'] ?? 0),
                    'role_count' => (int) ($supplySummary['unit_count'] ?? 0),
                    'permission_count' => (int) ($supplySummary['store_count'] ?? 0),
                    'pending_access_count' => (int) ($calculationSummary['work_item_count'] ?? 0),
                    'allowed_user_count' => 0,
                    'registration_enabled' => false,
                ],
                'chart' => [
                    'labels' => $supplySummary['chart_data']['labels'] ?? [],
                    'data' => $supplySummary['chart_data']['data'] ?? [],
                ],
                'recent_activities' => $supplySummary['recent_activities'] ?? [],
                'service_matrix' => [
                    'platform' => 0,
                    'supply' => 0,
                    'calculation' => 0,
                ],
            ],
        ]);
    }
}
