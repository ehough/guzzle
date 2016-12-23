<?php
namespace Hough\Guzzle\Test;

use Hough\Guzzle\Exception\RequestException;
use Hough\Psr7\Request;
use Hough\Psr7\Response;
use Hough\Guzzle\MessageFormatter;

/**
 * @covers \Hough\Guzzle\MessageFormatter
 */
class MessageFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testCreatesWithClfByDefault()
    {
        $f = new MessageFormatter();
        $this->assertEquals(MessageFormatter::CLF, $this->readAttribute($f, 'template'));
        $f = new MessageFormatter(null);
        $this->assertEquals(MessageFormatter::CLF, $this->readAttribute($f, 'template'));
    }

    public function dateProvider()
    {
        return array(
            array('{ts}', '/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}/'),
            array('{date_iso_8601}', '/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}/'),
            array('{date_common_log}', '/^\d\d\/[A-Z][a-z]{2}\/[0-9]{4}/')
        );
    }

    /**
     * @dataProvider dateProvider
     */
    public function testFormatsTimestamps($format, $pattern)
    {
        $f = new MessageFormatter($format);
        $request = new Request('GET', '/');
        $result = $f->format($request);
        $this->assertEquals(1, preg_match($pattern, $result));
    }

    public function formatProvider()
    {
        $request = new Request('PUT', '/', array('x-test' => 'abc'), \Hough\Psr7\stream_for('foo'));
        $response = new Response(200, array('X-Baz' => 'Bar'), \Hough\Psr7\stream_for('baz'));
        $err = new RequestException('Test', $request, $response);

        return array(
            array('{request}', array($request), \Hough\Psr7\str($request)),
            array('{response}', array($request, $response), \Hough\Psr7\str($response)),
            array('{request} {response}', array($request, $response), \Hough\Psr7\str($request) . ' ' . \Hough\Psr7\str($response)),
            // Empty response yields no value
            array('{request} {response}', array($request), \Hough\Psr7\str($request) . ' '),
            array('{req_headers}', array($request), "PUT / HTTP/1.1\r\nx-test: abc"),
            array('{res_headers}', array($request, $response), "HTTP/1.1 200 OK\r\nX-Baz: Bar"),
            array('{res_headers}', array($request), 'NULL'),
            array('{req_body}', array($request), 'foo'),
            array('{res_body}', array($request, $response), 'baz'),
            array('{res_body}', array($request), 'NULL'),
            array('{method}', array($request), $request->getMethod()),
            array('{url}', array($request), $request->getUri()),
            array('{target}', array($request), $request->getRequestTarget()),
            array('{req_version}', array($request), $request->getProtocolVersion()),
            array('{res_version}', array($request, $response), $response->getProtocolVersion()),
            array('{res_version}', array($request), 'NULL'),
            array('{host}', array($request), $request->getHeaderLine('Host')),
            array('{hostname}', array($request, $response), gethostname()),
            array('{hostname}{hostname}', array($request, $response), gethostname() . gethostname()),
            array('{code}', array($request, $response), $response->getStatusCode()),
            array('{code}', array($request), 'NULL'),
            array('{phrase}', array($request, $response), $response->getReasonPhrase()),
            array('{phrase}', array($request), 'NULL'),
            array('{error}', array($request, $response, $err), 'Test'),
            array('{error}', array($request), 'NULL'),
            array('{req_header_x-test}', array($request), 'abc'),
            array('{req_header_x-not}', array($request), ''),
            array('{res_header_X-Baz}', array($request, $response), 'Bar'),
            array('{res_header_x-not}', array($request, $response), ''),
            array('{res_header_X-Baz}', array($request), 'NULL'),
        );
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFormatsMessages($template, $args, $result)
    {
        $f = new MessageFormatter($template);
        $this->assertEquals((string) $result, call_user_func_array(array($f, 'format'), $args));
    }
}
