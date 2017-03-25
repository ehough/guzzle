<?php
namespace Hough\Test\Guzzle\Handler;

use Hough\Guzzle\Exception\ConnectException;
use Hough\Guzzle\Handler\StreamHandler;
use Hough\Guzzle\RequestOptions;
use Hough\Psr7;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Psr7\FnStream;
use Hough\Guzzle\Test\Server;
use Hough\Guzzle\TransferStats;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \Hough\Guzzle\Handler\StreamHandler
 */
class StreamHandlerTest extends \PHPUnit_Framework_TestCase
{
    private function queueRes()
    {
        Server::flush();
        Server::enqueue(array(
            new Response(200, array(
                'Foo' => 'Bar',
                'Content-Length' => 8,
            ), 'hi there')
        ));
    }

    public function testReturnsResponseForSuccessfulRequest()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $response = call_user_func($handler, 
            new Request('GET', Server::$url, array('Foo' => 'Bar')),
            array()
        )->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('Bar', $response->getHeaderLine('Foo'));
        $this->assertEquals('8', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('hi there', (string) $response->getBody());
        $received = Server::received();
        $sent = $received[0];
        $this->assertEquals('GET', $sent->getMethod());
        $this->assertEquals('/', $sent->getUri()->getPath());
        $this->assertEquals('127.0.0.1:8126', $sent->getHeaderLine('Host'));
        $this->assertEquals('Bar', $sent->getHeaderLine('foo'));
    }

    /**
     * @expectedException \Hough\Guzzle\Exception\ConnectException
     */
    public function testAddsErrorToResponse()
    {
        $handler = new StreamHandler();
        call_user_func($handler, 
            new Request('GET', 'http://localhost:123'),
            array('timeout' => 0.01)
        )->wait();
    }

    public function testStreamAttributeKeepsStreamOpen()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request(
            'PUT',
            Server::$url . 'foo?baz=bar',
            array('Foo' => 'Bar'),
            'test'
        );
        $response = call_user_func($handler, $request, array('stream' => true))->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('8', $response->getHeaderLine('Content-Length'));
        $body = $response->getBody();
        $stream = $body->detach();
        $this->assertTrue(is_resource($stream));
        $metadata = stream_get_meta_data($stream);
        $this->assertEquals('http', $metadata['wrapper_type']);
        $this->assertEquals('hi there', stream_get_contents($stream));
        fclose($stream);
        $received = Server::received();
        $sent = $received[0];
        $this->assertEquals('PUT', $sent->getMethod());
        $this->assertEquals('http://127.0.0.1:8126/foo?baz=bar', (string) $sent->getUri());
        $this->assertEquals('Bar', $sent->getHeaderLine('Foo'));
        $this->assertEquals('test', (string) $sent->getBody());
    }

    public function testDrainsResponseIntoTempStream()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array())->wait();
        $body = $response->getBody();
        $stream = $body->detach();
        $metadata = stream_get_meta_data($stream);
        $this->assertEquals('php://temp', $metadata['uri']);
        $this->assertEquals('hi', fread($stream, 2));
        fclose($stream);
    }

    public function testDrainsResponseIntoSaveToBody()
    {
        $r = fopen('php://temp', 'r+');
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('sink' => $r))->wait();
        $body = $response->getBody()->detach();
        $metadata = stream_get_meta_data($body);
        $this->assertEquals('php://temp', $metadata['uri']);
        $this->assertEquals('hi', fread($body, 2));
        $this->assertEquals(' there', stream_get_contents($r));
        fclose($r);
    }

    public function testDrainsResponseIntoSaveToBodyAtPath()
    {
        $tmpfname = tempnam('/tmp', 'save_to_path');
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('sink' => $tmpfname))->wait();
        $body = $response->getBody();
        $this->assertEquals($tmpfname, $body->getMetadata('uri'));
        $this->assertEquals('hi', $body->read(2));
        $body->close();
        unlink($tmpfname);
    }

    public function testDrainsResponseIntoSaveToBodyAtNonExistentPath()
    {
        $tmpfname = tempnam('/tmp', 'save_to_path');
        unlink($tmpfname);
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('sink' => $tmpfname))->wait();
        $body = $response->getBody();
        $this->assertEquals($tmpfname, $body->getMetadata('uri'));
        $this->assertEquals('hi', $body->read(2));
        $body->close();
        unlink($tmpfname);
    }

    public function testDrainsResponseAndReadsOnlyContentLengthBytes()
    {
        Server::flush();
        Server::enqueue(array(
            new Response(200, array(
                'Foo' => 'Bar',
                'Content-Length' => 8,
            ), 'hi there... This has way too much data!')
        ));
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array())->wait();
        $body = $response->getBody();
        $stream = $body->detach();
        $this->assertEquals('hi there', stream_get_contents($stream));
        fclose($stream);
    }

    public function testDoesNotDrainWhenHeadRequest()
    {
        Server::flush();
        // Say the content-length is 8, but return no response.
        Server::enqueue(array(
            new Response(200, array(
                'Foo' => 'Bar',
                'Content-Length' => 8,
            ), '')
        ));
        $handler = new StreamHandler();
        $request = new Request('HEAD', Server::$url);
        $response = call_user_func($handler, $request, array())->wait();
        $body = $response->getBody();
        $stream = $body->detach();
        $this->assertEquals('', stream_get_contents($stream));
        fclose($stream);
    }

    public function testAutomaticallyDecompressGzip()
    {
        Server::flush();
        $content = gzencode('test');
        Server::enqueue(array(
            new Response(200, array(
                'Content-Encoding' => 'gzip',
                'Content-Length'   => strlen($content),
            ), $content)
        ));
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('decode_content' => true))->wait();
        $this->assertEquals('test', (string) $response->getBody());
        $this->assertFalse($response->hasHeader('content-encoding'));
        $this->assertTrue(!$response->hasHeader('content-length') || $response->getHeaderLine('content-length') == $response->getBody()->getSize());
    }

    public function testReportsOriginalSizeAndContentEncodingAfterDecoding()
    {
        Server::flush();
        $content = gzencode('test');
        Server::enqueue(array(
            new Response(200, array(
                'Content-Encoding' => 'gzip',
                'Content-Length'   => strlen($content),
            ), $content)
        ));
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('decode_content' => true))->wait();

        $this->assertSame(
            'gzip',
            $response->getHeaderLine('x-encoded-content-encoding')
        );
        $this->assertSame(
            strlen($content),
            (int) $response->getHeaderLine('x-encoded-content-length')
        );
    }

    public function testDoesNotForceGzipDecode()
    {
        Server::flush();
        $content = gzencode('test');
        Server::enqueue(array(
            new Response(200, array(
                'Content-Encoding' => 'gzip',
                'Content-Length'   => strlen($content),
            ), $content)
        ));
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('decode_content' => false))->wait();
        $this->assertSame($content, (string) $response->getBody());
        $this->assertEquals('gzip', $response->getHeaderLine('content-encoding'));
        $this->assertEquals(strlen($content), $response->getHeaderLine('content-length'));
    }

    public function testProtocolVersion()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url, array(), null, '1.0');
        call_user_func($handler, $request, array());
        $received = Server::received();
        $this->assertEquals('1.0', $received[0]->getProtocolVersion());
    }

    protected function getSendResult(array $opts)
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $opts['stream'] = true;
        $request = new Request('GET', Server::$url);
        return call_user_func($handler, $request, $opts)->wait();
    }

    /**
     * @expectedException \Hough\Guzzle\Exception\ConnectException
     * @expectedExceptionMessage Connection refused
     */
    public function testAddsProxy()
    {
        $this->getSendResult(array('proxy' => '127.0.0.1:8125'));
    }

    public function testAddsProxyByProtocol()
    {
        $url = str_replace('http', 'tcp', Server::$url);
        $res = $this->getSendResult(array('proxy' => array('http' => $url)));
        $opts = stream_context_get_options($res->getBody()->detach());
        $this->assertEquals($url, $opts['http']['proxy']);
    }

    public function testAddsProxyButHonorsNoProxy()
    {
        $url = str_replace('http', 'tcp', Server::$url);
        $res = $this->getSendResult(array('proxy' => array(
            'http' => $url,
            'no'   => array('*')
        )));
        $opts = stream_context_get_options($res->getBody()->detach());
        $this->assertTrue(empty($opts['http']['proxy']));
    }

    public function testAddsTimeout()
    {
        $res = $this->getSendResult(array('stream' => true, 'timeout' => 200));
        $opts = stream_context_get_options($res->getBody()->detach());
        $this->assertEquals(200, $opts['http']['timeout']);
    }

    /**
     * @expectedException \Hough\Guzzle\Exception\RequestException
     * @expectedExceptionMessage SSL CA bundle not found: /does/not/exist
     */
    public function testVerifiesVerifyIsValidIfPath()
    {
        $this->getSendResult(array('verify' => '/does/not/exist'));
    }

    public function testVerifyCanBeDisabled()
    {
        $this->getSendResult(array('verify' => false));
    }

    /**
     * @expectedException \Hough\Guzzle\Exception\RequestException
     * @expectedExceptionMessage SSL certificate not found: /does/not/exist
     */
    public function testVerifiesCertIfValidPath()
    {
        $this->getSendResult(array('cert' => '/does/not/exist'));
    }

    public function testVerifyCanBeSetToPath()
    {
        $path = $path = \Hough\Guzzle\default_ca_bundle();
        $res = $this->getSendResult(array('verify' => $path));
        $opts = stream_context_get_options($res->getBody()->detach());
        $this->assertEquals(true, $opts['ssl']['verify_peer']);
        $this->assertEquals(true, $opts['ssl']['verify_peer_name']);
        $this->assertEquals($path, $opts['ssl']['cafile']);
        $this->assertTrue(file_exists($opts['ssl']['cafile']));
    }

    public function testUsesSystemDefaultBundle()
    {
        $path = $path = \Hough\Guzzle\default_ca_bundle();
        $res = $this->getSendResult(array('verify' => true));
        $opts = stream_context_get_options($res->getBody()->detach());
        if (PHP_VERSION_ID < 50600) {
            $this->assertEquals($path, $opts['ssl']['cafile']);
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid verify request option
     */
    public function testEnsuresVerifyOptionIsValid()
    {
        $this->getSendResult(array('verify' => 10));
    }

    public function testCanSetPasswordWhenSettingCert()
    {
        $path = __FILE__;
        $res = $this->getSendResult(array('cert' => array($path, 'foo')));
        $opts = stream_context_get_options($res->getBody()->detach());
        $this->assertEquals($path, $opts['ssl']['local_cert']);
        $this->assertEquals('foo', $opts['ssl']['passphrase']);
    }

    public function testDebugAttributeWritesToStream()
    {
        $this->queueRes();
        $f = fopen('php://temp', 'w+');
        $this->getSendResult(array('debug' => $f));
        fseek($f, 0);
        $contents = stream_get_contents($f);
        $this->assertContains('<GET http://127.0.0.1:8126/> [CONNECT]', $contents);
        $this->assertContains('<GET http://127.0.0.1:8126/> [FILE_SIZE_IS]', $contents);
        $this->assertContains('<GET http://127.0.0.1:8126/> [PROGRESS]', $contents);
    }

    public function testDebugAttributeWritesStreamInfoToBuffer()
    {
        $called = false;
        $this->queueRes();
        $buffer = fopen('php://temp', 'r+');
        $this->getSendResult(array(
            'progress' => function () use (&$called) { $called = true; },
            'debug' => $buffer,
        ));
        fseek($buffer, 0);
        $contents = stream_get_contents($buffer);
        $this->assertContains('<GET http://127.0.0.1:8126/> [CONNECT]', $contents);
        $this->assertContains('<GET http://127.0.0.1:8126/> [FILE_SIZE_IS] message: "Content-Length: 8"', $contents);
        $this->assertContains('<GET http://127.0.0.1:8126/> [PROGRESS] bytes_max: "8"', $contents);
        $this->assertTrue($called);
    }

    public function testEmitsProgressInformation()
    {
        $called = array();
        $this->queueRes();
        $this->getSendResult(array(
            'progress' => function () use (&$called) {
                $called[] = func_get_args();
            },
        ));
        $this->assertNotEmpty($called);
        $this->assertEquals(8, $called[0][0]);
        $this->assertEquals(0, $called[0][1]);
    }

    public function testEmitsProgressInformationAndDebugInformation()
    {
        $called = array();
        $this->queueRes();
        $buffer = fopen('php://memory', 'w+');
        $this->getSendResult(array(
            'debug'    => $buffer,
            'progress' => function () use (&$called) {
                $called[] = func_get_args();
            },
        ));
        $this->assertNotEmpty($called);
        $this->assertEquals(8, $called[0][0]);
        $this->assertEquals(0, $called[0][1]);
        rewind($buffer);
        $this->assertNotEmpty(stream_get_contents($buffer));
        fclose($buffer);
    }

    public function testPerformsShallowMergeOfCustomContextOptions()
    {
        $res = $this->getSendResult(array(
            'stream_context' => array(
                'http' => array(
                    'request_fulluri' => true,
                    'method' => 'HEAD',
                ),
                'socket' => array(
                    'bindto' => '127.0.0.1:0',
                ),
                'ssl' => array(
                    'verify_peer' => false,
                ),
            ),
        ));
        $opts = stream_context_get_options($res->getBody()->detach());
        $this->assertEquals('HEAD', $opts['http']['method']);
        $this->assertTrue($opts['http']['request_fulluri']);
        $this->assertEquals('127.0.0.1:0', $opts['socket']['bindto']);
        $this->assertFalse($opts['ssl']['verify_peer']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage stream_context must be an array
     */
    public function testEnsuresThatStreamContextIsAnArray()
    {
        $this->getSendResult(array('stream_context' => 'foo'));
    }

    public function testDoesNotAddContentTypeByDefault()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('PUT', Server::$url, array('Content-Length' => 3), 'foo');
        call_user_func($handler, $request, array());
        $received = Server::received();
        $req = $received[0];
        $this->assertEquals('', $req->getHeaderLine('Content-Type'));
        $this->assertEquals(3, $req->getHeaderLine('Content-Length'));
    }

    public function testAddsContentLengthByDefault()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('PUT', Server::$url, array(), 'foo');
        call_user_func($handler, $request, array());
        $received = Server::received();
        $req = $received[0];
        $this->assertEquals(3, $req->getHeaderLine('Content-Length'));
    }

    public function testAddsContentLengthEvenWhenEmpty()
    {
        $this->queueRes();
        $handler = new StreamHandler();
        $request = new Request('PUT', Server::$url, array(), '');
        call_user_func($handler, $request, array());
        $received = Server::received();
        $req = $received[0];
        $this->assertEquals(0, $req->getHeaderLine('Content-Length'));
    }

    public function testSupports100Continue()
    {
        Server::flush();
        $response = new Response(200, array('Test' => 'Hello', 'Content-Length' => '4'), 'test');
        Server::enqueue(array($response));
        $request = new Request('PUT', Server::$url, array('Expect' => '100-Continue'), 'test');
        $handler = new StreamHandler();
        $response = call_user_func($handler, $request, array())->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello', $response->getHeaderLine('Test'));
        $this->assertEquals('4', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('test', (string) $response->getBody());
    }

    public function testDoesSleep()
    {
        $response = new response(200);
        Server::enqueue(array($response));
        $a = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $s = microtime(true);
        call_user_func($a, $request, array('delay' => 0.1))->wait();
        $this->assertGreaterThan(0.0001, microtime(true) - $s);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresOnHeadersIsCallable()
    {
        $req = new Request('GET', Server::$url);
        $handler = new StreamHandler();
        call_user_func($handler, $req, array('on_headers' => 'error!'));
    }

    /**
     * @expectedException \Hough\Guzzle\Exception\RequestException
     * @expectedExceptionMessage An error was encountered during the on_headers event
     * @expectedExceptionMessage test
     */
    public function testRejectsPromiseWhenOnHeadersFails()
    {
        Server::flush();
        Server::enqueue(array(
            new Response(200, array('X-Foo' => 'bar'), 'abc 123')
        ));
        $req = new Request('GET', Server::$url);
        $handler = new StreamHandler();
        $promise = call_user_func($handler, $req, array(
            'on_headers' => function () {
                throw new \Exception('test');
            }
        ));
        $promise->wait();
    }

    public function testSuccessfullyCallsOnHeadersBeforeWritingToSink()
    {
        Server::flush();
        Server::enqueue(array(
            new Response(200, array('X-Foo' => 'bar'), 'abc 123')
        ));
        $req = new Request('GET', Server::$url);
        $got = null;

        $stream = Psr7\stream_for();
        $assertNotNull = array($this, 'assertNotNull');
        $stream = FnStream::decorate($stream, array(
            'write' => function ($data) use ($stream, &$got, $assertNotNull) {
                call_user_func($assertNotNull, $got);
                return $stream->write($data);
            }
        ));

        $handler = new StreamHandler();
        $assertEquals = array($this, 'assertEquals');
        $promise = call_user_func($handler, $req, array(
            'sink'       => $stream,
            'on_headers' => function (ResponseInterface $res) use (&$got, $assertEquals) {
                $got = $res;
                call_user_func($assertEquals, 'bar', $res->getHeaderLine('X-Foo'));
            }
        ));

        $response = $promise->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('bar', $response->getHeaderLine('X-Foo'));
        $this->assertEquals('abc 123', (string) $response->getBody());
    }

    public function testInvokesOnStatsOnSuccess()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response(200)));
        $req = new Psr7\Request('GET', Server::$url);
        $gotStats = null;
        $handler = new StreamHandler();
        $promise = call_user_func($handler, $req, array(
            'on_stats' => function (TransferStats $stats) use (&$gotStats) {
                $gotStats = $stats;
            }
        ));
        $response = $promise->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(200, $gotStats->getResponse()->getStatusCode());
        $this->assertEquals(
            Server::$url,
            (string) $gotStats->getEffectiveUri()
        );
        $this->assertEquals(
            Server::$url,
            (string) $gotStats->getRequest()->getUri()
        );
        $this->assertGreaterThan(0, $gotStats->getTransferTime());
    }

    public function testInvokesOnStatsOnError()
    {
        $req = new Psr7\Request('GET', 'http://127.0.0.1:123');
        $gotStats = null;
        $handler = new StreamHandler();
        $promise = call_user_func($handler, $req, array(
            'connect_timeout' => 0.001,
            'timeout' => 0.001,
            'on_stats' => function (TransferStats $stats) use (&$gotStats) {
                $gotStats = $stats;
            }
        ));
        $promise->wait(false);
        $this->assertFalse($gotStats->hasResponse());
        $this->assertEquals(
            'http://127.0.0.1:123',
            (string) $gotStats->getEffectiveUri()
        );
        $this->assertEquals(
            'http://127.0.0.1:123',
            (string) $gotStats->getRequest()->getUri()
        );
        $this->assertInternalType('float', $gotStats->getTransferTime());
        $this->assertInstanceOf(
            '\Hough\Guzzle\Exception\ConnectException',
            $gotStats->getHandlerErrorData()
        );
    }

    public function testStreamIgnoresZeroTimeout()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response(200)));
        $req = new Psr7\Request('GET', Server::$url);
        $gotStats = null;
        $handler = new StreamHandler();
        $promise = call_user_func($handler, $req, array(
            'connect_timeout' => 10,
            'timeout' => 0
        ));
        $response = $promise->wait();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDrainsResponseAndReadsAllContentWhenContentLengthIsZero()
    {
        Server::flush();
        Server::enqueue(array(
            new Response(200, array(
                'Foo' => 'Bar',
                'Content-Length' => '0',
            ), 'hi there... This has a lot of data!')
        ));
        $handler = new StreamHandler();
        $request = new Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array())->wait();
        $body = $response->getBody();
        $stream = $body->detach();
        $this->assertEquals('hi there... This has a lot of data!', stream_get_contents($stream));
        fclose($stream);
    }

    public function testHonorsReadTimeout()
    {
        Server::flush();
        $handler = new StreamHandler();
        $response = $handler(
            new Request('GET', Server::$url . 'guzzle-server/read-timeout'),
            array(
                RequestOptions::READ_TIMEOUT => 1,
                RequestOptions::STREAM => true,
            )
        )->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $body = $response->getBody()->detach();
        $line = fgets($body);
        $this->assertEquals("sleeping 60 seconds ...\n", $line);
        $line = fgets($body);
        $this->assertFalse($line);
        $metadata = stream_get_meta_data($body);
        $this->assertTrue($metadata['timed_out']);
        $this->assertFalse(feof($body));
    }
}
