<?php

use App\Extensions\Dedoc\Scramble\FractalResponseTypeInfer;
use App\Extensions\Dedoc\Scramble\TransformerFactoryTypeInfer;
use App\Extensions\Dedoc\Scramble\TransformerModelBindingExtension;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

pest()->group('API');

covers(FractalResponseTypeInfer::class);
covers(TransformerFactoryTypeInfer::class);
covers(TransformerModelBindingExtension::class);

/**
 * The Scramble extensions reach into internals that move between releases, so these cover the
 * shapes we would otherwise only notice were broken by opening the docs by hand.
 */
function generateDocument(string $api): array
{
    return app(Generator::class)(Scramble::getGeneratorConfig($api));
}

it('generates the application and client documents', function (string $api) {
    $document = generateDocument($api);

    expect($document['openapi'])->toStartWith('3.')
        ->and($document['paths'])->not->toBeEmpty();
})->with(['application', 'client']);

it('documents the transformed collection envelope with pagination', function () {
    $schema = generateDocument('application')['paths']['/servers']['get']['responses'][200]['content']['application/json']['schema'];

    // Fractal attaches the paginator itself, so the block is part of every listing response.
    expect($schema['properties']['object'])->toMatchArray(['type' => 'string', 'const' => 'list'])
        ->and($schema['properties']['data']['items']['properties'])->toHaveKeys(['object', 'attributes'])
        ->and($schema['properties']['data']['items']['properties']['attributes']['properties'])->toHaveKeys(['uuid', 'name'])
        ->and($schema['properties']['meta']['properties']['pagination']['properties'])
        ->toHaveKeys(['total', 'count', 'per_page', 'current_page', 'total_pages', 'links']);
});

it('documents the meta block each addMeta() call contributes to', function () {
    $document = generateDocument('client');

    $item = $document['paths']['/servers/{server}']['get']['responses'][200]['content']['application/json']['schema'];

    expect($item['properties']['meta']['properties'])->toHaveKeys(['is_server_owner', 'user_permissions']);

    // A paginated listing gets the pagination block Fractal adds plus whatever the controller asked for.
    $collection = $document['paths']['/servers/{server}/backups']['get']['responses'][200]['content']['application/json']['schema'];

    expect($collection['properties']['meta']['properties'])->toHaveKeys(['pagination', 'backup_count']);
});

it('documents the transformed item envelope without pagination', function () {
    $schema = generateDocument('application')['paths']['/servers/{server}']['get']['responses'][200]['content']['application/json']['schema'];

    expect($schema['properties']['object'])->toMatchArray(['type' => 'string', 'const' => 'server'])
        ->and($schema['properties']['attributes']['properties'])->toHaveKeys(['uuid', 'name'])
        ->and($schema['properties'])->not->toHaveKey('meta');
});

it('describes request body fields from the rules they belong to', function () {
    $schemas = generateDocument('application')['components']['schemas'];

    expect($schemas['StoreServerRequest']['properties']['limits']['properties']['memory']['description'])
        ->toBe('Memory the server may use, in MiB. Use `0` for unlimited.')
        // Descriptions given as attributes, for rules that come straight off a model.
        ->and($schemas['StoreUserRequest']['properties']['email']['description'])->not->toBeEmpty()
        // The deprecated flat build fields are gone entirely, not merely marked.
        ->and($schemas['UpdateServerBuildConfigurationRequest']['properties'])->not->toHaveKey('memory');
});

it('leaves operation descriptions as they are written in the controller', function () {
    $operation = generateDocument('application')['paths']['/servers']['get'];

    expect($operation['summary'])->toBe('List servers')
        ->and($operation['description'])->toBe('Return all the servers that currently exist on the Panel.');
});
