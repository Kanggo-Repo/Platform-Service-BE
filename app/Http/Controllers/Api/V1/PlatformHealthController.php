<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;

class PlatformHealthController extends Controller
{
    public function __invoke(Request $request, ResultStore $resultStore): JsonResponse
    {
        if ($request->boolean('fresh', true) || config('health.oh_dear_endpoint.always_send_fresh_results')) {
            Artisan::call(RunHealthChecksCommand::class);
        }

        $results = $resultStore->latestResults();
        $healthy = $results?->allChecksOk() ?? false;

        return response()->json([
            'status' => $healthy ? 'ok' : 'failed',
            'healthy' => $healthy,
            'service' => config('app.name'),
            'finished_at' => $results?->finishedAt?->format(DATE_ATOM),
            'checks' => $results?->storedCheckResults
                ->map(fn (StoredCheckResult $check): array => [
                    'name' => $check->name,
                    'label' => $check->label,
                    'status' => $check->status,
                    'summary' => $check->shortSummary,
                ])
                ->values()
                ->all() ?? [],
        ], $healthy ? 200 : 503);
    }
}
