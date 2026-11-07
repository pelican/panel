<?php

use App\Extensions\OAuth\OAuthService;
use App\Extensions\OAuth\Schemas\OAuthSchema;
use SocialiteProviders\Manager\SocialiteWasCalled;

it('does not crash the app when a socialite provider class is missing', function () {
    app(OAuthService::class)->register(new class extends OAuthSchema
    {
        public function getId(): string
        {
            return 'missing';
        }

        public function getSocialiteProvider(): string
        {
            return 'Missing\\Provider';
        }
    });

    // Fired on every boot by the socialite manager, a missing plugin package used to throw here
    event(app(SocialiteWasCalled::class));

    expect(app(OAuthService::class)->get('missing'))->not->toBeNull();
});
