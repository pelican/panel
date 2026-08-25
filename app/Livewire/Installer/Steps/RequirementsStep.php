<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\TablerIcon;
use App\Services\Environment\InstallationHealthService;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Spatie\Health\Checks\Result;

class RequirementsStep
{
    /**
     * Build the installer step that displays and enforces system requirements.
     */
    public static function make(): Step
    {
        $health = app(InstallationHealthService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
        $checks = $health->systemRequirements();

        $fields = $checks
            ->map(function (Result $result) use ($health): Section {
                $failed = $health->hasFailures([$result]);

                return Section::make($result->check->getLabel())
                    ->description($failed ? ($result->meta['remediation'] ?? null) : null)
                    ->icon($failed ? TablerIcon::X : TablerIcon::Check)
                    ->iconColor($failed ? 'danger' : 'success')
                    ->schema([
                        TextEntry::make($result->check->getName())
                            ->hiddenLabel()
                            ->state($result->getNotificationMessage()),
                    ]);
            })
            ->all();

        return Step::make('requirements')
            ->label(trans('installer.requirements.title'))
            ->schema($fields)
            ->afterValidation(function () use ($checks, $health) {
                if ($health->hasFailures($checks)) {
                    Notification::make()
                        ->title(trans('installer.health.preflight_failed'))
                        ->danger()
                        ->send();

                    throw new Halt(trans('installer.requirements.title'));
                }
            });
    }
}
