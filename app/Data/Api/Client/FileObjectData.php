<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final class FileObjectData extends ApiResource
{
    public function __construct(
        public ?string $name,
        public ?string $mode,
        public mixed $mode_bits,
        public ?int $size,
        public bool $is_file,
        public bool $is_symlink,
        public string $mimetype,
        public string $created_at,
        public string $modified_at,
    ) {}

    public static function getResourceName(): string
    {
        return 'file_object';
    }

    /**
     * @param  array<string, mixed>  $model
     */
    public static function fromModel(array $model): static
    {
        return new self(
            name: Arr::get($model, 'name'),
            mode: Arr::get($model, 'mode'),
            mode_bits: Arr::get($model, 'mode_bits'),
            size: Arr::get($model, 'size'),
            is_file: Arr::get($model, 'file', true),
            is_symlink: Arr::get($model, 'symlink', false),
            mimetype: Arr::get($model, 'mime', 'application/octet-stream'),
            created_at: Carbon::parse(Arr::get($model, 'created', ''))->toAtomString(),
            modified_at: Carbon::parse(Arr::get($model, 'modified', ''))->toAtomString(),
        );
    }
}
