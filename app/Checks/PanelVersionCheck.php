<?php

namespace App\Checks;

use App\Services\Helpers\SoftwareVersionService;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PanelVersionCheck extends Check
{
    public function __construct(private SoftwareVersionService $versionService) {}

    public function run(): Result
    {
        $isLatest = $this->versionService->isLatestPanel();
        $currentVersion = $this->versionService->currentPanelVersion();
        $latestVersion = $this->versionService->latestPanelVersion();

        $result = Result::make()
            ->meta([
                'isLatest' => $isLatest,
                'currentVersion' => $currentVersion,
                'latestVersion' => $latestVersion,
            ]);

        if (is_null($latestVersion)) {
            return $result
                ->shortSummary(trans('admin/health.results.panelversion.unknown'))
                ->warning(trans('admin/health.results.panelversion.unavailable'));
        }

        $result->shortSummary($isLatest ? trans('admin/health.results.panelversion.up_to_date') : trans('admin/health.results.panelversion.outdated'));

        return $isLatest
            ? $result->ok(trans('admin/health.results.panelversion.ok'))
            : $result->failed(trans('admin/health.results.panelversion.failed', [
                'currentVersion' => $currentVersion,
                'latestVersion' => $latestVersion,
            ]));
    }
}
