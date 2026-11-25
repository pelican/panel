<x-filament::badge :color="$condition->getColor()" :icon="$condition->getIcon()" size="sm">
    {{ $condition->getLabel() }}
</x-filament::badge>
