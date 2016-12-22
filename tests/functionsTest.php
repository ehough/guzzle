<?php
namespace Hough\Tests\Guzzle6;

use Hough\Guzzle6;

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    public function testExpandsTemplate()
    {
        $this->assertEquals(
            'foo/123',
            \Hough\Guzzle6\uri_template('foo/{bar}', ['bar' => '123'])
        );
    }
    public function noBodyProvider()
    {
        return [['get'], ['head'], ['delete']];
    }

    public function testProvidesDefaultUserAgent()
    {
        $ua = \Hough\Guzzle6\default_user_agent();
        $this->assertEquals(1, preg_match('#^ehough/guzzle6/[1-9]+\.[0-9]+\.[0-9]+ curl/.+ PHP/.+$#', $ua));
    }

    public function typeProvider()
    {
        return [
            ['foo', 'string(3) "foo"'],
            [true, 'bool(true)'],
            [false, 'bool(false)'],
            [10, 'int(10)'],
            [1.0, 'float(1)'],
            [new StrClass(), 'object(Hough\Tests\Guzzle6\StrClass)'],
            [['foo'], 'array(1)']
        ];
    }
    /**
     * @dataProvider typeProvider
     */
    public function testDescribesType($input, $output)
    {
        $this->assertEquals($output, \Hough\Guzzle6\describe_type($input));
    }

    public function testParsesHeadersFromLines()
    {
        $lines = ['Foo: bar', 'Foo: baz', 'Abc: 123', 'Def: a, b'];
        $this->assertEquals([
            'Foo' => ['bar', 'baz'],
            'Abc' => ['123'],
            'Def' => ['a, b'],
        ], \Hough\Guzzle6\headers_from_lines($lines));
    }

    public function testParsesHeadersFromLinesWithMultipleLines()
    {
        $lines = ['Foo: bar', 'Foo: baz', 'Foo: 123'];
        $this->assertEquals([
            'Foo' => ['bar', 'baz', '123'],
        ], \Hough\Guzzle6\headers_from_lines($lines));
    }

    public function testReturnsDebugResource()
    {
        $this->assertTrue(is_resource(\Hough\Guzzle6\debug_resource()));
    }

    public function testProvidesDefaultCaBundler()
    {
        $this->assertFileExists(\Hough\Guzzle6\default_ca_bundle());
    }

    public function noProxyProvider()
    {
        return [
            ['mit.edu', ['.mit.edu'], false],
            ['foo.mit.edu', ['.mit.edu'], true],
            ['mit.edu', ['mit.edu'], true],
            ['mit.edu', ['baz', 'mit.edu'], true],
            ['mit.edu', ['', '', 'mit.edu'], true],
            ['mit.edu', ['baz', '*'], true],
        ];
    }

    /**
     * @dataProvider noproxyProvider
     */
    public function testChecksNoProxyList($host, $list, $result)
    {
        $this->assertSame(
            $result,
            \Hough\Guzzle6\is_host_in_noproxy($host, $list)
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresNoProxyCheckHostIsSet()
    {
        \Hough\Guzzle6\is_host_in_noproxy('', []);
    }

    public function testEncodesJson()
    {
        $this->assertEquals('true', \Hough\Guzzle6\json_encode(true));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEncodesJsonAndThrowsOnError()
    {
        \Hough\Guzzle6\json_encode("\x99");
    }

    public function testDecodesJson()
    {
        $this->assertSame(true, \Hough\Guzzle6\json_decode('true'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDecodesJsonAndThrowsOnError()
    {
        \Hough\Guzzle6\json_decode('{{]]');
    }
}

final class StrClass
{
    public function __toString()
    {
        return 'foo';
    }
}
