<?php
namespace Hough\Guzzle\Test;

use Hough\Guzzle\Handler\MockHandler;
use Hough\Guzzle\HandlerStack;
use Hough\Guzzle\Middleware;
use Hough\Promise\PromiseInterface;
use Hough\Psr7;
use Hough\Psr7\FnStream;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class PrepareBodyMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testAddsContentLengthWhenMissingAndPossible()
    {
        $assertEquals = array($this, 'assertEquals');
        $h = new MockHandler(array(
            function (RequestInterface $request) use ($assertEquals) {
                call_user_func($assertEquals, 3, $request->getHeaderLine('Content-Length'));
                return new Response(200);
            }
        ));
        $m = Middleware::prepareBody();
        $stack = new HandlerStack($h);
        $stack->push($m);
        $comp = $stack->resolve();
        $p = call_user_func($comp, new Request('PUT', 'http://www.google.com', array(), '123'), array());
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $response = $p->wait();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddsTransferEncodingWhenNoContentLength()
    {
        $body = FnStream::decorate(Psr7\stream_for('foo'), array(
            'getSize' => function () { return null; }
        ));
        $assertEquals = array($this, 'assertEquals');
        $assertFalse  = array($this, 'assertFalse');
        $h = new MockHandler(array(
            function (RequestInterface $request) use ($assertEquals, $assertFalse) {
                call_user_func($assertFalse, $request->hasHeader('Content-Length'));
                call_user_func($assertEquals, 'chunked', $request->getHeaderLine('Transfer-Encoding'));
                return new Response(200);
            }
        ));
        $m = Middleware::prepareBody();
        $stack = new HandlerStack($h);
        $stack->push($m);
        $comp = $stack->resolve();
        $p = call_user_func($comp, new Request('PUT', 'http://www.google.com', array(), $body), array());
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $response = $p->wait();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddsContentTypeWhenMissingAndPossible()
    {
        $bd = Psr7\stream_for(fopen(__DIR__ . '/../composer.json', 'r'));
        $assertEquals = array($this, 'assertEquals');
        $assertTrue   = array($this, 'assertTrue');
        $h = new MockHandler(array(
            function (RequestInterface $request) use ($assertEquals, $assertTrue) {
                call_user_func($assertEquals, 'application/json', $request->getHeaderLine('Content-Type'));
                call_user_func($assertTrue, $request->hasHeader('Content-Length'));
                return new Response(200);
            }
        ));
        $m = Middleware::prepareBody();
        $stack = new HandlerStack($h);
        $stack->push($m);
        $comp = $stack->resolve();
        $p = call_user_func($comp, new Request('PUT', 'http://www.google.com', array(), $bd), array());
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $response = $p->wait();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function expectProvider()
    {
        return array(
            array(true, array('100-Continue')),
            array(false, array()),
            array(10, array('100-Continue')),
            array(500000, array())
        );
    }

    /**
     * @dataProvider expectProvider
     */
    public function testAddsExpect($value, $result)
    {
        $bd = Psr7\stream_for(fopen(__DIR__ . '/../composer.json', 'r'));

        $assertEquals = array($this, 'assertEquals');
        $h = new MockHandler(array(
            function (RequestInterface $request) use ($result, $assertEquals) {
                call_user_func($assertEquals, $result, $request->getHeader('Expect'));
                return new Response(200);
            }
        ));

        $m = Middleware::prepareBody();
        $stack = new HandlerStack($h);
        $stack->push($m);
        $comp = $stack->resolve();
        $p = call_user_func($comp, new Request('PUT', 'http://www.google.com', array(), $bd), array(
            'expect' => $value
        ));
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $response = $p->wait();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIgnoresIfExpectIsPresent()
    {
        $bd = Psr7\stream_for(fopen(__DIR__ . '/../composer.json', 'r'));
        $assertEquals = array($this, 'assertEquals');
        $h = new MockHandler(array(
            function (RequestInterface $request) use ($assertEquals) {
                call_user_func($assertEquals, array('Foo'), $request->getHeader('Expect'));
                return new Response(200);
            }
        ));

        $m = Middleware::prepareBody();
        $stack = new HandlerStack($h);
        $stack->push($m);
        $comp = $stack->resolve();
        $p = call_user_func($comp,
            new Request('PUT', 'http://www.google.com', array('Expect' => 'Foo'), $bd),
            array('expect' => true)
        );
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $response = $p->wait();
        $this->assertEquals(200, $response->getStatusCode());
    }
}
