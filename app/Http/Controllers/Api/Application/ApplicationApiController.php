<?php

namespace App\Http\Controllers\Api\Application;

use App\Data\Api\IncludeContext;
use App\Data\Api\PanelResponse;
use App\Http\Controllers\Controller;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

abstract class ApplicationApiController extends Controller
{
    protected Request $request;

    protected PanelResponse $response;

    /**
     * ApplicationApiController constructor.
     */
    public function __construct()
    {
        Container::getInstance()->call([$this, 'loadDependencies']);

        // Parse all the includes to use on this request.
        $input = $this->request->input('include', []);
        $input = is_array($input) ? $input : explode(',', $input);

        $includes = (new Collection($input))->map(function ($value) {
            return trim($value);
        })->filter()->toArray();

        $this->response->parseIncludes($includes);
        $this->response->limitRecursion(2);
    }

    /**
     * Perform dependency injection of certain classes needed for core functionality
     * without littering the constructors of classes that extend this abstract.
     */
    public function loadDependencies(PanelResponse $response, Request $request): void
    {
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * Returns the parsed includes for this request.
     *
     * @return array<array-key, mixed>
     */
    protected function parseIncludes(): array
    {
        $includes = $this->request->query('include') ?? [];

        if (!is_string($includes)) {
            return $includes;
        }

        return array_map(function ($item) {
            return trim($item);
        }, explode(',', $includes));
    }

    /**
     * The requested includes the API key may read, so list endpoints don't eager load
     * relations the response will null out anyway.
     *
     * @param  array<string, string>  $resources  include name => ACL resource it needs
     * @return string[]
     */
    protected function readableIncludes(array $resources): array
    {
        $context = new IncludeContext($this->request);

        return array_values(array_filter(
            array_intersect($this->parseIncludes(), array_keys($resources)),
            fn (string $include) => $context->allowsAdmin($resources[$include]),
        ));
    }

    /**
     * Return an HTTP/204 response for the API.
     */
    protected function returnNoContent(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Return an HTTP/406 response for the API.
     */
    protected function returnNotAcceptable(): Response
    {
        return new Response('', Response::HTTP_NOT_ACCEPTABLE);
    }
}
