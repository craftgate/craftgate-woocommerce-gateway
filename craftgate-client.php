<?php

use Craftgate\Craftgate;

require_once 'vendor/autoload.php';

class Craftgate_Client
{
    private $craftgate;

    public function __construct($api_key, $secret_key, $api_url)
    {
        $this->craftgate = new Craftgate(array(
            'apiKey' => $api_key,
            'secretKey' => $secret_key,
            'baseUrl' => $api_url,
        ));
    }

    public function init_checkout_form($request)
    {
        $response = $this->craftgate->payment()->initCheckoutPayment($request);
        return $this->buildResponse($response);
    }

    public function retrieve_checkout_form_result($token)
    {
        $response = $this->craftgate->payment()->retrieveCheckoutPayment($token);
        return $this->buildResponse($response);
    }

    private function buildResponse($response)
    {
        $response_json = json_decode($response);
        if (isset($response_json->data)) {
            return $response_json->data;
        } else {
            return $response_json;
        }
    }
}
