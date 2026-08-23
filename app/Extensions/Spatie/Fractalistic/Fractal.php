<?php

namespace App\Extensions\Spatie\Fractalistic;

use App\Extensions\League\Fractal\Serializers\PanelSerializer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Scope;
use League\Fractal\TransformerAbstract;
use Spatie\Fractal\Fractal as SpatieFractal;
use Spatie\Fractalistic\Exceptions\InvalidTransformation;
use Spatie\Fractalistic\Exceptions\NoTransformerSpecified;

class Fractal extends SpatieFractal
{
    /**
     * Create fractal data.
     *
     * @throws InvalidTransformation
     * @throws NoTransformerSpecified
     */
    public function createData(): Scope
    {
        // Set the serializer by default.
        if (empty($this->serializer)) {
            $this->serializer = new PanelSerializer();
        }

        // Automatically set the paginator on the response object if the
        // data being provided implements a paginator.
        if ($this->data instanceof LengthAwarePaginator) {
            $this->paginator = new IlluminatePaginatorAdapter($this->data);
        }

        // If the resource name is not set attempt to pull it off the transformer
        // itself and set it automatically.
        if (
            empty($this->resourceName)
            && $this->transformer instanceof TransformerAbstract
            && method_exists($this->transformer, 'getResourceName')
        ) {
            $this->resourceName = $this->transformer->getResourceName();
        }

        return parent::createData();
    }

    /**
     * Perform the transformation to array.
     *
     * @return array<mixed>
     *
     * @throws InvalidTransformation
     * @throws NoTransformerSpecified
     */
    public function toArray(): array
    {
        return parent::toArray() ?? [];
    }
}
