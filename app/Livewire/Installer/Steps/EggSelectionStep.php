<?php

namespace App\Livewire\Installer\Steps;

use App\Console\Commands\Egg\UpdateEggIndexCommand;
use App\Enums\TablerIcon;
use App\Livewire\Installer\PanelInstaller;
use Exception;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Artisan;

class EggSelectionStep
{
    public static function make(PanelInstaller $installer): Step
    {
        return Step::make('egg')
            ->label(trans('installer.egg.title'))
            ->columnSpanFull()
            ->schema(self::schema($installer->eggIndex));
    }

    /**
     * Fetches the latest egg index, falling back to the last one cached.
     *
     * @return array<string, array<string, string>> category => [download url => name]
     */
    public static function loadIndex(): array
    {
        try {
            Artisan::call(UpdateEggIndexCommand::class);
        } catch (Exception $exception) {
            Notification::make()
                ->title(trans('installer.egg.exceptions.failed_to_update'))
                ->icon(TablerIcon::Egg)
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }

        return cache()->get('eggs.index', []);
    }

    /**
     * @param  array<string, array<string, string>>|null  $eggs  null while still loading
     * @return array<int, mixed>
     */
    private static function schema(?array $eggs): array
    {
        if ($eggs === null) {
            return [
                View::make('filament.components.installer-loading')
                    ->viewData(['message' => trans('installer.egg.loading')]),
            ];
        }

        $categories = array_keys($eggs);

        $tabs = array_map(function (string $label) use ($eggs) {
            $id = str_slug($label, '_');
            $eggCount = count($eggs[$label]);

            return Tab::make($id)
                ->label($label)
                ->badge($eggCount)
                ->schema([
                    CheckboxList::make("eggs.$id")
                        ->hiddenLabel()
                        ->options(fn () => array_sort($eggs[$label]))
                        ->searchable($eggCount > 0)
                        ->bulkToggleable($eggCount > 0)
                        ->columns(4),
                ]);
        }, $categories);

        if (empty($tabs)) {
            $tabs[] = Tab::make('no_eggs')
                ->label(trans('installer.egg.no_eggs'))
                ->schema([
                    TextEntry::make('no_eggs')
                        ->hiddenLabel()
                        ->state(trans('installer.egg.exceptions.no_eggs')),
                ]);
        }

        return [
            Tabs::make('egg_tabs')
                ->tabs($tabs),
        ];
    }
}
