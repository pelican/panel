<?php

namespace App\Services\Helpers;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Guzzle's `stream_factory` for a streamed download with a deadline: every read from the connection waits at most
 * until the deadline. Guzzle bounds the body of a streamed response only by its idle timeout (60 seconds by
 * default), so a server sending a byte now and then could otherwise hold one read open well past the deadline.
 */
final class DeadlineStreamFactory implements StreamFactoryInterface
{
    private readonly HttpFactory $factory;

    /** @param  float  $deadline  in PluginRegistryService::now() seconds */
    public function __construct(private readonly float $deadline)
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
        return new class($this->factory->createStreamFromResource($resource), $resource, $this->deadline) implements StreamInterface
        {
            use StreamDecoratorTrait;

            /** @param  resource  $resource */
            public function __construct(private StreamInterface $stream, private $resource, private readonly float $deadline) {}

            public function read(int $length): string
            {
                $remaining = $this->deadline - PluginRegistryService::now();

                if ($remaining <= 0) {
                    throw new TimeoutException('Unable to read from stream: timed out');
                }

                // A read that times out makes the stream throw a TimeoutException itself.
                stream_set_timeout($this->resource, (int) $remaining, (int) (fmod($remaining, 1) * 1_000_000));

                return $this->stream->read($length);
            }
        };
    }
}
