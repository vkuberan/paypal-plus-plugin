<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Order;

use UnexpectedValueException;
use DomainException;
use WC_Order;
use WC_Order_Refund;
use Exception;
use WCPayPalPlus\Ipn\Request;

/**
 * Class OrderFactory
 * @package WCPayPalPlus
 */
class OrderFactory
{
    /**
     * @param Request $ipnRequest
     * @return WC_Order|WC_Order_Refund
     * @throws Exception
     */
    public function createByIpnRequest(Request $ipnRequest)
    {
        list($orderId, $orderKey) = $this->customOrderData($ipnRequest);

        $order = $this->createById($orderId);
        $order or $order = $this->createByOrderKey($orderKey);

        $this->bailIfInvalidOrder($order);

        return $order;
    }

    /**
     * @param $orderKey
     * @return WC_Order|WC_Order_Refund
     */
    public function createByOrderKey($orderKey)
    {
        assert(is_string($orderKey));

        $orderId = wc_get_order_id_by_order_key($orderKey);
        $order = $this->createById($orderId);

        $this->bailIfInvalidOrder($order);

        return $order;
    }

    /**
     * Create and order by the given Id
     *
     * @param $orderId
     * @return WC_Order|\WC_Order_Refund
     */
    public function createById($orderId)
    {
        assert(is_int($orderId));

        return wc_get_order($orderId);
    }

    /**
     * @param Request $ipnRequest
     * @return array
     * @throws DomainException
     * @throws UnexpectedValueException
     */
    private function customOrderData(Request $ipnRequest)
    {
        $data = $ipnRequest->get(Request::KEY_CUSTOM);
        if (!$data) {
            throw new DomainException('Invalid Custom Data');
        }

        $data = json_decode($data);
        if ($data === null) {
            throw new UnexpectedValueException('Decoding IPN Custom Data, produced no value');
        }

        $orderId = isset($data->order_id) ? (int)$data->order_id : 0;
        $orderKey = isset($data->order_key) ? $data->order_key : '';

        if (!$orderId && !$orderKey) {
            throw new UnexpectedValueException('Order ID nor Order Key are valid data.');
        }

        return [
            $orderId,
            $orderKey,
        ];
    }

    /**
     * @param $order
     * @throws UnexpectedValueException
     */
    private function bailIfInvalidOrder($order)
    {
        if (!$order instanceof WC_Order) {
            throw new UnexpectedValueException('No way to retrieve the order by IPN custom data.');
        }
    }
}
