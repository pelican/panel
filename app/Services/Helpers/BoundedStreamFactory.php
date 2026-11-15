<?php

namespace App\Services\Helpers;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Guzzle's `stream_factory` for a download with a size cap. Guzzle's curl handler writes the response body into
 * a stream it gets from createStreamFromResource(); that stream refuses the write that would take it past
 * $maxBytes, which makes curl stop the transfer right there instead of downloading the rest (R2.4).
 */
final class BoundedStreamFactory implements StreamFactoryInterface
{
    private readonly HttpFactory $factory;

    public function __construct(private readonly int $maxBytes)
    {
        $this->factory = new HttpFactory();
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return $this->factory->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->factory->createStreamFromFile($filename, $mode);
    }

    /** @param  resource  $resource */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new class($this->factory->createStreamFromResource($resource), $this->maxBytes) implements StreamInterface
        {
            use StreamDecoratorTrait;

            private int $written = 0;

            public function __construct(private StreamInterface $stream, private readonly int $maxBytes) {}

            public function write(string $string): int
            {
                if ($this->written + strlen($string) > $this->maxBytes) {
                    throw new RuntimeException(PluginRegistryService::TOO_LARGE);
                }

                $written = $this->stream->write($string);
                $this->written += $written;

                return $written;
            }
        };
    }
}
