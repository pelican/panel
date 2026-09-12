<?php

namespace App\Tests\Unit\Traits;

use App\Models\DatabaseHost;
use App\Models\Node;
use App\Models\WebhookConfiguration;
use App\Tests\TestCase;
use App\Traits\Filament\LogsAdminActivity;

class LogsAdminActivityTest extends TestCase
{
    use LogsAdminActivity;

    public function test_activity_key_camel_cases_the_model_basename(): void
    {
        $this->assertSame('node', self::activityKey(new Node()));
        $this->assertSame('databaseHost', self::activityKey(new DatabaseHost()));
    }

    public function test_activity_key_uses_overrides(): void
    {
        $this->assertSame('webhook', self::activityKey(new WebhookConfiguration()));
    }

    public function test_build_diff_returns_old_new_pairs_for_changed_keys_only(): void
    {
        $diff = self::buildDiff(
            ['name' => 'old-name', 'memory' => 1024, 'updated_at' => 'yesterday'],
            ['name' => 'new-name', 'memory' => 1024, 'updated_at' => 'today'],
        );

        $this->assertSame(['name' => ['old' => 'old-name', 'new' => 'new-name']], $diff);
    }

    public function test_build_diff_masks_secrets(): void
    {
        $diff = self::buildDiff(
            ['password' => 'old-hash', 'daemon_token' => 'abc'],
            ['password' => 'new-hash', 'daemon_token' => 'def'],
        );

        $this->assertSame('********', $diff['password']['old']);
        $this->assertSame('********', $diff['password']['new']);
        $this->assertSame('********', $diff['daemon_token']['new']);
    }

    public function test_redact_masks_matching_keys_and_leaves_others(): void
    {
        $this->assertSame('********', self::redact('client_secret', 'value'));
        $this->assertSame('********', self::redact('api_key', 'value'));
        $this->assertSame('********', self::redact('totp_secret', 'value'));
        $this->assertSame('plain', self::redact('name', 'plain'));
        $this->assertNull(self::redact('password', null));
    }

    public function test_identify_returns_identifying_attributes_only(): void
    {
        $node = new Node();
        $node->setRawAttributes(['id' => 4, 'name' => 'node-a', 'fqdn' => 'node.example.com', 'daemon_token' => 'secret']);

        $this->assertSame(['id' => 4, 'name' => 'node-a'], self::identify($node));
    }
}
