<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Dashboard;
use App\Services\Helpers\PanelUpdateService;
use Filament\Widgets\Widget;

class UpdateProgressWidget extends Widget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.update-progress';

    public static function canView(): bool
    {
        if (!user()?->isRootAdmin()) {
            return false;
        }

        $status = app(PanelUpdateService::class)->status(); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
        if (!filled($status['state'] ?? null)) {
            return false;
        }

        // Finished updates stay visible for a day unless dismissed.
        return in_array($status['state'], [PanelUpdateService::STATE_QUEUED, PanelUpdateService::STATE_RUNNING, PanelUpdateService::STATE_ROLLBACK_FAILED], true)
            || strtotime($status['finished_at'] ?? '') > now()->subDay()->getTimestamp();
    }

    public function dismiss(PanelUpdateService $panelUpdateService): void
    {
        abort_unless(user()?->isRootAdmin(), 403);

        $status = $panelUpdateService->status();

        if (in_array($status['state'] ?? null, [PanelUpdateService::STATE_SUCCEEDED, PanelUpdateService::STATE_FAILED], true)) {
            $panelUpdateService->dismissStatus();
        }

        $this->redirect(Dashboard::getUrl());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'status' => app(PanelUpdateService::class)->status(), // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
            'steps' => collect(PanelUpdateService::STEPS)->mapWithKeys(fn (string $step) => [$step => trans("admin/update.progress.steps.$step")])->all(),
            'states' => trans('admin/update.progress.states'),
            'statusUrl' => route('update.status'),
        ];
    }
}
