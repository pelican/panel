<?php

namespace Database\Factories;

use App\Models\UserSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserSession>
 */
class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    public function definition(): array
    {
        return [
            'session_id' => Str::random(40),
            'token_hash' => UserSession::hashToken(Str::random(64)),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
            'remember' => true,
            'created_at' => now()->subDay(),
            'last_active_at' => now()->subHour(),
        ];
    }
}
