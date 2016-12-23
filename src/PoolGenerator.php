<?php
namespace Hough\Guzzle;

use Hough\Generators\AbstractGenerator;
use Psr\Http\Message\RequestInterface;

class PoolGenerator extends AbstractGenerator
{
    /**
     * @var \Iterator
     */
    private $_requests;

    /**
     * @var ClientInterface
     */
    private $_client;

    /**
     * @var array
     */
    private $_opts;

    public function __construct(\Iterator $requests, ClientInterface $client, array $opts)
    {
        $this->_requests = $requests;
        $this->_client   = $client;
        $this->_opts     = $opts;
    }

    /**
     * @param int $position
     *
     * @return null|mixed
     */
    protected function resume($position)
    {
        if (!$this->_requests->valid()) {

            return null;
        }

        if ($position === 0) {
            
            $this->_requests->rewind();
        }

        $key = $this->_requests->key();
        $req = $this->_requests->current();

        if ($req instanceof RequestInterface || is_callable($req)) {

            if ($req instanceof RequestInterface) {

                $result = $this->_client->sendAsync($req, $this->_opts);

            } else {

                $result = call_user_func($req, $this->_opts);
            }

            $this->_requests->next();

            if (!$this->_requests->valid()) {

                $this->_requestsRanOut = true;
            }

            return array($key, $result);
        }

        throw new \InvalidArgumentException('Each value yielded by '
                . 'the iterator must be a Psr7\Http\Message\RequestInterface '
                . 'or a callable that returns a promise that fulfills '
                . 'with a Psr7\Message\Http\ResponseInterface object.');
    }
}