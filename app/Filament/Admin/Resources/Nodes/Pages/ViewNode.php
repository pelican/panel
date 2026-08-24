<?php

namespace App\Filament\Admin\Resources\Nodes\Pages;

use App\Filament\Admin\Resources\Nodes\NodeResource;
use App\Models\Node;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeTabs;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewNode extends ViewRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use CanCustomizeTabs;

    protected static string $resource = NodeResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Tabs')
                ->columns([
                    'default' => 2,
                    'sm' => 3,
                    'md' => 3,
                    'lg' => 4,
                ])
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs($this->getTabs()),
        ]);
    }

    /** @return Tab[] */
    protected function getDefaultTabs(): array
    {
        return NodeResource::detailTabs();
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $node = Node::findOrFail($data['id']);

        if (!is_ip($node->fqdn)) {
            $ip = get_ip_from_hostname($node->fqdn);
            if ($ip) {
                $data['dns'] = true;
                $data['ip'] = $ip;
            } else {
                $data['dns'] = false;
            }
        }

        return $data;
    }

    protected function getColumnSpan(): ?int
    {
        return null;
    }

    protected function getColumnStart(): ?int
    {
        return null;
    }
}
