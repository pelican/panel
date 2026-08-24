<?php

namespace App\Data\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Drop-in replacement for the injected Fractal wrapper. Call sites keep the same
 * fluent chain, item or collection then transformWith then toArray or respond, but
 * transformWith now takes an ApiResource class string instead of a transformer.
 *
 * Include parsing mirrors League Fractal: dot paths are trimmed to the recursion
 * limit, parents of nested paths are implied, endpoint parseIncludes calls merge
 * with what the request asked for, and addMeta blocks union with first key winning.
 */
class PanelResponse
{
    protected mixed $data = null;

    protected bool $isCollection = false;

    /** @var class-string<ApiResource>|null */
    protected ?string $dataClass = null;

    /** @var string[] */
    protected array $rawIncludes = [];

    protected int $recursionLimit = 10;

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct(protected Request $request) {}

    public function item(mixed $data): static
    {
        $this->data = $data;
        $this->isCollection = false;

        return $this;
    }

    public function collection(mixed $data): static
    {
        $this->data = $data;
        $this->isCollection = true;

        return $this;
    }

    /**
     * @param  class-string<ApiResource>  $dataClass
     */
    public function transformWith(string $dataClass): static
    {
        $this->dataClass = $dataClass;

        return $this;
    }

    /**
     * @param  string[]|string  $includes
     */
    public function parseIncludes(array|string $includes): static
    {
        $includes = is_string($includes) ? explode(',', $includes) : $includes;

        $this->rawIncludes = array_merge($this->rawIncludes, $includes);

        return $this;
    }

    public function limitRecursion(int $limit): static
    {
        $this->recursionLimit = $limit;

        return $this;
    }

    /**
     * Union semantics matching Fractalistic: a key set by an earlier call is not
     * overwritten by a later one.
     *
     * @param  array<string, mixed>  ...$blocks
     */
    public function addMeta(array ...$blocks): static
    {
        foreach ($blocks as $block) {
            $this->meta += $block;
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $context = new IncludeContext($this->request, $this->requestedIncludes());

        $meta = $this->meta;

        if ($this->data instanceof LengthAwarePaginator) {
            $envelope = Envelope::collection($this->data->items(), $this->dataClass, $context);
            $meta['pagination'] = $this->pagination($this->data);
        } elseif (is_null($this->data)) {
            $envelope = $context->null();
        } elseif ($this->isCollection) {
            $envelope = Envelope::collection($this->data, $this->dataClass, $context);
        } else {
            $envelope = Envelope::item($this->data, $this->dataClass, $context);
        }

        if ($meta !== []) {
            $envelope['meta'] = $meta;
        }

        return $envelope;
    }

    public function respond(int $status = 200): JsonResponse
    {
        return new JsonResponse($this->toArray(), $status);
    }

    /**
     * Normalize the raw include names the way League parsed them: strip parameter
     * suffixes, trim each dot path to the recursion limit, and imply every parent
     * of a nested path.
     *
     * @return string[]
     */
    protected function requestedIncludes(): array
    {
        $paths = [];

        foreach ($this->rawIncludes as $include) {
            $name = trim(explode(':', $include, 2)[0]);
            if ($name === '') {
                continue;
            }

            $segments = array_slice(explode('.', $name), 0, $this->recursionLimit);
            for ($depth = 1; $depth <= count($segments); $depth++) {
                $paths[] = implode('.', array_slice($segments, 0, $depth));
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The exact meta.pagination block League's ArraySerializer produced, including
     * the empty object when there are no previous or next links.
     *
     * @return array<string, mixed>
     */
    protected function pagination(LengthAwarePaginator $paginator): array
    {
        $pagination = [
            'total' => $paginator->total(),
            'count' => count($paginator->items()),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_pages' => $paginator->lastPage(),
        ];

        $links = [];
        if ($paginator->currentPage() > 1) {
            $links['previous'] = $paginator->url($paginator->currentPage() - 1);
        }
        if ($paginator->currentPage() < $paginator->lastPage()) {
            $links['next'] = $paginator->url($paginator->currentPage() + 1);
        }

        $pagination['links'] = $links === [] ? (object) [] : $links;

        return $pagination;
    }
}
