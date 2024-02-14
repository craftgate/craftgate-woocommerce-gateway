<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Craftgate Payment Gateway Blocks Support
 *
 */
final class WC_Craftgate_Gateway_Blocks_Support extends AbstractPaymentMethodType
{

    /**
     * The Craftgate Gateway instance.
     */
    private WC_Craftgate_Gateway $craftgate_instance;

    /**
     * Payment method name.
     */
    protected $name = 'craftgate_gateway';

    /**
     * Initializes the payment method
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_craftgate_gateway_settings', []);
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->craftgate_instance = $gateways[$this->name];
    }

    /**
     * Returns true if payment method is active.
     */
    public function is_active()
    {
        return $this->craftgate_instance->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for craftgate payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'craftgate_gateway-blocks-script',
            plugin_dir_url(dirname(__DIR__)) . 'assets/js/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        return ['craftgate_gateway-blocks-script'];
    }

    /**
     * Returns configs used in client side.
     *
     */
    public function get_payment_method_data()
    {
        return [
            'title' => $this->craftgate_instance->get_title(),
            'description' => $this->craftgate_instance->get_description(),
            'supports' => array_filter($this->craftgate_instance->supports, [$this->craftgate_instance, 'supports']),
            'icon' => $this->craftgate_instance->get_icon_url(),
        ];
    }
}