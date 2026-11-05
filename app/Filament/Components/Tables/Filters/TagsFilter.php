<?php

namespace App\Filament\Components\Tables\Filters;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TagsFilter extends BaseFilter
{
    protected string $model;

    public static function getDefaultName(): ?string
    {
        return 'tags';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->query(fn (Builder $query, array $data) => $query->when($data['tag'] ?? null, fn (Builder $query, $tag) => $query->whereJsonContains('tags', $tag)));

        $this->indicateUsing(fn (array $data) => ($data['tag'] ?? null) ? 'Tag: ' . $data['tag'] : null);

        $this->resetState(['tag' => null]);

        $this->visible(fn () => $this->getTags()->count() > 0);
    }

    private function getTags(): Collection
    {
        return once(fn () => $this->getModel()::query()->pluck('tags')->flatten()->unique());

    }

    public function getFormField(): Field
    {
        return Select::make('tag')
            ->preload()
            ->searchable()
            ->options(fn () => $this->getTags()->mapWithKeys(fn ($tag) => [$tag => $tag]));
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
