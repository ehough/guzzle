# ehough/guzzle

[![Build Status](https://travis-ci.org/ehough/guzzle.svg?branch=develop)](https://travis-ci.org/ehough/guzzle)
[![Latest Stable Version](https://poser.pugx.org/ehough/guzzle/v/stable)](https://packagist.org/packages/ehough/guzzle)
[![License](https://poser.pugx.org/ehough/guzzle/license)](https://packagist.org/packages/ehough/guzzle)


A PHP 5.3-compatible fork of [Guzzle 6](https://github.com/guzzle/guzzle).

# Why?

Sadly, [60%](https://w3techs.com/technologies/details/pl-php/5/all) of all PHP web servers still run PHP 5.4 and lower, but Guzzle needs PHP 5.5 or higher. This fork makes Guzzle 6 compatible with PHP 5.3.29 through 7.1.

# How to Use This Fork

Usage is identical to [`guzzle/guzzle`](https://github.com/guzzle/guzzle), except that the code in this library is 
namespaced under `Hough\Guzzle` instead of `GuzzleHttp`.

--- 

Guzzle is a PHP HTTP client that makes it easy to send HTTP requests and
trivial to integrate with web services.

- Simple interface for building query strings, POST requests, streaming large
  uploads, streaming large downloads, using HTTP cookies, uploading JSON data,
  etc...
- Can send both synchronous and asynchronous requests using the same interface.
- Uses PSR-7 interfaces for requests, responses, and streams. This allows you
  to utilize other PSR-7 compatible libraries with Guzzle.
- Abstracts away the underlying HTTP transport, allowing you to write
  environment and transport agnostic code; i.e., no hard dependency on cURL,
  PHP streams, sockets, or non-blocking event loops.
- Middleware system allows you to augment and compose client behavior.

```php
$client = new \Hough\Guzzle\Client();
$res = $client->request('GET', 'https://api.github.com/repos/guzzle/guzzle');
echo $res->getStatusCode();
// 200
echo $res->getHeaderLine('content-type');
// 'application/json; charset=utf8'
echo $res->getBody();
// '{"id": 1420053, "name": "guzzle", ...}'

// Send an asynchronous request.
$request = new \Hough\Psr7\Request('GET', 'http://httpbin.org');
$promise = $client->sendAsync($request)->then(function ($response) {
    echo 'I completed! ' . $response->getBody();
});
$promise->wait();
```

## Help and docs

- [Documentation](http://guzzlephp.org/)
- [stackoverflow](http://stackoverflow.com/questions/tagged/guzzle)
- [Gitter](https://gitter.im/guzzle/guzzle)
