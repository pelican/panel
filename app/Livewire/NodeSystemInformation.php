<?php

namespace App\Livewire;

use App\Enums\TablerIcon;
use App\Models\Node;
use App\Services\Helpers\SoftwareVersionService;
use Filament\Support\Enums\IconSize;
use Filament\Tables\View\Components\Columns\IconColumnComponent\IconComponent;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Attributes\Locked;
use Livewire\Component;

use function Filament\Support\generate_icon_html;

class NodeSystemInformation extends Component
{
    #[Locked]
    public Node $node;

    public function render(SoftwareVersionService $versionService): string
    {
        $systemInformation = $this->node->systemInformation();
        $exception = $systemInformation['exception'] ?? null;
        $version = $systemInformation['version'] ?? null;

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

        if ($exception) {
            $this->js('console.error(' . json_encode((string) $exception, $flags) . ');');
        }

        $incompatible = !$exception && $version && !$versionService->isCompatibleWings($version);

        $tooltip = match (true) {
            (bool) $exception => 'Error connecting to node! Check browser console for details.',
            $incompatible => $version . ' · ' . trans('admin/node.requires_wings', ['min' => config('panel.wings.min_version')]),
            default => (string) $version,
        };
        $tooltip = json_encode($tooltip, $flags);

        $icon = $exception ? TablerIcon::HeartOff : TablerIcon::Heartbeat;
        $color = $exception ? 'danger' : ($incompatible ? 'warning' : 'success');

        return generate_icon_html($icon, attributes: (new ComponentAttributeBag())
            ->merge([
                'x-tooltip' => '{
                    content: ' . $tooltip . ',
                    theme: $store.theme,
                    allowHTML: false,
                    placement: "bottom",
                }',
            ], escape: false)
            ->color(IconComponent::class, $color), size: IconSize::Large)
            ->toHtml();
    }

    public function placeholder(): string
    {
        return generate_icon_html(TablerIcon::HeartQuestion, attributes: (new ComponentAttributeBag())
            ->color(IconComponent::class, 'warning'), size: IconSize::Large)
            ->toHtml();
    }
}
