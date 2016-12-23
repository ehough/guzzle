<?php
namespace Hough\Test\Guzzle6\Handler;

use Hough\Guzzle6\Handler\MockHandler;
use Hough\Promise\PromiseInterface;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Guzzle6\TransferStats;

/**
 * @covers \Hough\Guzzle6\Handler\MockHandler
 */
class MockHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testReturnsMockResponse()
    {
        $res = new Response();
        $mock = new MockHandler(array($res));
        $request = new Request('GET', 'http://example.com');
        $p = call_user_func($mock, $request, array());
        $this->assertSame($res, $p->wait());
    }

    public function testIsCountable()
    {
        $res = new Response();
        $mock = new MockHandler(array($res, $res));
        $this->assertCount(2, $mock);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresEachAppendIsValid()
    {
        $mock = new MockHandler(array('a'));
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array());
    }

    public function testCanQueueExceptions()
    {
        $e = new \Exception('a');
        $mock = new MockHandler(array($e));
        $request = new Request('GET', 'http://example.com');
        $p = call_user_func($mock, $request, array());
        try {
            $p->wait();
            $this->fail();
        } catch (\Exception $e2) {
            $this->assertSame($e, $e2);
        }
    }

    public function testCanGetLastRequestAndOptions()
    {
        $res = new Response();
        $mock = new MockHandler(array($res));
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array('foo' => 'bar'));
        $this->assertSame($request, $mock->getLastRequest());
        $this->assertEquals(array('foo' => 'bar'), $mock->getLastOptions());
    }

    public function testSinkFilename()
    {
        $filename = sys_get_temp_dir().'/mock_test_'.uniqid();
        $res = new Response(200, array(), 'TEST CONTENT');
        $mock = new MockHandler(array($res));
        $request = new Request('GET', '/');
        $p = call_user_func($mock, $request, array('sink' => $filename));
        $p->wait();

        $this->assertFileExists($filename);
        $this->assertEquals('TEST CONTENT', file_get_contents($filename));

        unlink($filename);
    }

    public function testSinkResource()
    {
        $file = tmpfile();
        $meta = stream_get_meta_data($file);
        $res = new Response(200, array(), 'TEST CONTENT');
        $mock = new MockHandler(array($res));
        $request = new Request('GET', '/');
        $p = call_user_func($mock, $request, array('sink' => $file));
        $p->wait();

        $this->assertFileExists($meta['uri']);
        $this->assertEquals('TEST CONTENT', file_get_contents($meta['uri']));
    }

    public function testSinkStream()
    {
        $stream = new \Hough\Psr7\Stream(tmpfile());
        $res = new Response(200, array(), 'TEST CONTENT');
        $mock = new MockHandler(array($res));
        $request = new Request('GET', '/');
        $p = call_user_func($mock, $request, array('sink' => $stream));
        $p->wait();

        $this->assertFileExists($stream->getMetadata('uri'));
        $this->assertEquals('TEST CONTENT', file_get_contents($stream->getMetadata('uri')));
    }

    public function testCanEnqueueCallables()
    {
        $r = new Response();
        $fn = function ($req, $o) use ($r) { return $r; };
        $mock = new MockHandler(array($fn));
        $request = new Request('GET', 'http://example.com');
        $p = call_user_func($mock, $request, array('foo' => 'bar'));
        $this->assertSame($r, $p->wait());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresOnHeadersIsCallable()
    {
        $res = new Response();
        $mock = new MockHandler(array($res));
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array('on_headers' => 'error!'));
    }

    /**
     * @expectedException \Hough\Guzzle6\Exception\RequestException
     * @expectedExceptionMessage An error was encountered during the on_headers event
     * @expectedExceptionMessage test
     */
    public function testRejectsPromiseWhenOnHeadersFails()
    {
        $res = new Response();
        $mock = new MockHandler(array($res));
        $request = new Request('GET', 'http://example.com');
        $promise = call_user_func($mock, $request, array(
            'on_headers' => function () {
                throw new \Exception('test');
            }
        ));

        $promise->wait();
    }
    public function testInvokesOnFulfilled()
    {
        $res = new Response();
        $mock = new MockHandler(array($res), function ($v) use (&$c) {
            $c = $v;
        });
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array())->wait();
        $this->assertSame($res, $c);
    }

    public function testInvokesOnRejected()
    {
        $e = new \Exception('a');
        $c = null;
        $mock = new MockHandler(array($e), null, function ($v) use (&$c) { $c = $v; });
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array())->wait(false);
        $this->assertSame($e, $c);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testThrowsWhenNoMoreResponses()
    {
        $mock = new MockHandler();
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array());
    }

    /**
     * @expectedException \Hough\Guzzle6\Exception\BadResponseException
     */
    public function testCanCreateWithDefaultMiddleware()
    {
        $r = new Response(500);
        $mock = MockHandler::createWithMiddleware(array($r));
        $request = new Request('GET', 'http://example.com');
        call_user_func($mock, $request, array('http_errors' => true))->wait();
    }

    public function testInvokesOnStatsFunctionForResponse()
    {
        $res = new Response();
        $mock = new MockHandler(array($res));
        $request = new Request('GET', 'http://example.com');
        $stats = null;
        $onStats = function (TransferStats $s) use (&$stats) {
            $stats = $s;
        };
        $p = call_user_func($mock, $request, array('on_stats' => $onStats));
        $p->wait();
        $this->assertSame($res, $stats->getResponse());
        $this->assertSame($request, $stats->getRequest());
    }

    public function testInvokesOnStatsFunctionForError()
    {
        $e = new \Exception('a');
        $c = null;
        $mock = new MockHandler(array($e), null, function ($v) use (&$c) { $c = $v; });
        $request = new Request('GET', 'http://example.com');
        $stats = null;
        $onStats = function (TransferStats $s) use (&$stats) {
            $stats = $s;
        };
        call_user_func($mock, $request, array('on_stats' => $onStats))->wait(false);
        $this->assertSame($e, $stats->getHandlerErrorData());
        $this->assertSame(null, $stats->getResponse());
        $this->assertSame($request, $stats->getRequest());
    }
}
