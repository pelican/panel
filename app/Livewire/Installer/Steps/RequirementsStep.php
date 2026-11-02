<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\TablerIcon;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;

class RequirementsStep
{
    public const MIN_PHP_VERSION = '8.4';

    public static function make(): Step
    {
        $compare = version_compare(phpversion(), self::MIN_PHP_VERSION);
        $correctPhpVersion = $compare >= 0;

        $fields = [
            Section::make(trans('installer.requirements.sections.version.title'))
                ->description(trans('installer.requirements.sections.version.or_newer', ['version' => self::MIN_PHP_VERSION]))
                ->icon($correctPhpVersion ? TablerIcon::Check : TablerIcon::X)
                ->iconColor($correctPhpVersion ? 'success' : 'danger')
                ->schema([
                    TextEntry::make('php_version')
                        ->hiddenLabel()
                        ->state(trans('installer.requirements.sections.version.content', ['version' => PHP_VERSION])),
                ]),
        ];

        $phpExtensions = [
            'BCMath' => extension_loaded('bcmath'),
            'cURL' => extension_loaded('curl'),
            'GD' => extension_loaded('gd'),
            'intl' => extension_loaded('intl'),
            'mbstring' => extension_loaded('mbstring'),
            'MySQL' => extension_loaded('pdo_mysql'),
            'SQLite3' => extension_loaded('pdo_sqlite'),
            'XML' => extension_loaded('xml'),
            'Zip' => extension_loaded('zip'),
        ];
        $allExtensionsInstalled = !in_array(false, $phpExtensions);

        $fields[] = Section::make(trans('installer.requirements.sections.extensions.title'))
            ->description(implode(', ', array_keys($phpExtensions)))
            ->icon($allExtensionsInstalled ? TablerIcon::Check : TablerIcon::X)
            ->iconColor($allExtensionsInstalled ? 'success' : 'danger')
            ->schema([
                TextEntry::make('all_extensions_installed')
                    ->hiddenLabel()
                    ->state(trans('installer.requirements.sections.extensions.good'))
                    ->visible($allExtensionsInstalled),
                TextEntry::make('extensions_missing')
                    ->hiddenLabel()
                    ->state(trans('installer.requirements.sections.extensions.bad', ['extensions' => implode(', ', array_keys($phpExtensions, false))]))
                    ->visible(!$allExtensionsInstalled),
            ]);

        $folderPermissions = [
            'Storage' => substr(sprintf('%o', fileperms(base_path('storage/'))), -4) >= 755,
            'Cache' => substr(sprintf('%o', fileperms(base_path('bootstrap/cache/'))), -4) >= 755,
        ];
        $correctFolderPermissions = !in_array(false, $folderPermissions);

        $fields[] = Section::make(trans('installer.requirements.sections.permissions.title'))
            ->description(implode(', ', array_keys($folderPermissions)))
            ->icon($correctFolderPermissions ? TablerIcon::Check : TablerIcon::X)
            ->iconColor($correctFolderPermissions ? 'success' : 'danger')
            ->schema([
                TextEntry::make('correct_folder_permissions')
                    ->hiddenLabel()
                    ->state(trans('installer.requirements.sections.permissions.good'))
                    ->visible($correctFolderPermissions),
                TextEntry::make('wrong_folder_permissions')
                    ->hiddenLabel()
                    ->state(trans('installer.requirements.sections.permissions.bad', ['folders' => implode(', ', array_keys($folderPermissions, false))]))
                    ->visible(!$correctFolderPermissions),
            ]);

        return Step::make('requirements')
            ->label(trans('installer.requirements.title'))
            ->schema($fields)
            ->afterValidation(function () use ($correctPhpVersion, $allExtensionsInstalled, $correctFolderPermissions) {
                if (!$correctPhpVersion || !$allExtensionsInstalled || !$correctFolderPermissions) {
                    Notification::make()
                        ->title(trans('installer.requirements.exception'))
                        ->danger()
                        ->send();

                    throw new Halt(trans('installer.requirements.title'));
                }
            });
    }
}
