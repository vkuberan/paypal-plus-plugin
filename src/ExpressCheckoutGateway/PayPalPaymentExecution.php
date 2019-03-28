<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\ExpressCheckoutGateway;

use Inpsyde\Lib\PayPal\Api\Payment;
use Inpsyde\Lib\PayPal\Exception\PayPalConnectionException;
use Inpsyde\Lib\Psr\Log\LoggerInterface;
use WCPayPalPlus\Api\ApiContextFactory;
use WCPayPalPlus\Api\ErrorData\ApiErrorDataExtractor;
use function WCPayPalPlus\paymentErrorMessage;
use WCPayPalPlus\Order\OrderFactory;
use WCPayPalPlus\Payment\PaymentExecutionFactory;
use WCPayPalPlus\Payment\PaymentProcessException;
use WCPayPalPlus\Request\Request;
use WCPayPalPlus\Session\Session;
use WCPayPalPlus\Setting\ExpressCheckoutStorable;
use RuntimeException;

/**
 * Class PayPalPaymentExecution
 * @package WCPayPalPlus\ExpressCheckoutGateway
 */
class PayPalPaymentExecution
{
    use PaymentExecutionTrait {
        execute as executeHelper;
    }

    /**
     * @var Session
     */
    private $session;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var PaymentExecutionFactory
     */
    private $paymentExecutionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ExpressCheckoutStorable
     */
    private $settingRepository;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var ApiErrorDataExtractor
     */
    private $apiErrorDataExtractor;

    /**
     * PaymentExecution constructor.
     * @param OrderFactory $orderFactory
     * @param PaymentExecutionFactory $paymentExecutionFactory
     * @param Session $session
     * @param LoggerInterface $logger
     * @param ExpressCheckoutStorable $settingRepository
     * @param Request $request
     * @param ApiErrorDataExtractor $apiErrorDataExtractor
     */
    public function __construct(
        OrderFactory $orderFactory,
        PaymentExecutionFactory $paymentExecutionFactory,
        Session $session,
        LoggerInterface $logger,
        ExpressCheckoutStorable $settingRepository,
        Request $request,
        ApiErrorDataExtractor $apiErrorDataExtractor
    ) {

        $this->session = $session;
        $this->orderFactory = $orderFactory;
        $this->paymentExecutionFactory = $paymentExecutionFactory;
        $this->logger = $logger;
        $this->settingRepository = $settingRepository;
        $this->request = $request;
        $this->apiErrorDataExtractor = $apiErrorDataExtractor;
    }

    /**
     * Execute Payment Using Request Data
     *
     * @throws PaymentProcessException
     * @throws \InvalidArgumentException
     * @throws \WCPayPalPlus\Order\OrderFactoryException
     */
    public function execute()
    {
        $redirectUrl = null;
        $orderId = $this->session->get(Session::ORDER_ID);
        $cancelUrl = $this->settingRepository->cancelUrl();

        $requestData = $this->requestData();
        $sessionData = $this->sessionData();

        if (!$orderId
            || !$requestData
            || !$sessionData
            || $sessionData !== $requestData
            || wp_doing_ajax()
        ) {
            return;
        }

        try {
            $order = $this->orderFactory->createById($orderId);
        } catch (RuntimeException $exc) {
            $this->redirectWithErrorMessage(
                $cancelUrl,
                sprintf(
                    esc_html__(
                        'Cannot process the payment because order with ID %d does not exist.',
                        'woo-paypalplus'
                    ),
                    $orderId
                )
            );
        }

        try {
            $this->executeHelper(
                $order,
                $requestData[Request::INPUT_PAYMENT_ID],
                $requestData[Request::INPUT_PAYER_ID]
            );
        } catch (PayPalConnectionException $exc) {
            $errorData = $this->apiErrorDataExtractor->extractByException($exc);
            $this->logger->error($errorData);
            wc_add_notice(paymentErrorMessage($errorData), 'error');
            $redirectUrl = $this->settingRepository->cancelUrl();
        }

        $this->redirect($redirectUrl ?: $order->get_checkout_order_received_url());
    }

    /**
     * Retrieve the Request Data
     * Data include PaymentId, PayerId, PaymentToken
     *
     * @return array
     */
    private function requestData()
    {
        $paymentId = filter_input(
            INPUT_GET,
            Request::INPUT_PAYMENT_ID,
            FILTER_SANITIZE_STRING
        );
        $payerId = filter_input(
            INPUT_GET,
            Request::INPUT_PAYER_ID,
            FILTER_SANITIZE_STRING
        );
        $paymentToken = filter_input(
            INPUT_GET,
            Request::INPUT_PAYMENT_TOKEN,
            FILTER_SANITIZE_STRING
        );

        return $this->normalizeData($paymentId, $payerId, $paymentToken);
    }

    /**
     * Retrieve Session Data
     * Data include PaymentId, PayerId, PaymentToken
     *
     * @return array
     */
    private function sessionData()
    {
        $paymentId = $this->session->get(Session::PAYMENT_ID);
        $payerId = $this->session->get(Session::PAYER_ID);
        $paymentToken = $this->session->get(Session::PAYMENT_TOKEN);

        return $this->normalizeData($paymentId, $payerId, $paymentToken);
    }

    /**
     * @param $paymentId
     * @param $payerId
     * @param $paymentToken
     * @return array
     */
    private function normalizeData($paymentId, $payerId, $paymentToken)
    {
        $data = [
            Request::INPUT_PAYMENT_ID => $paymentId,
            Request::INPUT_PAYER_ID => $payerId,
            Request::INPUT_PAYMENT_TOKEN => $paymentToken,
        ];
        $data = array_map('strval', $data);

        return array_filter($data);
    }

    /**
     * Redirect the User with a WooCommerce Error Message
     *
     * @param $url
     * @param $message
     */
    private function redirectWithErrorMessage($url, $message)
    {
        assert(is_string($message));
        assert(is_string($url));

        $message and wc_add_notice($message, 'error');
        $this->redirect($url);
    }

    /**
     * Redirect the User
     *
     * @param $url
     */
    private function redirect($url)
    {
        assert(is_string($url));

        wp_safe_redirect($url);
        exit;
    }
}
