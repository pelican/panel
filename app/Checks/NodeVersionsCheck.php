<?php

namespace App\Checks;

use App\Models\Node;
use App\Services\Helpers\SoftwareVersionService;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;

class NodeVersionsCheck extends Check
{
    public function __construct(private SoftwareVersionService $versionService) {}

    public function run(): Result
    {
        $all = Node::all();

        if ($all->isEmpty()) {
            $result = Result::make()
                ->notificationMessage(trans('admin/health.results.nodeversions.no_nodes_created'))
                ->shortSummary(trans('admin/health.results.nodeversions.no_nodes'));
            $result->status = Status::skipped();

            return $result;
        }

        $versions = $all
            ->map(fn (Node $node) => $node->systemInformation())
            ->reject(fn (array $info) => isset($info['exception']))
            ->pluck('version')
            ->filter();

        $incompatible = $versions->reject(fn (string $version) => $this->versionService->isCompatibleWings($version))->count();
        $outdated = $versions->reject(fn (string $version) => $this->versionService->isLatestWings($version))->count();

        $all = $all->count();
        $min = config('panel.wings.min_version');

        $result = Result::make()
            ->meta([
                'all' => $all,
                'outdated' => $outdated,
                'incompatible' => $incompatible,
                'latestVersion' => $this->versionService->latestWingsVersion(),
                'minVersion' => $min,
            ]);

        if ($incompatible > 0) {
            return $result
                ->shortSummary(trans('admin/health.results.nodeversions.incompatible', ['incompatible' => $incompatible, 'all' => $all]))
                ->failed(trans('admin/health.results.nodeversions.failed_incompatible', ['incompatible' => $incompatible, 'all' => $all, 'min' => $min]));
        }

        if ($outdated > 0) {
            return $result
                ->shortSummary(trans('admin/health.results.nodeversions.outdated', ['outdated' => $outdated, 'all' => $all]))
                ->warning(trans('admin/health.results.nodeversions.failed', ['outdated' => $outdated, 'all' => $all]));
        }

        return $result
            ->shortSummary(trans('admin/health.results.nodeversions.all_up_to_date'))
            ->ok(trans('admin/health.results.nodeversions.ok'));
    }
}
