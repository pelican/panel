<?php

namespace App\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Models\Mount;
use App\Models\Server;
use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class Mounts extends ServerFormPage
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::LayersLinked;

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return parent::canAccess() && user()?->can(SubuserPermission::MountRead, Filament::getTenant());
    }

    protected function authorizeAccess(): void
    {
        abort_unless(user()?->can(SubuserPermission::MountRead, Filament::getTenant()), 403);
    }

    protected function fillForm(): void
    {
        $allowedMountIds = $this->getAllowedMounts()->pluck('id');

        $this->form->fill([
            'mounts' => $this->getRecord()->mounts->pluck('id')->intersect($allowedMountIds)->values()->toArray(),
        ]);
    }

    /** @return Collection<int, Mount> */
    protected function getAllowedMounts(): Collection
    {
        $server = $this->getRecord();

        return Mount::query()
            ->where('user_mountable', true)
            ->where(function ($query) use ($server) {
                $query->whereDoesntHave('nodes')
                    ->orWhereHas('nodes', fn ($q) => $q->where('nodes.id', $server->node_id));
            })
            ->where(function ($query) use ($server) {
                $query->whereDoesntHave('eggs')
                    ->orWhereHas('eggs', fn ($q) => $q->where('eggs.id', $server->egg_id));
            })
            ->get();
    }

    public function form(Schema $schema): Schema
    {
        $allowedMounts = $this->getAllowedMounts();

        return parent::form($schema)
            ->components([
                Section::make([
                    CheckboxList::make('mounts')
                        ->label(trans('server/mount.description'))
                        // Only sync mounts the user can see, so mounts attached by an admin stay attached
                        ->relationship('mounts', modifyQueryUsing: fn (Builder $query) => $query->whereIn('mounts.id', $allowedMounts->pluck('id')))
                        ->options(fn () => $allowedMounts->mapWithKeys(fn (Mount $mount) => [$mount->id => $mount->name]))
                        ->descriptions(fn () => $allowedMounts->mapWithKeys(fn (Mount $mount) => [$mount->id => new HtmlString(str("$mount->source -> $mount->target")->stripTags() . ($mount->description ? '<br>' . str($mount->description)->stripTags() : ''))]))
                        ->helperText(fn () => $allowedMounts->isEmpty() ? trans('server/mount.no_mounts') : null)
                        ->disabled(fn (Server $server) => !user()?->can(SubuserPermission::MountUpdate, $server))
                        ->bulkToggleable()
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            $this->save();
                        })
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(user()?->can(SubuserPermission::MountUpdate, $this->getRecord()), 403);

        try {
            $this->form->getState();
            $this->form->saveRelationships();

            Activity::event('server:mount.update')
                ->log();

            Notification::make()
                ->title(trans('server/mount.notification_updated'))
                ->body(trans('server/mount.notification_updated_body'))
                ->success()
                ->send();
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->title(trans('server/mount.notification_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return trans('server/mount.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/mount.title');
    }
}
