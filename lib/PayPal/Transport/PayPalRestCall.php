<?php
namespace Inpsyde\Lib\PayPal\Transport;

use Inpsyde\Lib\PayPal\Core\PayPalHttpConfig;
use Inpsyde\Lib\PayPal\Core\PayPalHttpConnection;
use Inpsyde\Lib\PayPal\Core\PayPalLoggingManager;
use Inpsyde\Lib\PayPal\Rest\ApiContext;

/**
 * Class PayPalRestCall
 *
 * @package Inpsyde\Lib\PayPal\Transport
 */
class PayPalRestCall
{


    /**
     * Paypal Logger
     *
     * @var PayPalLoggingManager logger interface
     */
    private $logger;

    /**
     * API Context
     *
     * @var ApiContext
     */
    private $apiContext;


    /**
     * Default Constructor
     *
     * @param ApiContext $apiContext
     */
    public function __construct(ApiContext $apiContext)
    {
        $this->apiContext = $apiContext;
        $this->logger = PayPalLoggingManager::getInstance(__CLASS__);
    }

    /**
     * @param string $path     Resource path relative to base service endpoint
     * @param string $method   HTTP method - one of GET, POST, PUT, DELETE, PATCH etc
     * @param string $data     Request payload
     * @param array  $headers  HTTP headers
     * @param array  $handlers Array of handlers
     * @return mixed
     * @throws \Inpsyde\Lib\PayPal\Exception\PayPalConnectionException
     */
    public function execute($path, $method, $data = '', $headers = array(), $handlers = array())
    {
        $config = $this->apiContext->getConfig();
        $httpConfig = new PayPalHttpConfig(null, $method, $config);
        $headers = $headers ? $headers : array();
        $httpConfig->setHeaders($headers +
            array(
                'Content-Type' => 'application/json'
            )
        );

        // if proxy set via config, add it
        if (!empty($config['http.Proxy'])) {
            $httpConfig->setHttpProxy($config['http.Proxy']);
        }

        /** @var \Paypal\Handler\IPayPalHandler $handler */
        foreach ($handlers as $handler) {
            if (!is_object($handler)) {
                $fullHandler = "\\" . (string)$handler;
                $handler = new $fullHandler($this->apiContext);
            }
            $handler->handle($httpConfig, $data, array('path' => $path, 'apiContext' => $this->apiContext));
        }
        $connection = new PayPalHttpConnection($httpConfig, $config);
        $response = $connection->execute($data);

        return $response;
    }
}
