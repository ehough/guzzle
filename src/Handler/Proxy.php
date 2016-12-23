<?php
namespace Hough\Guzzle\Handler;

use Hough\Guzzle\RequestOptions;
use Psr\Http\Message\RequestInterface;

/**
 * Provides basic proxies for handlers.
 */
class Proxy
{
    /**
     * Sends synchronous requests to a specific handler while sending all other
     * requests to another handler.
     *
     * @param callable $default Handler used for normal responses
     * @param callable $sync    Handler used for synchronous responses.
     *
     * @return callable Returns the composed handler.
     */
    public static function wrapSync(
        $default,
        $sync
    ) {
        return function (RequestInterface $request, array $options) use ($default, $sync) {
            return empty($options[RequestOptions::SYNCHRONOUS])
                ? call_user_func($default, $request, $options)
                : call_user_func($sync, $request, $options);
        };
    }

    /**
     * Sends streaming requests to a streaming compatible handler while sending
     * all other requests to a default handler.
     *
     * This, for example, could be useful for taking advantage of the
     * performance benefits of curl while still supporting true streaming
     * through the StreamHandler.
     *
     * @param callable $default   Handler used for non-streaming responses
     * @param callable $streaming Handler used for streaming responses
     *
     * @return callable Returns the composed handler.
     */
    public static function wrapStreaming(
        $default,
        $streaming
    ) {
        return function (RequestInterface $request, array $options) use ($default, $streaming) {
            return empty($options['stream'])
                ? call_user_func($default, $request, $options)
                : call_user_func($streaming, $request, $options);
        };
    }
}
