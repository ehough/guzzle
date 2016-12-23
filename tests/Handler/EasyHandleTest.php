<?php
namespace Hough\Test\Guzzle6\Handler;

use Hough\Guzzle6\Handler\EasyHandle;
use Hough\Psr7;

/**
 * @covers \Hough\Guzzle6\Handler\EasyHandle
 */
class EasyHandleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage The EasyHandle has been released
     */
    public function testEnsuresHandleExists()
    {
        $easy = new EasyHandle;
        unset($easy->handle);
        $easy->handle;
    }
}
