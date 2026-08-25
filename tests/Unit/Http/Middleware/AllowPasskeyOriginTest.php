<?php

namespace App\Tests\Unit\Http\Middleware;

use App\Http\Middleware\AllowPasskeyOrigin;
use App\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class AllowPasskeyOriginTest extends TestCase
{
    /**
     * The origins configured before the middleware gets a say.
     */
    private const ConfiguredOrigins = ['https://panel.example.com'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('passkeys.relying_party_id', 'panel.example.com');
        config()->set('passkeys.allowed_origins', self::ConfiguredOrigins);
        config()->set('passkeys.strict_origin', false);
    }

    /**
     * Test that only an origin the relying party could have produced is allowed through.
     */
    #[DataProvider('originDataProvider')]
    public function test_origin_is_allowed_only_for_the_relying_party(string $url, ?string $origin, array $expected): void
    {
        $this->handleRequest($url, $origin);

        $this->assertSame($expected, config('passkeys.allowed_origins'));
    }

    /**
     * Provide a request URL and Origin header alongside the resulting allow list.
     */
    public static function originDataProvider(): array
    {
        return [
            'the configured origin stays a single entry' => [
                'https://panel.example.com/user/passkeys',
                'https://panel.example.com',
                ['https://panel.example.com'],
            ],
            'a non standard port on the relying party is added' => [
                'https://panel.example.com:8443/user/passkeys',
                'https://panel.example.com:8443',
                ['https://panel.example.com', 'https://panel.example.com:8443'],
            ],
            'a subdomain of the relying party is rejected' => [
                'https://evil.panel.example.com/user/passkeys',
                'https://evil.panel.example.com',
                ['https://panel.example.com'],
            ],
            'an unrelated domain is rejected' => [
                'https://evil.example.com/user/passkeys',
                'https://evil.example.com',
                ['https://panel.example.com'],
            ],
            'http on a public host is rejected' => [
                'http://panel.example.com/user/passkeys',
                'http://panel.example.com',
                ['https://panel.example.com'],
            ],
            'an origin the request was not routed to is rejected' => [
                'https://other.example.com/user/passkeys',
                'https://panel.example.com:8443',
                ['https://panel.example.com'],
            ],
            'a missing origin header falls back to the request' => [
                'https://panel.example.com:8443/user/passkeys',
                null,
                ['https://panel.example.com', 'https://panel.example.com:8443'],
            ],
        ];
    }

    /**
     * Test that a loopback relying party may run over http, since browsers treat it as a
     * secure context.
     */
    public function test_loopback_relying_party_is_allowed_over_http(): void
    {
        config()->set('passkeys.relying_party_id', 'localhost');
        config()->set('passkeys.allowed_origins', ['http://localhost']);

        $this->handleRequest('http://localhost:8000/user/passkeys', 'http://localhost:8000');

        $this->assertSame(['http://localhost', 'http://localhost:8000'], config('passkeys.allowed_origins'));
    }

    /**
     * Test that strict mode leaves the configured origins exactly as they were.
     */
    public function test_strict_origin_never_widens_the_allow_list(): void
    {
        config()->set('passkeys.strict_origin', true);

        $this->handleRequest('https://panel.example.com:8443/user/passkeys', 'https://panel.example.com:8443');

        $this->assertSame(self::ConfiguredOrigins, config('passkeys.allowed_origins'));
    }

    /**
     * Run a request through the middleware, asserting it was passed along the stack.
     */
    private function handleRequest(string $url, ?string $origin): void
    {
        $server = $origin === null ? [] : ['HTTP_ORIGIN' => $origin];

        $response = (new AllowPasskeyOrigin())->handle(
            Request::create($url, 'POST', [], [], [], $server),
            fn () => new Response(),
        );

        $this->assertInstanceOf(Response::class, $response);
    }
}
