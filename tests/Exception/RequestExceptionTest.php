<?php
namespace Hough\Tests\Event;

use Hough\Guzzle6\Exception\RequestException;
use Hough\Psr7\Request;
use Hough\Psr7\Response;

/**
 * @covers \Hough\Guzzle6\Exception\RequestException
 */
class RequestExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasRequestAndResponse()
    {
        $req = new Request('GET', '/');
        $res = new Response(200);
        $e = new RequestException('foo', $req, $res);
        $this->assertSame($req, $e->getRequest());
        $this->assertSame($res, $e->getResponse());
        $this->assertTrue($e->hasResponse());
        $this->assertEquals('foo', $e->getMessage());
    }

    public function testCreatesGenerateException()
    {
        $e = RequestException::create(new Request('GET', '/'));
        $this->assertEquals('Error completing request', $e->getMessage());
        $this->assertInstanceOf('Hough\Guzzle6\Exception\RequestException', $e);
    }

    public function testCreatesClientErrorResponseException()
    {
        $e = RequestException::create(new Request('GET', '/'), new Response(400));
        $this->assertContains(
            'GET /',
            $e->getMessage()
        );
        $this->assertContains(
            '400 Bad Request',
            $e->getMessage()
        );
        $this->assertInstanceOf('Hough\Guzzle6\Exception\ClientException', $e);
    }

    public function testCreatesServerErrorResponseException()
    {
        $e = RequestException::create(new Request('GET', '/'), new Response(500));
        $this->assertContains(
            'GET /',
            $e->getMessage()
        );
        $this->assertContains(
            '500 Internal Server Error',
            $e->getMessage()
        );
        $this->assertInstanceOf('Hough\Guzzle6\Exception\ServerException', $e);
    }

    public function testCreatesGenericErrorResponseException()
    {
        $e = RequestException::create(new Request('GET', '/'), new Response(600));
        $this->assertContains(
            'GET /',
            $e->getMessage()
        );
        $this->assertContains(
            '600 ',
            $e->getMessage()
        );
        $this->assertInstanceOf('Hough\Guzzle6\Exception\RequestException', $e);
    }

    public function dataPrintableResponses()
    {
        return array(
            array('You broke the test!'),
            array('<h1>zlomený zkouška</h1>'),
            array('{"tester": "Philépe Gonzalez"}'),
            array("<xml>\n\t<text>Your friendly test</text>\n</xml>"),
            array('document.body.write("here comes a test");'),
            array("body:before {\n\tcontent: 'test style';\n}"),
        );
    }

    /**
     * @dataProvider dataPrintableResponses
     */
    public function testCreatesExceptionWithPrintableBodySummary($content)
    {
        $response = new Response(
            500,
            array(),
            $content
        );
        $e = RequestException::create(new Request('GET', '/'), $response);
        $this->assertContains(
            $content,
            $e->getMessage()
        );
        $this->assertInstanceOf('Hough\Guzzle6\Exception\RequestException', $e);
    }

    public function testCreatesExceptionWithTruncatedSummary()
    {
        $content = str_repeat('+', 121);
        $response = new Response(500, array(), $content);
        $e = RequestException::create(new Request('GET', '/'), $response);
        $expected = str_repeat('+', 120) . ' (truncated...)';
        $this->assertContains($expected, $e->getMessage());
    }

    public function testExceptionMessageIgnoresEmptyBody()
    {
        $e = RequestException::create(new Request('GET', '/'), new Response(500));
        $this->assertStringEndsWith('response', $e->getMessage());
    }

    public function testCreatesExceptionWithoutPrintableBody()
    {
        $response = new Response(
            500,
            array('Content-Type' => 'image/gif'),
            $content = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') // 1x1 gif
        );
        $e = RequestException::create(new Request('GET', '/'), $response);
        $this->assertNotContains(
            $content,
            $e->getMessage()
        );
        $this->assertInstanceOf('Hough\Guzzle6\Exception\RequestException', $e);
    }

    public function testHasStatusCodeAsExceptionCode()
    {
        $e = RequestException::create(new Request('GET', '/'), new Response(442));
        $this->assertEquals(442, $e->getCode());
    }

    public function testWrapsRequestExceptions()
    {
        $e = new \Exception('foo');
        $r = new Request('GET', 'http://www.oo.com');
        $ex = RequestException::wrapException($r, $e);
        $this->assertInstanceOf('Hough\Guzzle6\Exception\RequestException', $ex);
        $this->assertSame($e, $ex->getPrevious());
    }

    public function testDoesNotWrapExistingRequestExceptions()
    {
        $r = new Request('GET', 'http://www.oo.com');
        $e = new RequestException('foo', $r);
        $e2 = RequestException::wrapException($r, $e);
        $this->assertSame($e, $e2);
    }

    public function testCanProvideHandlerContext()
    {
        $r = new Request('GET', 'http://www.oo.com');
        $e = new RequestException('foo', $r, null, null, array('bar' => 'baz'));
        $this->assertEquals(array('bar' => 'baz'), $e->getHandlerContext());
    }

    public function testObfuscateUrlWithUsername()
    {
        $r = new Request('GET', 'http://username@www.oo.com');
        $e = RequestException::create($r, new Response(500));
        $this->assertContains('http://username@www.oo.com', $e->getMessage());
    }

    public function testObfuscateUrlWithUsernameAndPassword()
    {
        $r = new Request('GET', 'http://user:password@www.oo.com');
        $e = RequestException::create($r, new Response(500));
        $this->assertContains('http://user:***@www.oo.com', $e->getMessage());
    }
}
