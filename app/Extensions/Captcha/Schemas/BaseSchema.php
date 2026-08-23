<?php

namespace App\Extensions\Captcha\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

abstract class BaseSchema
{
    abstract public function getId(): string;

    public function getName(): string
    {
        return Str::upper($this->getId());
    }

    /**
     * @return array<string, string|string[]|bool|null>
     */
    public function getConfig(): array
    {
        $id = Str::upper($this->getId());

        return [
            'site_key' => env("CAPTCHA_{$id}_SITE_KEY"),
            'secret_key' => env("CAPTCHA_{$id}_SECRET_KEY"),
        ];
    }

    /**
     * @return Component[]
     */
    public function getSettingsForm(): array
    {
        $id = Str::upper($this->getId());

        return [
            TextInput::make("CAPTCHA_{$id}_SITE_KEY")
                ->label(trans('admin/setting.captcha.site_key'))
                ->placeholder(trans('admin/setting.captcha.site_key'))
                ->columnSpan(2)
                ->required()
                ->password()
                ->revealable()
                ->autocomplete(false)
                ->default(env("CAPTCHA_{$id}_SITE_KEY")),
            TextInput::make("CAPTCHA_{$id}_SECRET_KEY")
                ->label(trans('admin/setting.captcha.secret_key'))
                ->placeholder(trans('admin/setting.captcha.secret_key'))
                ->columnSpan(2)
                ->required()
                ->password()
                ->revealable()
                ->autocomplete(false)
                ->default(env("CAPTCHA_{$id}_SECRET_KEY")),
        ];
    }
}
