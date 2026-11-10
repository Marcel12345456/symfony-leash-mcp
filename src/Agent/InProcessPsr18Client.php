<?php

declare(strict_types=1);

namespace App\Agent;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * PSR-18 client that hands each request to this app's kernel, so agents in a console command reach the MCP endpoint
 * through the full HTTP stack (firewall, controller, transport) without a running web server.
 */
final readonly class InProcessPsr18Client implements ClientInterface
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private Psr17Factory $factory = new Psr17Factory(),
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $server = ['REMOTE_ADDR' => '127.0.0.1'];
        foreach ($request->getHeaders() as $name => $values) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = implode(', ', $values);
        }
        $symfonyRequest = Request::create((string) $request->getUri(), $request->getMethod(), server: $server, content: (string) $request->getBody());

        $response = $this->kernel->handle($symfonyRequest);
        if ($response instanceof StreamedResponse) {
            ob_start();
            $response->sendContent();
            $content = (string) ob_get_clean();
        } else {
            $content = (string) $response->getContent();
        }

        $psr = $this->factory->createResponse($response->getStatusCode())->withBody($this->factory->createStream($content));
        foreach ($response->headers->all() as $name => $values) {
            $psr = $psr->withHeader($name, array_values(array_filter($values, \is_string(...))));
        }

        return $psr;
    }
}
