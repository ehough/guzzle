<?php
namespace Hough\Tests\Guzzle6;

use Hough\Guzzle6\Cookie\CookieJar;
use Hough\Guzzle6\Handler\MockHandler;
use Hough\Guzzle6\HandlerStack;
use Hough\Psr7\Request;
use Hough\Psr7\Response;

class HandlerStackTest extends \PHPUnit_Framework_TestCase
{
    public function testSetsHandlerInCtor()
    {
        $f = function () {};
        $m1 = function () {};
        $h = new HandlerStack($f, array($m1));
        $this->assertTrue($h->hasHandler());
    }

    public function testCanSetDifferentHandlerAfterConstruction()
    {
        $f = function () {};
        $h = new HandlerStack();
        $h->setHandler($f);
        $h->resolve();
    }

    /**
     * @expectedException \LogicException
     */
    public function testEnsuresHandlerIsSet()
    {
        $h = new HandlerStack();
        $h->resolve();
    }

    public function testPushInOrder()
    {
        $meths = $this->getFunctions();
        $builder = new HandlerStack();
        $builder->setHandler($meths[1]);
        $builder->push($meths[2]);
        $builder->push($meths[3]);
        $builder->push($meths[4]);
        $composed = $builder->resolve();
        $this->assertEquals('Hello - test123', call_user_func($composed, 'test'));
        $this->assertEquals(
            array(array('a', 'test'), array('b', 'test1'), array('c', 'test12')),
            $meths[0]
        );
    }

    public function testUnshiftsInReverseOrder()
    {
        $meths = $this->getFunctions();
        $builder = new HandlerStack();
        $builder->setHandler($meths[1]);
        $builder->unshift($meths[2]);
        $builder->unshift($meths[3]);
        $builder->unshift($meths[4]);
        $composed = $builder->resolve();
        $this->assertEquals('Hello - test321', call_user_func($composed, 'test'));
        $this->assertEquals(
            array(array('c', 'test'), array('b', 'test3'), array('a', 'test32')),
            $meths[0]
        );
    }

    public function testCanRemoveMiddlewareByInstance()
    {
        $meths = $this->getFunctions();
        $builder = new HandlerStack();
        $builder->setHandler($meths[1]);
        $builder->push($meths[2]);
        $builder->push($meths[2]);
        $builder->push($meths[3]);
        $builder->push($meths[4]);
        $builder->push($meths[2]);
        $builder->remove($meths[3]);
        $composed = $builder->resolve();
        $this->assertEquals('Hello - test1131', call_user_func($composed, 'test'));
    }

    public function testCanPrintMiddleware()
    {
        $meths = $this->getFunctions();
        $builder = new HandlerStack();
        $builder->setHandler($meths[1]);
        $builder->push($meths[2], 'a');
        $builder->push(array(__CLASS__, 'foo'));
        $builder->push(array($this, 'bar'));
        $builder->push(__CLASS__ . '::' . 'foo');
        $lines = explode("\n", (string) $builder);
        $this->assertContains("> 4) Name: 'a', Function: callable(", $lines[0]);
        $this->assertContains("> 3) Name: '', Function: callable(Hough\\Tests\\Guzzle6\\HandlerStackTest::foo)", $lines[1]);
        $this->assertContains("> 2) Name: '', Function: callable(['Hough\\Tests\\Guzzle6\\HandlerStackTest', 'bar'])", $lines[2]);
        $this->assertContains("> 1) Name: '', Function: callable(Hough\\Tests\\Guzzle6\\HandlerStackTest::foo)", $lines[3]);
        $this->assertContains("< 0) Handler: callable(", $lines[4]);
        $this->assertContains("< 1) Name: '', Function: callable(Hough\\Tests\\Guzzle6\\HandlerStackTest::foo)", $lines[5]);
        $this->assertContains("< 2) Name: '', Function: callable(['Hough\\Tests\\Guzzle6\\HandlerStackTest', 'bar'])", $lines[6]);
        $this->assertContains("< 3) Name: '', Function: callable(Hough\\Tests\\Guzzle6\\HandlerStackTest::foo)", $lines[7]);
        $this->assertContains("< 4) Name: 'a', Function: callable(", $lines[8]);
    }

    public function testCanAddBeforeByName()
    {
        $meths = $this->getFunctions();
        $builder = new HandlerStack();
        $builder->setHandler($meths[1]);
        $builder->push($meths[2], 'foo');
        $builder->before('foo', $meths[3], 'baz');
        $builder->before('baz', $meths[4], 'bar');
        $builder->before('baz', $meths[4], 'qux');
        $lines = explode("\n", (string) $builder);
        $this->assertContains('> 4) Name: \'bar\'', $lines[0]);
        $this->assertContains('> 3) Name: \'qux\'', $lines[1]);
        $this->assertContains('> 2) Name: \'baz\'', $lines[2]);
        $this->assertContains('> 1) Name: \'foo\'', $lines[3]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresHandlerExistsByName()
    {
        $builder = new HandlerStack();
        $builder->before('foo', function () {});
    }

    public function testCanAddAfterByName()
    {
        $meths = $this->getFunctions();
        $builder = new HandlerStack();
        $builder->setHandler($meths[1]);
        $builder->push($meths[2], 'a');
        $builder->push($meths[3], 'b');
        $builder->after('a', $meths[4], 'c');
        $builder->after('b', $meths[4], 'd');
        $lines = explode("\n", (string) $builder);
        $this->assertContains('4) Name: \'a\'', $lines[0]);
        $this->assertContains('3) Name: \'c\'', $lines[1]);
        $this->assertContains('2) Name: \'b\'', $lines[2]);
        $this->assertContains('1) Name: \'d\'', $lines[3]);
    }

    public function testPicksUpCookiesFromRedirects()
    {
        $mock = new MockHandler(array(
            new Response(301, array(
                'Location'   => 'http://foo.com/baz',
                'Set-Cookie' => 'foo=bar; Domain=foo.com'
            )),
            new Response(200)
        ));
        $handler = HandlerStack::create($mock);
        $request = new Request('GET', 'http://foo.com/bar');
        $jar = new CookieJar();
        $response = call_user_func($handler, $request, array(
            'allow_redirects' => true,
            'cookies' => $jar
        ))->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $lastRequest = $mock->getLastRequest();
        $this->assertEquals('http://foo.com/baz', (string) $lastRequest->getUri());
        $this->assertEquals('foo=bar', $lastRequest->getHeaderLine('Cookie'));
    }

    private function getFunctions()
    {
        $calls = array();

        $a = function (callable $next) use (&$calls) {
            return function ($v) use ($next, &$calls) {
                $calls[] = array('a', $v);
                return call_user_func($next, $v . '1');
            };
        };

        $b = function (callable $next) use (&$calls) {
            return function ($v) use ($next, &$calls) {
                $calls[] = array('b', $v);
                return call_user_func($next, $v . '2');
            };
        };

        $c = function (callable $next) use (&$calls) {
            return function ($v) use ($next, &$calls) {
                $calls[] = array('c', $v);
                return call_user_func($next, $v . '3');
            };
        };

        $handler = function ($v) {
            return 'Hello - ' . $v;
        };

        return array(&$calls, $handler, $a, $b, $c);
    }

    public static function foo() {}
    public function bar () {}
}
