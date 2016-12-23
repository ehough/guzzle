<?php
namespace Hough\Tests\Guzzle6;

use Hough\Guzzle6\TransferStats;
use Hough\Psr7;

class TransferStatsTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $request = new Psr7\Request('GET', 'http://foo.com');
        $response = new Psr7\Response();
        $stats = new TransferStats(
            $request,
            $response,
            10.5,
            null,
            array('foo' => 'bar')
        );
        $this->assertSame($request, $stats->getRequest());
        $this->assertSame($response, $stats->getResponse());
        $this->assertTrue($stats->hasResponse());
        $this->assertEquals(array('foo' => 'bar'), $stats->getHandlerStats());
        $this->assertEquals('bar', $stats->getHandlerStat('foo'));
        $this->assertSame($request->getUri(), $stats->getEffectiveUri());
        $this->assertEquals(10.5, $stats->getTransferTime());
        $this->assertNull($stats->getHandlerErrorData());
    }
}
