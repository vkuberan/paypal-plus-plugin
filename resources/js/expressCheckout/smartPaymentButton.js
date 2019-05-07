import { formDataByElement, formDataForCart } from './form'
import { contextByElement } from './context'
import * as utils from '../utils'

const SINGLE_PRODUCT_BUTTON = 'paypalplus_ecs_single_product_button'
const CART_BUTTON = 'paypalplus_ecs_cart_button'

const TASK_CREATE_ORDER = 'createOrder'
const TASK_STORE_PAYMENT_DATA = 'storePaymentData'

/**
 * Class Smart Payment Button Renderer
 *
 * @type {SmartPaymentButtonRenderer}
 */
const SmartPaymentButtonRenderer = class SmartPaymentButtonRenderer
{
  /**
   * Constructor
   *
   * @param buttonConfiguration
   * @param validContexts
   * @param request
   */
  constructor (buttonConfiguration, validContexts, request)
  {
    this.buttonConfiguration = buttonConfiguration
    this.cancelUrl = this.buttonConfiguration.redirect_urls.cancel_url
    this.validContexts = Array.from(validContexts)
    this.request = request
  }

  /**
   * Render button for single product
   */
  singleProductButtonRender ()
  {
    const element = document.querySelector(`#${SINGLE_PRODUCT_BUTTON}`)
    element && this.render(element)
  }

  /**
   * Render Button for Cart
   */
  cartButtonRender ()
  {
    const element = document.querySelector(`#${CART_BUTTON}`)
    element && this.render(element)
  }

  /**
   * Render Button for the Given Element
   *
   * @param element
   * @returns {*}
   */
  // TODO Make it private
  render (element)
  {
    if (_.isUndefined(paypal)) {
      return
    }

    const button = element.querySelector('.paypal-button')
    button && button.remove()

    paypal.Button.render({
      ...this.buttonConfiguration,

      /**
       * Do Payment
       *
       * @returns {*}
       */
      payment: () => {
        let formData = this.formDataByElement(element)
        formData += `&task=${TASK_CREATE_ORDER}`

        formData = formData.replace(/&add-to-cart=[0-9]+/, '')

        return this.request.submit(formData).then(response => {
          if (!'data' in response) {
            console.warn('Unable to process the payment, server did not response with valid data')
            try {
              window.location = this.cancelUrl
            } catch (e) {
              return
            }
          }

          if (!response.success) {
            try {
              window.location = utils.redirectUrlByRequest(response, this.cancelUrl)
            } catch (e) {
              return
            }
          }

          const orderId = 'orderId' in response.data ? response.data.orderId : ''

          if (!orderId) {
            try {
              window.location = utils.redirectUrlByRequest(response, this.cancelUrl)
            } catch (e) {
              return
            }
          }

          return orderId
        }).catch(error => {
          const textStatus = 'textStatus' in error ? error.textStatus : 'Unknown Error during payment'
          console.warn(textStatus)
        })
      },

      /**
       * Execute Authorization
       *
       * @param data
       * @param actions
       * @returns {*}
       */
      onAuthorize: (data, actions) => {
        // TODO Ensure return_url exists.
        let formData = this.formDataByElement(element)

        formData += `&task=${TASK_STORE_PAYMENT_DATA}`;
        formData += `&orderId=${data.OrderID}`;
        formData += `&PayerID=${data.payerID}`;
        formData += `&paymentId=${data.paymentID}`;
        formData += `&token=${data.paymentToken}`;

        formData = formData.replace(/&add-to-cart=[0-9]+/, '')

        return this.request.submit(formData).then((response) => {

          if (!response.success) {
            try {
              window.location = utils.redirectUrlByRequest(response, this.cancelUrl)
            } catch (e) {
              return
            }
          }

          let returnUrl = ''

          if ('redirect_urls' in this.buttonConfiguration
            && 'return_url' in this.buttonConfiguration.redirect_urls
          ) {
            returnUrl = this.buttonConfiguration.redirect_urls.return_url
          }

          returnUrl && actions.redirect(null, returnUrl)
        })
      },

      /**
       * Perform Action when a Payment get Cancelled
       *
       * @param data
       * @param actions
       */
      onCancel: (data, actions) => {
        actions.close()
        const cancelUrl = 'cancelUrl' in data ? data.cancelUrl : ''
        cancelUrl && actions.redirect(null, cancelUrl)
      },

      onError: (data, actions) => {
        console.log('ON ERROR', data, actions)
        // TODO Redirect to cart and show customizable notice with message.
      },

    }, element)
  }

  /**
   * Retrieve context for FormData instance by the Given Element
   *
   * @param element
   * @returns {String}
   */
  // TODO Make it private if not possible move it as closure within the render function.
  formDataByElement (element)
  {
    let formData = ''
    const context = contextByElement(element)

    if (!this.validContexts.includes(context)) {
      throw new Error(
        'Invalid context when try to retrieve the form data during express checkout request.',
      )
    }

    try {
      switch (context) {
        case 'cart':
          formData = formDataForCart(element)
          break
        case 'product':
          formData = formDataByElement(element)
          break
      }
    } catch (err) {
    }

    return formData
  }
}

/**
 * Smart Payment Button Renderer Factory
 *
 * @param buttonConfiguration
 * @param validContexts
 * @param request
 * @returns {SmartPaymentButtonRenderer}
 * @constructor
 */
export function SmartPaymentButtonRendererFactory (buttonConfiguration, validContexts, request)
{
  const object = new SmartPaymentButtonRenderer(buttonConfiguration, validContexts, request)

  Object.freeze(object)

  return object
}
