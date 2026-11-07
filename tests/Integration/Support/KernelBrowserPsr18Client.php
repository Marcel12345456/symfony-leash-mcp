<?php

declare(strict_types=1);

namespace App\Tests\Integration\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** PSR-18 client that sends requests into the kernel in-process, so mcp/sdk's HttpTransport needs no server. */
final readonly class KernelBrowserPsr18Client implements ClientInterface
{
    public function __construct(
        private KernelBrowser $browser,
        private Psr17Factory $factory = new Psr17Factory(),
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $server = [];
        foreach ($request->getHeaders() as $name => $values) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = implode(', ', $values);
        }

        $this->browser->request($request->getMethod(), (string) $request->getUri(), server: $server, content: (string) $request->getBody());

        // The internal response holds the content of streamed (SSE) responses too.
        $internal = $this->browser->getInternalResponse();
        $response = $this->factory->createResponse($internal->getStatusCode())->withBody($this->factory->createStream($internal->getContent()));
        foreach ($internal->getHeaders() as $name => $values) {
            $response = $response->withHeader($name, array_values(array_filter((array) $values, \is_string(...))));
        }

        return $response;
    }
}
