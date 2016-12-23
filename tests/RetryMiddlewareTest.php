<?php
namespace Hough\Guzzle\Test;

use Hough\Guzzle\Client;
use Hough\Guzzle\Handler\MockHandler;
use Hough\Guzzle\Middleware;
use Hough\Psr7;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Guzzle\RetryMiddleware;

class RetryMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testRetriesWhenDeciderReturnsTrue()
    {
        $delayCalls = 0;
        $calls = array();
        $decider = function ($retries, $request, $response, $error) use (&$calls) {
            $calls[] = func_get_args();
            return count($calls) < 3;
        };
        $assertEquals = array($this, 'assertEquals');
        $assertInstanceOf = array($this, 'assertInstanceOf');
        $delay = function ($retries, $response) use (&$delayCalls, $assertEquals, $assertInstanceOf) {
            $delayCalls++;
            call_user_func($assertEquals, $retries, $delayCalls);
            call_user_func($assertInstanceOf, '\Hough\Psr7\Response', $response);
            return 1;
        };
        $m = Middleware::retry($decider, $delay);
        $h = new MockHandler(array(new Response(200), new Response(201), new Response(202)));
        $f = call_user_func($m, $h);
        $c = new Client(array('handler' => $f));
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), array());
        $p->wait();
        $this->assertCount(3, $calls);
        $this->assertEquals(2, $delayCalls);
        $this->assertEquals(202, $p->wait()->getStatusCode());
    }

    public function testDoesNotRetryWhenDeciderReturnsFalse()
    {
        $decider = function () { return false; };
        $m = Middleware::retry($decider);
        $h = new MockHandler(array(new Response(200)));
        $c = new Client(array('handler' => call_user_func($m, $h)));
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), array());
        $this->assertEquals(200, $p->wait()->getStatusCode());
    }

    public function testCanRetryExceptions()
    {
        $calls = array();
        $decider = function ($retries, $request, $response, $error) use (&$calls) {
            $calls[] = func_get_args();
            return $error instanceof \Exception;
        };
        $m = Middleware::retry($decider);
        $h = new MockHandler(array(new \Exception(), new Response(201)));
        $c = new Client(array('handler' => call_user_func($m, $h)));
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), array());
        $this->assertEquals(201, $p->wait()->getStatusCode());
        $this->assertCount(2, $calls);
        $this->assertEquals(0, $calls[0][0]);
        $this->assertNull($calls[0][2]);
        $this->assertInstanceOf('Exception', $calls[0][3]);
        $this->assertEquals(1, $calls[1][0]);
        $this->assertInstanceOf('\Hough\Psr7\Response', $calls[1][2]);
        $this->assertNull($calls[1][3]);
    }

    public function testBackoffCalculateDelay()
    {
        $this->assertEquals(0, RetryMiddleware::exponentialDelay(0));
        $this->assertEquals(1, RetryMiddleware::exponentialDelay(1));
        $this->assertEquals(2, RetryMiddleware::exponentialDelay(2));
        $this->assertEquals(4, RetryMiddleware::exponentialDelay(3));
        $this->assertEquals(8, RetryMiddleware::exponentialDelay(4));
    }
}
