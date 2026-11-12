<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\TablerIcon;
use App\Livewire\Installer\PanelInstaller;
use Exception;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

class QueueStep
{
    public const QUEUE_DRIVERS = [
        'database' => 'Database',
        'redis' => 'Redis',
        'sync' => 'Sync',
    ];

    /**
     * @throws Exception
     */
    public static function make(PanelInstaller $installer): Step
    {
        return Step::make('queue')
            ->label(trans('installer.queue.title'))
            ->columns()
            ->schema([
                ToggleButtons::make('env_queue.QUEUE_CONNECTION')
                    ->label(trans('installer.queue.driver'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.queue.driver_help'))
                    ->required()
                    ->inline()
                    ->options(self::QUEUE_DRIVERS)
                    ->disableOptionWhen(fn ($value, Get $get) => $value === 'redis' && $get('env_cache.CACHE_STORE') !== 'redis')
                    ->default(config('queue.default')),
                Toggle::make('done')
                    ->label(trans('installer.queue.fields.done'))
                    ->accepted(fn () => !is_running_in_docker())
                    ->inline(false)
                    ->validationMessages([
                        'accepted' => trans('installer.queue.fields.done_validation'),
                    ])
                    ->hidden(fn () => is_running_in_docker()),
                TextInput::make('crontab')
                    ->label(new HtmlString(trans('installer.queue.fields.crontab')))
                    ->disabled()
                    ->hintCopy()
                    ->default('(sudo crontab -l -u www-data 2>/dev/null; echo "* * * * * php ' . base_path() . '/artisan schedule:run >> /dev/null 2>&1") | sudo crontab -u www-data -')
                    ->hidden(fn () => is_running_in_docker())
                    ->columnSpanFull(),
                TextInput::make('queueService')
                    ->label(new HtmlString(trans('installer.queue.fields.service')))
                    ->disabled()
                    ->hintCopy()
                    ->default('sudo php ' . base_path() . '/artisan p:environment:queue-service')
                    ->hidden(fn () => is_running_in_docker())
                    ->columnSpanFull(),
            ])
            ->afterValidation(fn () => $installer->writeToEnv('env_queue'));
    }
}
