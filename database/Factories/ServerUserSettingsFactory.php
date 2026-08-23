<?php

namespace Database\Factories;

use App\Enums\ServerUserSettingKey;
use App\Models\ServerUserSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerUserSettingsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ServerUserSettings::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'settings' => [
                ServerUserSettingKey::ManualBackupNotifications->value => true,
                ServerUserSettingKey::ScheduledBackupNotifications->value => true,
            ],
        ];
    }

    /**
     * Indicate the user has opted in to all backup notifications.
     */
    public function optedIn(): static
    {
        return $this->state([
            'settings' => [
                ServerUserSettingKey::ManualBackupNotifications->value => true,
                ServerUserSettingKey::ScheduledBackupNotifications->value => true,
            ],
        ]);
    }

    /**
     * Indicate the user has opted out of all backup notifications.
     */
    public function optedOut(): static
    {
        return $this->state([
            'settings' => [
                ServerUserSettingKey::ManualBackupNotifications->value => false,
                ServerUserSettingKey::ScheduledBackupNotifications->value => false,
            ],
        ]);
    }
}
