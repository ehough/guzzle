<?php
namespace Hough\Tests\Guzzle6;

use Hough\Guzzle6\Client;
use Hough\Guzzle6\Cookie\CookieJar;
use Hough\Guzzle6\Handler\MockHandler;
use Hough\Guzzle6\HandlerStack;
use Hough\Promise\PromiseInterface;
use Hough\Psr7;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testUsesDefaultHandler()
    {
        $client = new Client();
        Server::enqueue(array(new Response(200, array('Content-Length' => 0))));
        $response = $client->get(Server::$url);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Magic request methods require a URI and optional options array
     */
    public function testValidatesArgsForMagicMethods()
    {
        $client = new Client();
        $client->get();
    }

    public function testCanSendMagicAsyncRequests()
    {
        $client = new Client();
        Server::flush();
        Server::enqueue(array(new Response(200, array('Content-Length' => 2), 'hi')));
        $p = $client->getAsync(Server::$url, array('query' => array('test' => 'foo')));
        $this->assertInstanceOf('\Hough\Promise\PromiseInterface', $p);
        $this->assertEquals(200, $p->wait()->getStatusCode());
        $received = Server::received(true);
        $this->assertCount(1, $received);
        $this->assertEquals('test=foo', $received[0]->getUri()->getQuery());
    }

    public function testCanSendSynchronously()
    {
        $client = new Client(array('handler' => new MockHandler(array(new Response()))));
        $request = new Request('GET', 'http://example.com');
        $r = $client->send($request);
        $this->assertInstanceOf('\Psr\Http\Message\ResponseInterface', $r);
        $this->assertEquals(200, $r->getStatusCode());
    }

    public function testClientHasOptions()
    {
        $client = new Client(array(
            'base_uri' => 'http://foo.com',
            'timeout'  => 2,
            'headers'  => array('bar' => 'baz'),
            'handler'  => new MockHandler()
        ));
        $base = $client->getConfig('base_uri');
        $this->assertEquals('http://foo.com', (string) $base);
        $this->assertInstanceOf('\Hough\Psr7\Uri', $base);
        $this->assertNotNull($client->getConfig('handler'));
        $this->assertEquals(2, $client->getConfig('timeout'));
        $this->assertArrayHasKey('timeout', $client->getConfig());
        $this->assertArrayHasKey('headers', $client->getConfig());
    }

    public function testCanMergeOnBaseUri()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array(
            'base_uri' => 'http://foo.com/bar/',
            'handler'  => $mock
        ));
        $client->get('baz');
        $this->assertEquals(
            'http://foo.com/bar/baz',
            $mock->getLastRequest()->getUri()
        );
    }

    public function testCanMergeOnBaseUriWithRequest()
    {
        $mock = new MockHandler(array(new Response(), new Response()));
        $client = new Client(array(
            'handler'  => $mock,
            'base_uri' => 'http://foo.com/bar/'
        ));
        $client->request('GET', new Uri('baz'));
        $this->assertEquals(
            'http://foo.com/bar/baz',
            (string) $mock->getLastRequest()->getUri()
        );

        $client->request('GET', new Uri('baz'), array('base_uri' => 'http://example.com/foo/'));
        $this->assertEquals(
            'http://example.com/foo/baz',
            (string) $mock->getLastRequest()->getUri(),
            'Can overwrite the base_uri through the request options'
        );
    }

    public function testCanUseRelativeUriWithSend()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array(
            'handler'  => $mock,
            'base_uri' => 'http://bar.com'
        ));
        $this->assertEquals('http://bar.com', (string) $client->getConfig('base_uri'));
        $request = new Request('GET', '/baz');
        $client->send($request);
        $this->assertEquals(
            'http://bar.com/baz',
            (string) $mock->getLastRequest()->getUri()
        );
    }

    public function testMergesDefaultOptionsAndDoesNotOverwriteUa()
    {
        $c = new Client(array('headers' => array('User-agent' => 'foo')));
        $this->assertEquals(array('User-agent' => 'foo'), $c->getConfig('headers'));
        $this->assertInternalType('array', $c->getConfig('allow_redirects'));
        $this->assertTrue($c->getConfig('http_errors'));
        $this->assertTrue($c->getConfig('decode_content'));
        $this->assertTrue($c->getConfig('verify'));
    }

    public function testDoesNotOverwriteHeaderWithDefault()
    {
        $mock = new MockHandler(array(new Response()));
        $c = new Client(array(
            'headers' => array('User-agent' => 'foo'),
            'handler' => $mock
        ));
        $c->get('http://example.com', array('headers' => array('User-Agent' => 'bar')));
        $this->assertEquals('bar', $mock->getLastRequest()->getHeaderLine('User-Agent'));
    }

    public function testDoesNotOverwriteHeaderWithDefaultInRequest()
    {
        $mock = new MockHandler(array(new Response()));
        $c = new Client(array(
            'headers' => array('User-agent' => 'foo'),
            'handler' => $mock
        ));
        $request = new Request('GET', Server::$url, array('User-Agent' => 'bar'));
        $c->send($request);
        $this->assertEquals('bar', $mock->getLastRequest()->getHeaderLine('User-Agent'));
    }

    public function testDoesOverwriteHeaderWithSetRequestOption()
    {
        $mock = new MockHandler(array(new Response()));
        $c = new Client(array(
            'headers' => array('User-agent' => 'foo'),
            'handler' => $mock
        ));
        $request = new Request('GET', Server::$url, array('User-Agent' => 'bar'));
        $c->send($request, array('headers' => array('User-Agent' => 'YO')));
        $this->assertEquals('YO', $mock->getLastRequest()->getHeaderLine('User-Agent'));
    }

    public function testCanUnsetRequestOptionWithNull()
    {
        $mock = new MockHandler(array(new Response()));
        $c = new Client(array(
            'headers' => array('foo' => 'bar'),
            'handler' => $mock
        ));
        $c->get('http://example.com', array('headers' => null));
        $this->assertFalse($mock->getLastRequest()->hasHeader('foo'));
    }

    public function testRewriteExceptionsToHttpErrors()
    {
        $client = new Client(array('handler' => new MockHandler(array(new Response(404)))));
        $res = $client->get('http://foo.com', array('exceptions' => false));
        $this->assertEquals(404, $res->getStatusCode());
    }

    public function testRewriteSaveToToSink()
    {
        $r = Psr7\stream_for(fopen('php://temp', 'r+'));
        $mock = new MockHandler(array(new Response(200, array(), 'foo')));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('save_to' => $r));
        $this->assertSame($r, $mock->getLastOptions()['sink']);
    }

    public function testAllowRedirectsCanBeTrue()
    {
        $mock = new MockHandler(array(new Response(200, array(), 'foo')));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler));
        $client->get('http://foo.com', array('allow_redirects' => true));
        $this->assertInternalType('array',  $mock->getLastOptions()['allow_redirects']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage allow_redirects must be true, false, or array
     */
    public function testValidatesAllowRedirects()
    {
        $mock = new MockHandler(array(new Response(200, array(), 'foo')));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler));
        $client->get('http://foo.com', array('allow_redirects' => 'foo'));
    }

    /**
     * @expectedException \Hough\Guzzle6\Exception\ClientException
     */
    public function testThrowsHttpErrorsByDefault()
    {
        $mock = new MockHandler(array(new Response(404)));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler));
        $client->get('http://foo.com');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage cookies must be an instance of Hough\Guzzle6\Cookie\CookieJarInterface
     */
    public function testValidatesCookies()
    {
        $mock = new MockHandler(array(new Response(200, array(), 'foo')));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler));
        $client->get('http://foo.com', array('cookies' => 'foo'));
    }

    public function testSetCookieToTrueUsesSharedJar()
    {
        $mock = new MockHandler(array(
            new Response(200, array('Set-Cookie' => 'foo=bar')),
            new Response()
        ));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler, 'cookies' => true));
        $client->get('http://foo.com');
        $client->get('http://foo.com');
        $this->assertEquals('foo=bar', $mock->getLastRequest()->getHeaderLine('Cookie'));
    }

    public function testSetCookieToJar()
    {
        $mock = new MockHandler(array(
            new Response(200, array('Set-Cookie' => 'foo=bar')),
            new Response()
        ));
        $handler = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handler));
        $jar = new CookieJar();
        $client->get('http://foo.com', array('cookies' => $jar));
        $client->get('http://foo.com', array('cookies' => $jar));
        $this->assertEquals('foo=bar', $mock->getLastRequest()->getHeaderLine('Cookie'));
    }

    public function testCanDisableContentDecoding()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('decode_content' => false));
        $last = $mock->getLastRequest();
        $this->assertFalse($last->hasHeader('Accept-Encoding'));
        $this->assertFalse($mock->getLastOptions()['decode_content']);
    }

    public function testCanSetContentDecodingToValue()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('decode_content' => 'gzip'));
        $last = $mock->getLastRequest();
        $this->assertEquals('gzip', $last->getHeaderLine('Accept-Encoding'));
        $this->assertEquals('gzip', $mock->getLastOptions()['decode_content']);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesHeaders()
    {
        $mock = new MockHandler();
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('headers' => 'foo'));
    }

    public function testAddsBody()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array('body' => 'foo'));
        $last = $mock->getLastRequest();
        $this->assertEquals('foo', (string) $last->getBody());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesQuery()
    {
        $mock = new MockHandler();
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array('query' => false));
    }

    public function testQueryCanBeString()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array('query' => 'foo'));
        $this->assertEquals('foo', $mock->getLastRequest()->getUri()->getQuery());
    }

    public function testQueryCanBeArray()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array('query' => array('foo' => 'bar baz')));
        $this->assertEquals('foo=bar%20baz', $mock->getLastRequest()->getUri()->getQuery());
    }

    public function testCanAddJsonData()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array('json' => array('foo' => 'bar')));
        $last = $mock->getLastRequest();
        $this->assertEquals('{"foo":"bar"}', (string) $mock->getLastRequest()->getBody());
        $this->assertEquals('application/json', $last->getHeaderLine('Content-Type'));
    }

    public function testCanAddJsonDataWithoutOverwritingContentType()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array(
            'headers' => array('content-type' => 'foo'),
            'json'    => 'a'
        ));
        $last = $mock->getLastRequest();
        $this->assertEquals('"a"', (string) $mock->getLastRequest()->getBody());
        $this->assertEquals('foo', $last->getHeaderLine('Content-Type'));
    }

    public function testAuthCanBeTrue()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('auth' => false));
        $last = $mock->getLastRequest();
        $this->assertFalse($last->hasHeader('Authorization'));
    }

    public function testAuthCanBeArrayForBasicAuth()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('auth' => array('a', 'b')));
        $last = $mock->getLastRequest();
        $this->assertEquals('Basic YTpi', $last->getHeaderLine('Authorization'));
    }

    public function testAuthCanBeArrayForDigestAuth()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('auth' => array('a', 'b', 'digest')));
        $last = $mock->getLastOptions();
        $this->assertEquals(array(
            CURLOPT_HTTPAUTH => 2,
            CURLOPT_USERPWD  => 'a:b'
        ), $last['curl']);
    }

    public function testAuthCanBeCustomType()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->get('http://foo.com', array('auth' => 'foo'));
        $last = $mock->getLastOptions();
        $this->assertEquals('foo', $last['auth']);
    }

    public function testCanAddFormParams()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->post('http://foo.com', array(
            'form_params' => array(
                'foo' => 'bar bam',
                'baz' => array('boo' => 'qux')
            )
        ));
        $last = $mock->getLastRequest();
        $this->assertEquals(
            'application/x-www-form-urlencoded',
            $last->getHeaderLine('Content-Type')
        );
        $this->assertEquals(
            'foo=bar+bam&baz%5Bboo%5D=qux',
            (string) $last->getBody()
        );
    }

    public function testFormParamsEncodedProperly()
    {
        $separator = ini_get('arg_separator.output');
        ini_set('arg_separator.output', '&amp;');
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->post('http://foo.com', array(
            'form_params' => array(
                'foo' => 'bar bam',
                'baz' => array('boo' => 'qux')
            )
        ));
        $last = $mock->getLastRequest();
        $this->assertEquals(
            'foo=bar+bam&baz%5Bboo%5D=qux',
            (string) $last->getBody()
        );

        ini_set('arg_separator.output', $separator);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresThatFormParamsAndMultipartAreExclusive()
    {
        $client = new Client(array('handler' => function () {}));
        $client->post('http://foo.com', array(
            'form_params' => array('foo' => 'bar bam'),
            'multipart' => array()
        ));
    }

    public function testCanSendMultipart()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->post('http://foo.com', array(
            'multipart' => array(
                array(
                    'name'     => 'foo',
                    'contents' => 'bar'
                ),
                array(
                    'name'     => 'test',
                    'contents' => fopen(__FILE__, 'r')
                )
            )
        ));

        $last = $mock->getLastRequest();
        $this->assertContains(
            'multipart/form-data; boundary=',
            $last->getHeaderLine('Content-Type')
        );

        $this->assertContains(
            'Content-Disposition: form-data; name="foo"',
            (string) $last->getBody()
        );

        $this->assertContains('bar', (string) $last->getBody());
        $this->assertContains(
            'Content-Disposition: form-data; name="foo"' . "\r\n",
            (string) $last->getBody()
        );
        $this->assertContains(
            'Content-Disposition: form-data; name="test"; filename="ClientTest.php"',
            (string) $last->getBody()
        );
    }

    public function testCanSendMultipartWithExplicitBody()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->send(
            new Request(
                'POST',
                'http://foo.com',
                array(),
                new Psr7\MultipartStream(
                    array(
                        array(
                            'name' => 'foo',
                            'contents' => 'bar',
                        ),
                        array(
                            'name' => 'test',
                            'contents' => fopen(__FILE__, 'r'),
                        ),
                    )
                )
            )
        );

        $last = $mock->getLastRequest();
        $this->assertContains(
            'multipart/form-data; boundary=',
            $last->getHeaderLine('Content-Type')
        );

        $this->assertContains(
            'Content-Disposition: form-data; name="foo"',
            (string) $last->getBody()
        );

        $this->assertContains('bar', (string) $last->getBody());
        $this->assertContains(
            'Content-Disposition: form-data; name="foo"' . "\r\n",
            (string) $last->getBody()
        );
        $this->assertContains(
            'Content-Disposition: form-data; name="test"; filename="ClientTest.php"',
            (string) $last->getBody()
        );
    }

    public function testUsesProxyEnvironmentVariables()
    {
        $http = getenv('HTTP_PROXY');
        $https = getenv('HTTPS_PROXY');
        $no = getenv('NO_PROXY');
        $client = new Client();
        $this->assertNull($client->getConfig('proxy'));
        putenv('HTTP_PROXY=127.0.0.1');
        $client = new Client();
        $this->assertEquals(
            array('http' => '127.0.0.1'),
            $client->getConfig('proxy')
        );
        putenv('HTTPS_PROXY=127.0.0.2');
        putenv('NO_PROXY=127.0.0.3, 127.0.0.4');
        $client = new Client();
        $this->assertEquals(
            array('http' => '127.0.0.1', 'https' => '127.0.0.2', 'no' => array('127.0.0.3','127.0.0.4')),
            $client->getConfig('proxy')
        );
        putenv("HTTP_PROXY=$http");
        putenv("HTTPS_PROXY=$https");
        putenv("NO_PROXY=$no");
    }

    public function testRequestSendsWithSync()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->request('GET', 'http://foo.com');
        $this->assertTrue($mock->getLastOptions()['synchronous']);
    }

    public function testSendSendsWithSync()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $client->send(new Request('GET', 'http://foo.com'));
        $this->assertTrue($mock->getLastOptions()['synchronous']);
    }

    public function testCanSetCustomHandler()
    {
        $mock = new MockHandler(array(new Response(500)));
        $client = new Client(array('handler' => $mock));
        $mock2 = new MockHandler(array(new Response(200)));
        $this->assertEquals(
            200,
            $client->send(new Request('GET', 'http://foo.com'), array(
                'handler' => $mock2
            ))->getStatusCode()
        );
    }

    public function testProperlyBuildsQuery()
    {
        $mock = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mock));
        $request = new Request('PUT', 'http://foo.com');
        $client->send($request, array('query' => array('foo' => 'bar', 'john' => 'doe')));
        $this->assertEquals('foo=bar&john=doe', $mock->getLastRequest()->getUri()->getQuery());
    }

    public function testSendSendsWithIpAddressAndPortAndHostHeaderInRequestTheHostShouldBePreserved()
    {
        $mockHandler = new MockHandler(array(new Response()));
        $client = new Client(array('base_uri' => 'http://127.0.0.1:8585', 'handler' => $mockHandler));
        $request = new Request('GET', '/test', array('Host'=>'foo.com'));

        $client->send($request);

        $this->assertEquals('foo.com', $mockHandler->getLastRequest()->getHeader('Host')[0]);
    }

    public function testSendSendsWithDomainAndHostHeaderInRequestTheHostShouldBePreserved()
    {
        $mockHandler = new MockHandler(array(new Response()));
        $client = new Client(array('base_uri' => 'http://foo2.com', 'handler' => $mockHandler));
        $request = new Request('GET', '/test', array('Host'=>'foo.com'));

        $client->send($request);

        $this->assertEquals('foo.com', $mockHandler->getLastRequest()->getHeader('Host')[0]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesSink()
    {
        $mockHandler = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mockHandler));
        $client->get('http://test.com', array('sink' => true));
    }

    public function testHttpDefaultSchemeIfUriHasNone()
    {
        $mockHandler = new MockHandler(array(new Response()));
        $client = new Client(array('handler' => $mockHandler));

        $client->request('GET', '//example.org/test');

        $this->assertSame('http://example.org/test', (string) $mockHandler->getLastRequest()->getUri());
    }

    public function testOnlyAddSchemeWhenHostIsPresent()
    {
        $mockHandler = new MockHandler(array(new Response()));
        $client = new Client(array('handler'  => $mockHandler));

        $client->request('GET', 'baz');
        $this->assertSame(
            'baz',
            (string) $mockHandler->getLastRequest()->getUri()
        );
    }
}
