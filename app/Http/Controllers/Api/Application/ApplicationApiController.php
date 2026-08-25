<?php

namespace App\Http\Controllers\Api\Application;

use App\Data\Api\PanelResponse;
use App\Extensions\Spatie\Fractalistic\Fractal;
use App\Http\Controllers\Controller;
use App\Transformers\Api\Application\BaseTransformer;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Webmozart\Assert\Assert;

abstract class ApplicationApiController extends Controller
{
    protected Request $request;

    protected Fractal $fractal;

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

        $this->fractal->parseIncludes($includes);
        $this->fractal->limitRecursion(2);

        $this->response->parseIncludes($includes);
        $this->response->limitRecursion(2);
    }

    /**
     * Perform dependency injection of certain classes needed for core functionality
     * without littering the constructors of classes that extend this abstract.
     */
    public function loadDependencies(Fractal $fractal, PanelResponse $response, Request $request): void
    {
        $this->fractal = $fractal;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * Return an instance of an application transformer.
     *
     * @template T of \App\Transformers\Api\Application\BaseTransformer
     *
     * @param  class-string<T>  $abstract
     * @return T
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function getTransformer(string $abstract)
    {
        Assert::subclassOf($abstract, BaseTransformer::class);

        return $abstract::fromRequest($this->request);
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
