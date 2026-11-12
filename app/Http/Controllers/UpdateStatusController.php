<?php

namespace App\Http\Controllers;

use App\Services\Helpers\PanelUpdateService;
use Illuminate\Http\JsonResponse;

class UpdateStatusController extends Controller
{
    /**
     * Progress of a panel update for the dashboard to poll. A plain endpoint instead of a Livewire poll, since the
     * component's snapshot doesn't survive the code underneath it being replaced.
     */
    public function __invoke(PanelUpdateService $updateService): JsonResponse
    {
        abort_unless(user()?->isRootAdmin(), 403);

        return response()->json($updateService->status() ?? ['state' => null])
            ->header('Cache-Control', 'no-store');
    }
}
