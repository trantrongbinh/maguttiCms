<?php

namespace App\maguttiCms\Domain\Store\Payment\PayPal;

use Illuminate\Support\Collection;
use Srmklive\PayPal\Traits\PayPalRequest as PayPalAPIRequest;
use Srmklive\PayPal\Traits\PayPalTransactions;
use Srmklive\PayPal\Traits\RecurringProfiles;
use Srmklive\PayPal\Services\ExpressCheckout;

class GFExpressCheckout extends ExpressCheckout
{
    // Integrate PayPal Request trait
    use PayPalAPIRequest, PayPalTransactions, RecurringProfiles;

    /**
     * ExpressCheckout constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        // Setting PayPal API Credentials
        $this->setConfig($config);

        $this->httpBodyParam = 'form_params';

        $this->options = [];
    }

    /**
     * Set ExpressCheckout API endpoints & options.
     *
     * @param array $credentials
     *
     * @return void
     */
    public function setExpressCheckoutOptions($credentials)
    {
        // Setting API Endpoints
        if ($this->mode === 'sandbox') {
            $this->config['api_url'] = !empty($this->config['secret']) ?
                'https://api-3t.sandbox.paypal.com/nvp' : 'https://api.sandbox.paypal.com/nvp';

            $this->config['gateway_url'] = 'https://www.sandbox.paypal.com';
        } else {
            $this->config['api_url'] = !empty($this->config['secret']) ?
                'https://api-3t.paypal.com/nvp' : 'https://api.paypal.com/nvp';

            $this->config['gateway_url'] = 'https://www.paypal.com';
        }

        // Adding params outside sandbox / live array
        $this->config['payment_action'] = $credentials['payment_action'];
        $this->config['notify_url'] = $credentials['notify_url'];
        // $this->config['locale'] = $credentials['locale'];
        $this->config['locale'] = get_locale();
    }

    /**
     * Set cart item details for PayPal.
     *
     * @param array $items
     *
     * @return \Illuminate\Support\Collection
     */
    protected function setCartItems($items)
    {
        return (new Collection($items))->map(function ($item, $num) {
            return [
                'L_PAYMENTREQUEST_0_NAME'.$num => $item['name'],
                'L_PAYMENTREQUEST_0_AMT'.$num  => $item['price'],
                'L_PAYMENTREQUEST_0_QTY'.$num  => isset($item['qty']) ? $item['qty'] : 1,
            ];
        })->flatMap(function ($value) {
            return $value;
        });
    }

    /**
     * Set Recurring payments details for SetExpressCheckout API call.
     *
     * @param array $data
     * @param bool  $subscription
     */
    protected function setExpressCheckoutRecurringPaymentConfig($data, $subscription = false)
    {
        $this->post = $this->post->merge([
            'L_BILLINGTYPE0'                 => ($subscription) ? 'RecurringPayments' : 'MerchantInitiatedBilling',
            'L_BILLINGAGREEMENTDESCRIPTION0' => !empty($data['subscription_desc']) ?
                $data['subscription_desc'] : $data['invoice_description'],
        ]);
    }

    /**
     * Set item subtotal if available.
     *
     * @param array $data
     */
    protected function setItemSubTotal($data)
    {
        $this->subtotal = isset($data['subtotal']) ? $data['subtotal'] : $data['total'];
    }

    /**
     * Set shipping amount if available.
     *
     * @param array $data
     */
    protected function setShippingAmount($data)
    {
        if (isset($data['shipping'])) {
            $this->post = $this->post->merge([
                'PAYMENTREQUEST_0_SHIPPINGAMT' => $data['shipping'],
            ]);
        }
    }


    /**
     * Function to perform SetExpressCheckout PayPal API operation.
     *
     * @param array $data
     * @param bool  $subscription
     *
     * @return array
     */
    public function setSimpleExpressCheckout($data, $subscription = false)
    {

        $this->post = collect([
            'PAYMENTREQUEST_0_AMT'              => $data['total'],
            'PAYMENTREQUEST_0_PAYMENTACTION'    => $this->paymentAction,
            'PAYMENTREQUEST_0_CURRENCYCODE'     => $this->currency,
            'PAYMENTREQUEST_0_NOTETEXT'         => '',
            'NOSHIPPING'                        => $data['noshipping'],
            'RETURNURL'                         => $data['return_url'],
            'CANCELURL'                         => $data['cancel_url'],
            'LOCALE'                            => $this->locale,
        ]);

        $response = $this->doPayPalRequest('SetExpressCheckout');

        return collect($response)->merge([
            'paypal_link' => !empty($response['TOKEN']) ? $this->config['gateway_url'].'/webscr?cmd=_express-checkout&token='.$response['TOKEN'] : null,
        ])->toArray();
    }

	/**
	* Function to perform SetExpressCheckout PayPal API operation.
	*
	* @param array $data
	* @param bool  $subscription
	*
	* @return array
	*/
	public function setExpressCheckout($data, $subscription = false,$guest = false)
	{
		$this->setItemSubTotal($data);

		$this->post = $this->setCartItems($data['items'])->merge([
			'PAYMENTREQUEST_0_ITEMAMT'          => $data['products_cost'],
			'PAYMENTREQUEST_0_SHIPPINGAMT'      => $data['shipping_cost'],
			'PAYMENTREQUEST_0_AMT'              => $data['total'],
			'PAYMENTREQUEST_0_PAYMENTACTION'    => $this->paymentAction,
			'PAYMENTREQUEST_0_CURRENCYCODE'     => $this->currency,
			'PAYMENTREQUEST_0_DESC'             => $data['invoice_description'],
			'PAYMENTREQUEST_0_INVNUM'           => $data['invoice_id'],
			'PAYMENTREQUEST_0_NOTETEXT'         => '',
			'NOSHIPPING'                        => $data['noshipping'],
			'PAYMENTREQUEST_0_SHIPTONAME'       => (array_key_exists('ship_to_name', $data))? $data['ship_to_name']: '',
			'PAYMENTREQUEST_0_SHIPTOSTREET'     => (array_key_exists('ship_to_street', $data))? $data['ship_to_street']: '',
			'PAYMENTREQUEST_0_SHIPTOSTREET2'    => '',
			'PAYMENTREQUEST_0_SHIPTOCITY'       => (array_key_exists('ship_to_city', $data))? $data['ship_to_city']: '',
			'PAYMENTREQUEST_0_SHIPTOSTATE'      => (array_key_exists('ship_to_state', $data))? $data['ship_to_state']: '',
			'PAYMENTREQUEST_0_SHIPTOZIP'        => (array_key_exists('ship_to_zip', $data))? $data['ship_to_zip']: '',
			'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'=> (array_key_exists('ship_to_country_code', $data))? $data['ship_to_country_code']: '',
			'PAYMENTREQUEST_0_SHIPTOCOUNTRYNAME'=> (array_key_exists('ship_to_country_name', $data))? $data['ship_to_country_name']: '',
			'PAYMENTREQUEST_0_ADDRESSSTATUS'    => 'none',
			'PAYMENTREQUEST_0_SHIPTOPHONENUM'   => (array_key_exists('ship_phone_number', $data))? $data['ship_phone_number']: '',
			'RETURNURL'                         => $data['return_url'],
			'CANCELURL'                         => $data['cancel_url'],
			'LOCALE'                            => $this->locale,
		]);

		$this->setShippingAmount($data);

        $this->setExpressCheckoutRecurringPaymentConfig($data, $subscription);

        $response = $this->doPayPalRequest('SetExpressCheckout');

        return collect($response)->merge([
            'paypal_link' => !empty($response['TOKEN']) ? $this->config['gateway_url'].'/webscr?cmd=_express-checkout&token='.$response['TOKEN'] : null,
        ])->toArray();
	}

    /**
     * Perform a GetExpressCheckoutDetails API call on PayPal.
     *
     * @param string $token
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function getExpressCheckoutDetails($token)
    {
        $this->setRequestData([
            'TOKEN' => $token,
        ]);

        return $this->doPayPalRequest('GetExpressCheckoutDetails');
    }

    /**
     * Perform DoExpressCheckoutPayment API call on PayPal.
     *
     * @param array  $data
     * @param string $token
     * @param string $payerid
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function doFullExpressCheckoutPayment($data, $token, $payerid)
    {
        $this->setItemSubTotal($data);

        $this->post = $this->setCartItems($data['items'])->merge([
            'TOKEN'                          => $token,
            'PAYERID'                        => $payerid,
            'PAYMENTREQUEST_0_ITEMAMT'       => $data['products_cost'],
			'PAYMENTREQUEST_0_SHIPPINGAMT'      => $data['shipping_cost'],
            'PAYMENTREQUEST_0_AMT'           => $data['total'],
            'PAYMENTREQUEST_0_PAYMENTACTION' => !empty($this->config['payment_action']) ? $this->config['payment_action'] : 'Sale',
            'PAYMENTREQUEST_0_CURRENCYCODE'  => $this->currency,
            'PAYMENTREQUEST_0_DESC'          => $data['invoice_description'],
            'PAYMENTREQUEST_0_INVNUM'        => $data['invoice_id'],
            'PAYMENTREQUEST_0_NOTIFYURL'     => $this->notifyUrl,
        ]);

        $this->setShippingAmount($data);

        return $this->doPayPalRequest('DoExpressCheckoutPayment');
    }


    public function doExpressCheckoutPayment($data, $token, $payerid)
    {
        $this->setItemSubTotal($data);

        $this->post = $this->setCartItems($data['items'])->merge([
            'TOKEN'                          => $token,
            'PAYERID'                        => $payerid,
            //          'PAYMENTREQUEST_0_ITEMAMT'       => $data['products_cost'],
            // 'PAYMENTREQUEST_0_SHIPPINGAMT'      => $data['shipping_cost'],
            'PAYMENTREQUEST_0_AMT'           => $data['total'],
            'PAYMENTREQUEST_0_PAYMENTACTION' => !empty($this->config['payment_action']) ? $this->config['payment_action'] : 'Sale',
            'PAYMENTREQUEST_0_CURRENCYCODE'  => $this->currency,
            // 'PAYMENTREQUEST_0_DESC'          => $data['invoice_description'],
            // 'PAYMENTREQUEST_0_INVNUM'        => $data['invoice_id'],
            'PAYMENTREQUEST_0_NOTIFYURL'     => $this->notifyUrl,
        ]);

        $this->setShippingAmount($data);

        return $this->doPayPalRequest('DoExpressCheckoutPayment');
    }

    /**
     * Perform a DoAuthorization API call on PayPal.
     *
     * @param string $authorization_id Transaction ID
     * @param float  $amount           Amount to capture
     * @param array  $data             Optional request fields
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function doAuthorization($authorization_id, $amount, $data = [])
    {
        $this->setRequestData(
            array_merge($data, [
                'AUTHORIZATIONID' => $authorization_id,
                'AMT'             => $amount,
            ])
        );

        return $this->doPayPalRequest('DoAuthorization');
    }

    /**
     * Perform a DoCapture API call on PayPal.
     *
     * @param string $authorization_id Transaction ID
     * @param float  $amount           Amount to capture
     * @param string $complete         Indicates whether or not this is the last capture.
     * @param array  $data             Optional request fields
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function doCapture($authorization_id, $amount, $complete = 'Complete', $data = [])
    {
        $this->setRequestData(
            array_merge($data, [
                'AUTHORIZATIONID' => $authorization_id,
                'AMT'             => $amount,
                'COMPLETETYPE'    => $complete,
                'CURRENCYCODE'    => $this->currency,
            ])
        );

        return $this->doPayPalRequest('DoCapture');
    }

    /**
     * Perform a DoReauthorization API call on PayPal to reauthorize an existing authorization transaction.
     *
     * @param string $authorization_id
     * @param float  $amount
     * @param array  $data
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function doReAuthorization($authorization_id, $amount, $data = [])
    {
        $this->setRequestData(
            array_merge($data, [
                'AUTHORIZATIONID' => $authorization_id,
                'AMOUNT'          => $amount,
            ])
        );

        return $this->doPayPalRequest('DoReauthorization');
    }

    /**
     * Perform a DoVoid API call on PayPal.
     *
     * @param string $authorization_id Transaction ID
     * @param array  $data             Optional request fields
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function doVoid($authorization_id, $data = [])
    {
        $this->setRequestData(
            array_merge($data, [
                'AUTHORIZATIONID' => $authorization_id,
            ])
        );

        return $this->doPayPalRequest('DoVoid');
    }

    /**
     * Perform a CreateBillingAgreement API call on PayPal.
     *
     * @param string $token
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function createBillingAgreement($token)
    {
        $this->setRequestData([
            'TOKEN' => $token,
        ]);

        return $this->doPayPalRequest('CreateBillingAgreement');
    }

    /**
     * Perform a CreateRecurringPaymentsProfile API call on PayPal.
     *
     * @param array  $data
     * @param string $token
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function createRecurringPaymentsProfile($data, $token)
    {
        $this->post = $this->setRequestData($data)->merge([
            'TOKEN' => $token,
        ]);

        return $this->doPayPalRequest('CreateRecurringPaymentsProfile');
    }

    /**
     * Perform a GetRecurringPaymentsProfileDetails API call on PayPal.
     *
     * @param string $id
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function getRecurringPaymentsProfileDetails($id)
    {
        $this->setRequestData([
            'PROFILEID' => $id,
        ]);

        return $this->doPayPalRequest('GetRecurringPaymentsProfileDetails');
    }

    /**
     * Perform a UpdateRecurringPaymentsProfile API call on PayPal.
     *
     * @param array  $data
     * @param string $id
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function updateRecurringPaymentsProfile($data, $id)
    {
        $this->post = $this->setRequestData($data)->merge([
            'PROFILEID' => $id,
        ]);

        return $this->doPayPalRequest('UpdateRecurringPaymentsProfile');
    }

    /**
     * Change Recurring payment profile status on PayPal.
     *
     * @param string $id
     * @param string $status
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    protected function manageRecurringPaymentsProfileStatus($id, $status)
    {
        $this->setRequestData([
            'PROFILEID' => $id,
            'ACTION'    => $status,
        ]);

        return $this->doPayPalRequest('ManageRecurringPaymentsProfileStatus');
    }

    /**
     * Perform a ManageRecurringPaymentsProfileStatus API call on PayPal to cancel a RecurringPaymentsProfile.
     *
     * @param string $id
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function cancelRecurringPaymentsProfile($id)
    {
        return $this->manageRecurringPaymentsProfileStatus($id, 'Cancel');
    }

    /**
     * Perform a ManageRecurringPaymentsProfileStatus API call on PayPal to suspend a RecurringPaymentsProfile.
     *
     * @param string $id
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function suspendRecurringPaymentsProfile($id)
    {
        return $this->manageRecurringPaymentsProfileStatus($id, 'Suspend');
    }

    /**
     * Perform a ManageRecurringPaymentsProfileStatus API call on PayPal to reactivate a RecurringPaymentsProfile.
     *
     * @param string $id
     *
     * @throws \Exception
     *
     * @return array|\Psr\Http\Message\StreamInterface
     */
    public function reactivateRecurringPaymentsProfile($id)
    {
        return $this->manageRecurringPaymentsProfileStatus($id, 'Reactivate');
    }
}

?>
