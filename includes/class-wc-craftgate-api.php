<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Craftgate_API class.
 */
class WC_Craftgate_API
{
    /**
     * API adapter which is used to communicate Craftgate API.
     *
     * @var \Craftgate\Craftgate API adapter
     */
    private $craftgate;

    /**
     * WC_Craftgate_API constructor.
     *
     * @param string $api_key API Key
     * @param string $secret_key Secret Key
     * @param string $api_url API base Url
     */
    public function __construct($api_key, $secret_key, $api_url)
    {
        $this->craftgate = new \Craftgate\Craftgate(array(
            'apiKey' => $api_key,
            'secretKey' => $secret_key,
            'baseUrl' => $api_url,
        ));
    }

    /**
     * Initialize Craftgate checkout form.
     *
     * @param $request array Request array
     */
    public function init_checkout_form($request)
    {
        $response = $this->craftgate->payment()->initCheckoutPayment($request);
        return $this->build_response($response);
    }

    /**
     * Retrieves checkout result.
     *
     * @param $token string Checkout token
     */
    public function retrieve_checkout_form_result($token)
    {
        $response = $this->craftgate->payment()->retrieveCheckoutPayment($token);
        return $this->build_response($response);
    }

    /**
     * Parses and build json response.
     *
     * @param $response string Json response from API
     */
    private function build_response($response)
    {
        $response_json = json_decode($response);
        if (isset($response_json->data)) {
            return $response_json->data;
        } else {
            return $response_json;
        }
    }
}
