<?php
namespace Hough\Test\Guzzle6\Handler;

use Hough\Guzzle6\Exception\ConnectException;
use Hough\Guzzle6\Handler\CurlHandler;
use Hough\Psr7;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Tests\Guzzle6\Server;

/**
 * @covers \Hough\Guzzle6\Handler\CurlHandler
 */
class CurlHandlerTest extends \PHPUnit_Framework_TestCase
{
    protected function getHandler($options = array())
    {
        return new CurlHandler($options);
    }

    /**
     * @expectedException \Hough\Guzzle6\Exception\ConnectException
     * @expectedExceptionMessage cURL
     */
    public function testCreatesCurlErrors()
    {
        $handler = new CurlHandler();
        $request = new Request('GET', 'http://localhost:123');
        $handler($request, array('timeout' => 0.001, 'connect_timeout' => 0.001))->wait();
    }

    public function testReusesHandles()
    {
        Server::flush();
        $response = new response(200);
        Server::enqueue(array($response, $response));
        $a = new CurlHandler();
        $request = new Request('GET', Server::$url);
        $a($request, array());
        $a($request, array());
    }

    public function testDoesSleep()
    {
        $response = new response(200);
        Server::enqueue(array($response));
        $a = new CurlHandler();
        $request = new Request('GET', Server::$url);
        $s = microtime(true);
        $a($request, array('delay' => 0.1))->wait();
        $this->assertGreaterThan(0.0001, microtime(true) - $s);
    }

    public function testCreatesCurlErrorsWithContext()
    {
        $handler = new CurlHandler();
        $request = new Request('GET', 'http://localhost:123');
        $called = false;
        $assertArrayHasKey = array($this, 'assertArrayHasKey');
        $p = $handler($request, array('timeout' => 0.001, 'connect_timeout' => 0.001))
            ->otherwise(function (ConnectException $e) use (&$called, $assertArrayHasKey) {
                $called = true;
                call_user_func($assertArrayHasKey, 'errno', $e->getHandlerContext());
            });
        $p->wait();
        $this->assertTrue($called);
    }

    public function testUsesContentLengthWhenOverInMemorySize()
    {
        Server::flush();
        Server::enqueue(array(new Response()));
        $stream = Psr7\stream_for(str_repeat('.', 1000000));
        $handler = new CurlHandler();
        $request = new Request(
            'PUT',
            Server::$url,
            array('Content-Length' => 1000000),
            $stream
        );
        $handler($request, array())->wait();
        $received = Server::received();
        $received = $received[0];
        $this->assertEquals(1000000, $received->getHeaderLine('Content-Length'));
        $this->assertFalse($received->hasHeader('Transfer-Encoding'));
    }
}
