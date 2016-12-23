<?php
namespace Hough\Guzzle6;

use Hough\Promise\PromiseInterface;
use Hough\Promise\RejectedPromise;
use Hough\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that retries requests based on the boolean result of
 * invoking the provided "decider" function.
 */
class RetryMiddleware
{
    /** @var callable  */
    private $nextHandler;

    /** @var callable */
    private $decider;

    /**
     * @param callable $decider     Function that accepts the number of retries,
     *                              a request, [response], and [exception] and
     *                              returns true if the request is to be
     *                              retried.
     * @param callable $nextHandler Next handler to invoke.
     * @param callable $delay       Function that accepts the number of retries
     *                              and [response] and returns the number of
     *                              milliseconds to delay.
     */
    public function __construct(
        $decider,
        $nextHandler,
        $delay = null
    ) {
        $this->decider = $decider;
        $this->nextHandler = $nextHandler;
        $this->delay = $delay ?: __CLASS__ . '::exponentialDelay';
    }

    /**
     * Default exponential backoff delay function.
     *
     * @param $retries
     *
     * @return int
     */
    public static function exponentialDelay($retries)
    {
        return (int) pow(2, $retries - 1);
    }

    /**
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        if (!isset($options['retries'])) {
            $options['retries'] = 0;
        }

        $fn = $this->nextHandler;
        return call_user_func($fn, $request, $options)
            ->then(
                $this->onFulfilled($request, $options),
                $this->onRejected($request, $options)
            );
    }

    private function onFulfilled(RequestInterface $req, array $options)
    {
        $callback = array($this, '__onFulfilled');

        return function ($value) use ($req, $options, $callback) {

            return call_user_func($callback, $value, $req, $options);
        };
    }

    private function onRejected(RequestInterface $req, array $options)
    {
        $callback = array($this, '__onRejected');

        return function ($reason) use ($req, $options, $callback) {

            return call_user_func($callback, $reason, $req, $options);
        };
    }

    private function doRetry(RequestInterface $request, array $options, ResponseInterface $response = null)
    {
        $options['delay'] = call_user_func($this->delay, ++$options['retries'], $response);

        return call_user_func($this, $request, $options);
    }

    /**
     * @internal
     */
    public function __onFulfilled($value, RequestInterface $req, $options)
    {
        if (!call_user_func(
            $this->decider,
            $options['retries'],
            $req,
            $value,
            null
        )) {
            return $value;
        }
        return $this->doRetry($req, $options, $value);
    }

    /**
     * @internal
     */
    public function __onRejected($reason, RequestInterface $req, $options)
    {
        if (!call_user_func(
            $this->decider,
            $options['retries'],
            $req,
            null,
            $reason
        )) {
            return new RejectedPromise($reason);
        }
        return $this->doRetry($req, $options);
    }
}
