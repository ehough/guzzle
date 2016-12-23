<?php
namespace Hough\Tests\Guzzle6;

use Hough\Guzzle6\Cookie\CookieJar;
use Hough\Guzzle6\Cookie\SetCookie;
use Hough\Guzzle6\Exception\RequestException;
use Hough\Guzzle6\Handler\MockHandler;
use Hough\Guzzle6\HandlerStack;
use Hough\Guzzle6\MessageFormatter;
use Hough\Guzzle6\Middleware;
use Hough\Promise\PromiseInterface;
use Hough\Psr7;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testAddsCookiesToRequests()
    {
        $jar = new CookieJar();
        $m = Middleware::cookies($jar);
        $h = new MockHandler(
            array(
                function (RequestInterface $request) {
                    return new Response(200, array(
                        'Set-Cookie' => new SetCookie(array(
                            'Name'   => 'name',
                            'Value'  => 'value',
                            'Domain' => 'foo.com'
                        ))
                    ));
                }
            )
        );
        $f = $m($h);
        $f(new Request('GET', 'http://foo.com'), array('cookies' => $jar))->wait();
        $this->assertCount(1, $jar);
    }

    /**
     * @expectedException \Hough\Guzzle6\Exception\ClientException
     */
    public function testThrowsExceptionOnHttpClientError()
    {
        $m = Middleware::httpErrors();
        $h = new MockHandler(array(new Response(404)));
        $f = $m($h);
        $p = $f(new Request('GET', 'http://foo.com'), array('http_errors' => true));
        $this->assertEquals('pending', $p->getState());
        $p->wait();
        $this->assertEquals('rejected', $p->getState());
    }

    /**
     * @expectedException \Hough\Guzzle6\Exception\ServerException
     */
    public function testThrowsExceptionOnHttpServerError()
    {
        $m = Middleware::httpErrors();
        $h = new MockHandler(array(new Response(500)));
        $f = $m($h);
        $p = $f(new Request('GET', 'http://foo.com'), array('http_errors' => true));
        $this->assertEquals('pending', $p->getState());
        $p->wait();
        $this->assertEquals('rejected', $p->getState());
    }

    /**
     * @dataProvider getHistoryUseCases
     */
    public function testTracksHistory($container)
    {
        $m = Middleware::history($container);
        $h = new MockHandler(array(new Response(200), new Response(201)));
        $f = $m($h);
        $p1 = $f(new Request('GET', 'http://foo.com'), array('headers' => array('foo' => 'bar')));
        $p2 = $f(new Request('HEAD', 'http://foo.com'), array('headers' => array('foo' => 'baz')));
        $p1->wait();
        $p2->wait();
        $this->assertCount(2, $container);
        $this->assertEquals(200, $container[0]['response']->getStatusCode());
        $this->assertEquals(201, $container[1]['response']->getStatusCode());
        $this->assertEquals('GET', $container[0]['request']->getMethod());
        $this->assertEquals('HEAD', $container[1]['request']->getMethod());
        $this->assertEquals('bar', $container[0]['options']['headers']['foo']);
        $this->assertEquals('baz', $container[1]['options']['headers']['foo']);
    }

    public function getHistoryUseCases()
    {
        return array(
            array(array()),                // 1. Container is an array
            array(new \ArrayObject()) // 2. Container is an ArrayObject
        );
    }

    public function testTracksHistoryForFailures()
    {
        $container = array();
        $m = Middleware::history($container);
        $request = new Request('GET', 'http://foo.com');
        $h = new MockHandler(array(new RequestException('error', $request)));
        $f = $m($h);
        $f($request, array())->wait(false);
        $this->assertCount(1, $container);
        $this->assertEquals('GET', $container[0]['request']->getMethod());
        $this->assertInstanceOf('\Hough\Guzzle6\Exception\RequestException', $container[0]['error']);
    }

    public function testTapsBeforeAndAfter()
    {
        $calls = array();
        $m = function ($handler) use (&$calls) {
            return function ($request, $options) use ($handler, &$calls) {
                $calls[] = '2';
                return $handler($request, $options);
            };
        };

        $m2 = Middleware::tap(
            function (RequestInterface $request, array $options) use (&$calls) {
                $calls[] = '1';
            },
            function (RequestInterface $request, array $options, PromiseInterface $p) use (&$calls) {
                $calls[] = '3';
            }
        );

        $h = new MockHandler(array(new Response()));
        $b = new HandlerStack($h);
        $b->push($m2);
        $b->push($m);
        $comp = $b->resolve();
        $p = $comp(new Request('GET', 'http://foo.com'), array());
        $this->assertEquals('123', implode('', $calls));
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $this->assertEquals(200, $p->wait()->getStatusCode());
    }

    public function testMapsRequest()
    {
        $assertEquals = array($this, 'assertEquals');
        $h = new MockHandler(array(
            function (RequestInterface $request, array $options) use ($assertEquals) {
                call_user_func($assertEquals, 'foo', $request->getHeaderLine('Bar'));
                return new Response(200);
            }
        ));
        $stack = new HandlerStack($h);
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('Bar', 'foo');
        }));
        $comp = $stack->resolve();
        $p = $comp(new Request('PUT', 'http://www.google.com'), array());
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
    }

    public function testMapsResponse()
    {
        $h = new MockHandler(array(new Response(200)));
        $stack = new HandlerStack($h);
        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            return $response->withHeader('Bar', 'foo');
        }));
        $comp = $stack->resolve();
        $p = $comp(new Request('PUT', 'http://www.google.com'), array());
        $p->wait();
        $this->assertEquals('foo', $p->wait()->getHeaderLine('Bar'));
    }

    public function testLogsRequestsAndResponses()
    {
        $h = new MockHandler(array(new Response(200)));
        $stack = new HandlerStack($h);
        $logger = new Logger();
        $formatter = new MessageFormatter();
        $stack->push(Middleware::log($logger, $formatter));
        $comp = $stack->resolve();
        $p = $comp(new Request('PUT', 'http://www.google.com'), array());
        $p->wait();
        $this->assertContains('"PUT / HTTP/1.1" 200', $logger->output);
    }

    public function testLogsRequestsAndResponsesCustomLevel()
    {
        $h = new MockHandler(array(new Response(200)));
        $stack = new HandlerStack($h);
        $logger = new Logger();
        $formatter = new MessageFormatter();
        $stack->push(Middleware::log($logger, $formatter, 'debug'));
        $comp = $stack->resolve();
        $p = $comp(new Request('PUT', 'http://www.google.com'), array());
        $p->wait();
        $this->assertContains('"PUT / HTTP/1.1" 200', $logger->output);
        $this->assertContains('[debug]', $logger->output);
    }

    public function testLogsRequestsAndErrors()
    {
        $h = new MockHandler(array(new Response(404)));
        $stack = new HandlerStack($h);
        $logger = new Logger();
        $formatter = new MessageFormatter('{code} {error}');
        $stack->push(Middleware::log($logger, $formatter));
        $stack->push(Middleware::httpErrors());
        $comp = $stack->resolve();
        $p = $comp(new Request('PUT', 'http://www.google.com'), array('http_errors' => true));
        $p->wait(false);
        $this->assertContains('PUT http://www.google.com', $logger->output);
        $this->assertContains('404 Not Found', $logger->output);
    }
}

/**
 * @internal
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;
    public $output;

    public function log($level, $message, array $context = array())
    {
        $this->output .= "[{$level}] {$message}\n";
    }
}
