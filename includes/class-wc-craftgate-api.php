<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Craftgate_API
{
    private $craftgate;

    public function __construct($api_key, $secret_key, $api_url)
    {
        $this->craftgate = new \Craftgate\Craftgate(array(
            'apiKey' => $api_key,
            'secretKey' => $secret_key,
            'baseUrl' => $api_url,
        ));
    }

    public function init_checkout_form($request)
    {
        $response = $this->craftgate->payment()->initCheckoutPayment($request);
        return $this->build_response($response);
    }

    public function retrieve_checkout_form_result($token)
    {
        $response = $this->craftgate->payment()->retrieveCheckoutPayment($token);
        return $this->build_response($response);
    }

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
