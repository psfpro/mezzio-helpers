<?php

/**
 * @see       https://github.com/mezzio/mezzio-helpers for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-helpers/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-helpers/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Helper;

use Mezzio\Helper\ContentLengthMiddleware;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ContentLengthMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->response = $response = $this->prophesize(ResponseInterface::class);
        $this->request = $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $this->stream = $this->prophesize(StreamInterface::class);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->will([$response, 'reveal']);
        $this->handler = $handler->reveal();

        $this->middleware = new ContentLengthMiddleware();
    }

    public function testReturnsResponseVerbatimIfContentLengthHeaderPresent()
    {
        $this->response->hasHeader('Content-Length')->willReturn(true);
        $response = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response->reveal(), $response);
    }

    public function testReturnsResponseVerbatimIfContentLengthHeaderNotPresentAndBodySizeIsNull()
    {
        $this->stream->getSize()->willReturn(null);
        $this->response->hasHeader('Content-Length')->willReturn(false);
        $this->response->getBody()->will([$this->stream, 'reveal']);

        $response = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response->reveal(), $response);
    }

    public function testReturnsResponseWithContentLengthHeaderBasedOnBodySize()
    {
        $this->stream->getSize()->willReturn(42);
        $this->response->hasHeader('Content-Length')->willReturn(false);
        $this->response->getBody()->will([$this->stream, 'reveal']);
        $this->response->withHeader('Content-Length', '42')->will([$this->response, 'reveal']);

        $response = $this->middleware->process($this->request, $this->handler);
        $this->assertSame($this->response->reveal(), $response);
    }
}
