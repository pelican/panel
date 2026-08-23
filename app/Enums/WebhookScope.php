<?php

namespace App\Enums;

enum WebhookScope: string
{
    case Global = 'global';
    case Server = 'server';

    public function getLabel(): string
    {
        return match ($this) {
            self::Global => trans('admin/webhook.scope.global'),
            self::Server => trans('admin/webhook.scope.server'),
        };
    }
}
