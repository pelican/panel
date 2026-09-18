<?php

namespace App\Tests\Unit\Observers;

use App\Models\DatabaseHost;
use App\Models\Node;
use App\Models\WebhookConfiguration;
use App\Observers\AuditObserver;
use App\Tests\TestCase;

class AuditObserverTest extends TestCase
{
    public function test_activity_key_camel_cases_the_model_basename(): void
    {
        $this->assertSame('node', AuditObserver::activityKey(new Node()));
        $this->assertSame('databaseHost', AuditObserver::activityKey(new DatabaseHost()));
    }

    public function test_activity_key_uses_overrides(): void
    {
        $this->assertSame('webhook', AuditObserver::activityKey(new WebhookConfiguration()));
    }

    public function test_build_diff_returns_old_new_pairs_for_changed_keys_only(): void
    {
        $diff = AuditObserver::buildDiff(
            ['name' => 'old-name', 'memory' => 1024, 'updated_at' => 'yesterday'],
            ['name' => 'new-name', 'memory' => 1024, 'updated_at' => 'today'],
        );

        $this->assertSame(['name' => ['old' => 'old-name', 'new' => 'new-name']], $diff);
    }

    public function test_build_diff_ignores_churn_attributes(): void
    {
        $diff = AuditObserver::buildDiff(
            ['remember_token' => 'a', 'last_used_at' => 'yesterday', 'updated_at' => 'yesterday'],
            ['remember_token' => 'b', 'last_used_at' => 'today', 'updated_at' => 'today'],
        );

        $this->assertSame([], $diff);
    }

    public function test_build_diff_masks_secrets(): void
    {
        $diff = AuditObserver::buildDiff(
            ['password' => 'old-hash', 'daemon_token' => 'abc'],
            ['password' => 'new-hash', 'daemon_token' => 'def'],
        );

        $this->assertSame('********', $diff['password']['old']);
        $this->assertSame('********', $diff['password']['new']);
        $this->assertSame('********', $diff['daemon_token']['new']);
    }

    public function test_redact_masks_matching_keys_and_leaves_others(): void
    {
        $this->assertSame('********', AuditObserver::redact('client_secret', 'value'));
        $this->assertSame('********', AuditObserver::redact('api_key', 'value'));
        $this->assertSame('********', AuditObserver::redact('totp_secret', 'value'));
        $this->assertSame('plain', AuditObserver::redact('name', 'plain'));
        $this->assertNull(AuditObserver::redact('password', null));
    }

    public function test_identify_returns_identifying_attributes_only(): void
    {
        $node = new Node();
        $node->setRawAttributes(['id' => 4, 'name' => 'node-a', 'fqdn' => 'node.example.com', 'daemon_token' => 'secret']);

        $this->assertSame(['id' => 4, 'name' => 'node-a'], AuditObserver::identify($node));
    }
}
