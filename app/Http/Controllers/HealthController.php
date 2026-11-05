<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Enums\Status;
use Spatie\Health\ResultStores\ResultStore;

class HealthController extends Controller
{
    /**
     * Health check results for external monitors, gated by HEALTH_ENDPOINT_SECRET. Warnings stay 200,
     * only failed or crashed checks return 503.
     */
    public function __invoke(Request $request, ResultStore $resultStore): Response
    {
        $secret = (string) config('health.endpoint_secret');
        abort_if($secret === '', 404);
        abort_unless(hash_equals($secret, (string) ($request->bearerToken() ?? $request->query('token'))), 403);

        if ($request->has('fresh')) {
            Artisan::call(RunHealthChecksCommand::class);
        }

        $results = $resultStore->latestResults();
        $failing = $results?->containsCheckWithStatus([Status::failed(), Status::crashed()]) ?? true;

        return response($results?->toJson() ?? '{}', $failing ? 503 : 200)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'no-store');
    }
}
