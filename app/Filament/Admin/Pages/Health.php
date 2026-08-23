<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Enums\Status;
use Spatie\Health\ResultStores\ResultStore;

class Health extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Heart;

    protected string $view = 'filament.pages.health';

    /** @var array<string, string> */
    protected $listeners = [
        'refresh-component' => '$refresh',
    ];

    public function getTitle(): string
    {
        return trans('admin/health.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('admin/health.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    public static function canAccess(): bool
    {
        return user()?->can('view health') ?? false;
    }

    protected function getActions(): array
    {
        return [
            Action::make('refresh')
                ->hiddenLabel()
                ->tooltip(trans('admin/health.refresh'))
                ->icon(TablerIcon::Refresh)
                ->action('refresh'),
        ];
    }

    protected function getViewData(): array
    {
        // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
        $checkResults = app(ResultStore::class)->latestResults();

        if ($checkResults === null) {
            Artisan::call(RunHealthChecksCommand::class);

            $this->dispatch('refresh-component');
        }

        return [
            'lastRanAt' => new Carbon($checkResults?->finishedAt),
            'checkResults' => $checkResults,
        ];
    }

    public function refresh(): void
    {
        Artisan::call(RunHealthChecksCommand::class);

        $this->dispatch('refresh-component');

        Notification::make()
            ->title(trans('admin/health.results_refreshed'))
            ->success()
            ->send();
    }

    public static function getNavigationBadge(): ?string
    {
        // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
        $results = app(ResultStore::class)->latestResults();

        if ($results === null) {
            return null;
        }

        $results = json_decode($results->toJson(), true);

        $failed = array_reduce($results['checkResults'], function ($numFailed, $result) {
            return $numFailed + ($result['status'] === 'failed' ? 1 : 0);
        }, 0);

        return $failed === 0 ? null : (string) $failed;
    }

    public static function getNavigationBadgeColor(): string
    {
        return self::getNavigationBadge() > null ? 'danger' : '';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
        $results = app(ResultStore::class)->latestResults();

        if ($results === null) {
            return null;
        }

        $results = json_decode($results->toJson(), true);

        $failedNames = array_reduce($results['checkResults'], function ($carry, $result) {
            if ($result['status'] === 'failed') {
                $carry[] = $result['name'];
            }

            return $carry;
        }, []);

        return trans('admin/health.checks.failed', ['checks' => implode(', ', $failedNames)]);
    }

    public static function getNavigationIcon(): BackedEnum
    {
        // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
        $results = app(ResultStore::class)->latestResults();

        if ($results === null) {
            return TablerIcon::HeartQuestion;
        }

        return $results->containsFailingCheck() ? TablerIcon::HeartExclamation : TablerIcon::HeartCheck;
    }

    public function backgroundColor(string $str): string
    {
        return match ($str) {
            Status::ok()->value => 'bg-success-100 dark:bg-success-200',
            Status::warning()->value => 'bg-warning-100 dark:bg-warning-200',
            Status::skipped()->value => 'bg-info-100 dark:bg-info-200',
            Status::failed()->value, Status::crashed()->value => 'bg-danger-100 dark:bg-danger-200',
            default => 'bg-gray-100 dark:bg-gray-200'
        };
    }

    public function iconColor(string $str): string
    {
        return match ($str) {
            Status::ok()->value => 'text-success-500 dark:text-success-600',
            Status::warning()->value => 'text-warning-500 dark:text-warning-600',
            Status::skipped()->value => 'text-info-500 dark:text-info-600',
            Status::failed()->value, Status::crashed()->value => 'text-danger-500 dark:text-danger-600',
            default => 'text-gray-500 dark:text-gray-600'
        };
    }

    public function icon(string $str): BackedEnum
    {
        return match ($str) {
            Status::ok()->value => TablerIcon::CircleCheck,
            Status::warning()->value => TablerIcon::ExclamationCircle,
            Status::skipped()->value => TablerIcon::CircleChevronRight,
            Status::failed()->value, Status::crashed()->value => TablerIcon::CircleX,
            default => TablerIcon::HelpCircle
        };
    }
}
