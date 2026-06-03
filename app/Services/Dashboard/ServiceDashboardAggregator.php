<?php

namespace App\Services\Dashboard;

use App\Support\Observability\RequestCorrelation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ServiceDashboardAggregator
{
    public function fetchSupplySummary(): array
    {
        $response = $this->supplyClient()
            ->get('/api/v1/dashboard-summary')
            ->throw();

        return $response->json('data', []);
    }

    public function fetchCalculationSummary(): array
    {
        $response = $this->calculationClient()
            ->get('/api/v1/dashboard-summary')
            ->throw();

        return $response->json('data', []);
    }

    private function supplyClient(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.supply_service.base_url'), '/');
        $serviceName = trim((string) config('services.supply_service.service_name'));
        $token = trim((string) config('services.supply_service.token'));

        if ($baseUrl === '' || $serviceName === '' || $token === '') {
            throw new RuntimeException('Supply service dashboard client is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(array_merge(
                RequestCorrelation::outgoingHeaders($serviceName),
                [
                    'X-Service-Token' => $token,
                ],
            ));
    }

    private function calculationClient(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.calculation_service.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Calculation service dashboard client is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(RequestCorrelation::outgoingHeaders());
    }
}
