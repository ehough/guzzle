<?php
namespace Hough\Guzzle;

use Hough\Promise\PromisorInterface;
use Hough\Promise\EachPromise;

/**
 * Sends and iterator of requests concurrently using a capped pool size.
 *
 * The pool will read from an iterator until it is cancelled or until the
 * iterator is consumed. When a request is yielded, the request is sent after
 * applying the "request_options" request options (if provided in the ctor).
 *a
 *
 * When a function is yielded by the iterator, the function is provided the
 * "request_options" array that should be merged on top of any existing
 * options, and the function MUST then return a wait-able promise.
 */
class Pool implements PromisorInterface
{
    /** @var EachPromise */
    private $each;

    /**
     * @param ClientInterface $client   Client used to send the requests.
     * @param array|\Iterator $requests Requests or functions that return
     *                                  requests to send concurrently.
     * @param array           $config   Associative array of options
     *     - concurrency: (int) Maximum number of requests to send concurrently
     *     - options: Array of request options to apply to each request.
     *     - fulfilled: (callable) Function to invoke when a request completes.
     *     - rejected: (callable) Function to invoke when a request is rejected.
     */
    public function __construct(
        ClientInterface $client,
        $requests,
        array $config = array()
    ) {
        // Backwards compatibility.
        if (isset($config['pool_size'])) {
            $config['concurrency'] = $config['pool_size'];
        } elseif (!isset($config['concurrency'])) {
            $config['concurrency'] = 25;
        }

        if (isset($config['options'])) {
            $opts = $config['options'];
            unset($config['options']);
        } else {
            $opts = array();
        }

        $iterable = \Hough\Promise\iter_for($requests);
        $requests = new PoolGenerator($iterable, $client, $opts);

        $this->each = new EachPromise(call_user_func($requests), $config);
    }

    public function promise()
    {
        return $this->each->promise();
    }

    /**
     * Sends multiple requests concurrently and returns an array of responses
     * and exceptions that uses the same ordering as the provided requests.
     *
     * IMPORTANT: This method keeps every request and response in memory, and
     * as such, is NOT recommended when sending a large number or an
     * indeterminate number of requests concurrently.
     *
     * @param ClientInterface $client   Client used to send the requests
     * @param array|\Iterator $requests Requests to send concurrently.
     * @param array           $options  Passes through the options available in
     *                                  {@see Hough\Guzzle\Pool::__construct}
     *
     * @return array Returns an array containing the response or an exception
     *               in the same order that the requests were sent.
     * @throws \InvalidArgumentException if the event format is incorrect.
     */
    public static function batch(
        ClientInterface $client,
        $requests,
        array $options = array()
    ) {
        $res = array();
        self::cmpCallback($options, 'fulfilled', $res);
        self::cmpCallback($options, 'rejected', $res);
        $pool = new static($client, $requests, $options);
        $pool->promise()->wait();
        ksort($res);

        return $res;
    }

    private static function cmpCallback(array &$options, $name, array &$results)
    {
        if (!isset($options[$name])) {
            $options[$name] = function ($v, $k) use (&$results) {
                $results[$k] = $v;
            };
        } else {
            $currentFn = $options[$name];
            $options[$name] = function ($v, $k) use (&$results, $currentFn) {
                call_user_func($currentFn, $v, $k);
                $results[$k] = $v;
            };
        }
    }
}
