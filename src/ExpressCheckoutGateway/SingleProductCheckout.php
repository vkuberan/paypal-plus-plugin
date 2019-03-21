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

use Inpsyde\Lib\Psr\Log\LoggerInterface as Logger;
use WCPayPalPlus\Product\ProductStatuses;
use WCPayPalPlus\Request\Request;
use WCPayPalPlus\Utils\AjaxJsonRequest;
use WooCommerce;
use WC_Product;
use WC_Product_Variation;

/**
 * Class SingleProductCheckout
 * @package WCPayPalPlus\ExpressCheckoutGateway
 */
class SingleProductCheckout
{
    const TASK_CREATE_ORDER = 'createOrder';

    const INPUT_PRODUCT_ID = 'product_id';
    const INPUT_PRODUCT_QUANTITY = 'quantity';

    const FILTER_ADD_TO_CART_PRODUCT_ID = 'woocommerce_add_to_cart_product_id';
    const FILTER_ADD_TO_CART_VALIDATION = 'woocommerce_add_to_cart_validation';
    const ACTION_AJAX_ADDED_TO_CART = 'woocommerce_ajax_added_to_cart';

    /**
     * @var CartCheckout
     */
    private $cartCheckout;

    /**
     * @var AjaxJsonRequest
     */
    private $ajaxJsonRequest;

    /**
     * @var WooCommerce
     */
    private $wooCommerce;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * SingleProductCheckout constructor.
     * @param WooCommerce $wooCommerce
     * @param AjaxJsonRequest $ajaxJsonRequest
     * @param CartCheckout $cartCheckout
     * @param Request $request
     * @param Logger $logger
     */
    public function __construct(
        WooCommerce $wooCommerce,
        AjaxJsonRequest $ajaxJsonRequest,
        CartCheckout $cartCheckout,
        Request $request,
        Logger $logger
    ) {

        $this->wooCommerce = $wooCommerce;
        $this->ajaxJsonRequest = $ajaxJsonRequest;
        $this->cartCheckout = $cartCheckout;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Create Order
     */
    public function createOrder()
    {
        $this->addToCart();
        $this->cartCheckout->createOrder();
    }

    /**
     * Add product to Cart
     */
    private function addToCart()
    {
        $productId = (int)$this->request->get(self::INPUT_PRODUCT_ID, FILTER_SANITIZE_NUMBER_INT);

        /**
         * Filter the product Id before create the product
         *
         * This filter is documented in \WC_AJAX::add_to_cart
         *
         * @param int $productId
         */
        $productId = apply_filters(self::FILTER_ADD_TO_CART_PRODUCT_ID, $productId);
        $product = wc_get_product($productId);

        if (!$product instanceof WC_Product) {
            $this->ajaxJsonRequest->sendJsonError([
                'message' => esc_html_x(
                    'The product you are trying to add to cart does not exists.',
                    'express-checkout',
                    'woo-paypalplus'
                ),
            ]);
        }

        $quantity = (int)$this->request->get(
            self::INPUT_PRODUCT_QUANTITY,
            FILTER_SANITIZE_NUMBER_INT
        );
        $quantity = $quantity ? wc_stock_amount($quantity) : 1;

        $productStatus = get_post_status($productId);
        $variationId = 0;
        $variation = [];

        /**
         * Custom Product Validation
         *
         * This filter is documented in \WC_AJAX::add_to_cart
         *
         * @param bool $passedValidation
         * @param int $productId
         * @param int $quantity
         */
        $passedValidation = apply_filters(
            self::FILTER_ADD_TO_CART_VALIDATION,
            true,
            $productId,
            $quantity
        );

        if (!$passedValidation || $productStatus !== ProductStatuses::PUBLISH_STATUS) {
            $this->logger->error(
                'Product cannot be added to cart because is not publicly available.',
                [
                    $productStatus,
                    $productId,
                ]
            );
            $this->ajaxJsonRequest->sendJsonError([
                'message' => esc_html_x(
                    'Product cannot be added to cart because is not publicly available.',
                    'express-checkout',
                    'woo-paypalplus'
                ),
            ]);
        }

        if ($product instanceof WC_Product_Variation) {
            $variationId = $productId;
            $productId = $product->get_parent_id();
            $variation = $product->get_variation_attributes();
        }

        $addedToCart = $this->wooCommerce->cart->add_to_cart(
            $productId,
            $quantity,
            $variationId,
            $variation
        );

        if (!$addedToCart) {
            $this->logger->error(
                'There was a problem to add the product into cart.',
                [
                    $productId,
                    $quantity,
                    $variationId,
                ]
            );
            $this->ajaxJsonRequest->sendJsonError([
                'message' => esc_html__(
                    'There was a problem to add the product into cart. Try again or contact the shop owner.',
                    'woo-paypalplus'
                ),
            ]);
        }

        /**
         * After Product has been Added to the Cart
         *
         * This filter is documented in \WC_AJAX::add_to_cart
         *
         * @param int $productId
         */
        do_action(self::ACTION_AJAX_ADDED_TO_CART, $productId);
    }
}
