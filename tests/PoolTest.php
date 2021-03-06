<?php
namespace Hough\Guzzle\Test;

use Hough\Guzzle\Exception\ClientException;
use Hough\Guzzle\Handler\MockHandler;
use Hough\Guzzle\HandlerStack;
use Hough\Guzzle\Pool;
use Hough\Guzzle\Client;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Promise\Promise;
use Psr\Http\Message\RequestInterface;

class PoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesIterable()
    {
        $p = new Pool(new Client(), 'foo');
        $p->promise()->wait();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesEachElement()
    {
        $c = new Client();
        $requests = array('foo');
        $p = new Pool($c, new \ArrayIterator($requests));
        $p->promise()->wait();
    }

    public function testSendsAndRealizesFuture()
    {
        $c = $this->getClient();
        $p = new Pool($c, array(new Request('GET', 'http://example.com')));
        $p->promise()->wait();
    }

    public function testExecutesPendingWhenWaiting()
    {
        $r1 = new Promise(function () use (&$r1) { $r1->resolve(new Response()); });
        $r2 = new Promise(function () use (&$r2) { $r2->resolve(new Response()); });
        $r3 = new Promise(function () use (&$r3) { $r3->resolve(new Response()); });
        $handler = new MockHandler(array($r1, $r2, $r3));
        $c = new Client(array('handler' => $handler));
        $p = new Pool($c, array(
            new Request('GET', 'http://example.com'),
            new Request('GET', 'http://example.com'),
            new Request('GET', 'http://example.com'),
        ), array('pool_size' => 2));
        $p->promise()->wait();
    }

    public function testUsesRequestOptions()
    {
        $h = array();
        $handler = new MockHandler(array(
            function (RequestInterface $request) use (&$h) {
                $h[] = $request;
                return new Response();
            }
        ));
        $c = new Client(array('handler' => $handler));
        $opts = array('options' => array('headers' => array('x-foo' => 'bar')));
        $p = new Pool($c, array(new Request('GET', 'http://example.com')), $opts);
        $p->promise()->wait();
        $this->assertCount(1, $h);
        $this->assertTrue($h[0]->hasHeader('x-foo'));
    }

    public function testCanProvideCallablesThatReturnResponses()
    {
        $h = array();
        $handler = new MockHandler(array(
            function (RequestInterface $request) use (&$h) {
                $h[] = $request;
                return new Response();
            }
        ));
        $c = new Client(array('handler' => $handler));
        $optHistory = array();
        $fn = function (array $opts) use (&$optHistory, $c) {
            $optHistory = $opts;
            return $c->request('GET', 'http://example.com', $opts);
        };
        $opts = array('options' => array('headers' => array('x-foo' => 'bar')));
        $p = new Pool($c, array($fn), $opts);
        $p->promise()->wait();
        $this->assertCount(1, $h);
        $this->assertTrue($h[0]->hasHeader('x-foo'));
    }

    public function testBatchesResults()
    {
        $requests = array(
            new Request('GET', 'http://foo.com/200'),
            new Request('GET', 'http://foo.com/201'),
            new Request('GET', 'http://foo.com/202'),
            new Request('GET', 'http://foo.com/404'),
        );
        $fn = function (RequestInterface $request) {
            return new Response(substr($request->getUri()->getPath(), 1));
        };
        $mock = new MockHandler(array($fn, $fn, $fn, $fn));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler));
        $results = Pool::batch($client, $requests);
        $this->assertCount(4, $results);
        $this->assertEquals(array(0, 1, 2, 3), array_keys($results));
        $this->assertEquals(200, $results[0]->getStatusCode());
        $this->assertEquals(201, $results[1]->getStatusCode());
        $this->assertEquals(202, $results[2]->getStatusCode());
        $this->assertInstanceOf('\Hough\Guzzle\Exception\ClientException', $results[3]);
    }

    public function testBatchesResultsWithCallbacks()
    {
        $requests = array(
            new Request('GET', 'http://foo.com/200'),
            new Request('GET', 'http://foo.com/201')
        );
        $mock = new MockHandler(array(
            function (RequestInterface $request) {
                return new Response(substr($request->getUri()->getPath(), 1));
            }
        ));
        $client = new Client(array('handler' => $mock));
        $results = Pool::batch($client, $requests, array(
            'fulfilled' => function ($value) use (&$called) { $called = true; }
        ));
        $this->assertCount(2, $results);
        $this->assertTrue($called);
    }

    public function testUsesYieldedKeyInFulfilledCallback()
    {
        $r1 = new Promise(function () use (&$r1) { $r1->resolve(new Response()); });
        $r2 = new Promise(function () use (&$r2) { $r2->resolve(new Response()); });
        $r3 = new Promise(function () use (&$r3) { $r3->resolve(new Response()); });
        $handler = new MockHandler(array($r1, $r2, $r3));
        $c = new Client(array('handler' => $handler));
        $keys = array();
        $requests = array(
            'request_1' => new Request('GET', 'http://example.com'),
            'request_2' => new Request('GET', 'http://example.com'),
            'request_3' => new Request('GET', 'http://example.com'),
        );
        $p = new Pool($c, $requests, array(
            'pool_size' => 2,
            'fulfilled' => function($res, $index) use (&$keys) { $keys[] = $index; }
        ));
        $p->promise()->wait();
        $this->assertCount(3, $keys);
        $this->assertSame($keys, array_keys($requests));
    }

    private function getClient($total = 1)
    {
        $queue = array();
        for ($i = 0; $i < $total; $i++) {
            $queue[] = new Response();
        }
        $handler = new MockHandler($queue);
        return new Client(array('handler' => $handler));
    }
}
