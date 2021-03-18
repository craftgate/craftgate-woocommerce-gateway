<?php

namespace Craftgate\Adapter;

class PaymentAdapter extends BaseAdapter
{
    public function initCheckoutPayment(array $request)
    {
        $path = "/payment/v1/checkout-payments/init";
        return $this->httpPost($path, $request);
    }

    public function retrieveCheckoutPayment($token)
    {
        $path = "/payment/v1/checkout-payments/" . $token;
        return $this->httpGet($path);
    }
}
