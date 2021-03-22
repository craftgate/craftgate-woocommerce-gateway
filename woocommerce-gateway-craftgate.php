<?php
/**
 * Plugin Name: WooCommerce Craftgate Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-craftgate/
 * Description: Accept debit/credit card payments easily and directly on your WordPress site using Craftgate.
 * Author: Craftgate
 * Author URI: https://craftgate.io
 * Version: 1.0.0
 * Requires at least: 4.7
 * WC tested up to: 5.7.0
 * Requires PHP: 5.6
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-craftgate
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_woocommerce_craftgate_gateway', 0);


/**
 * Initializes WooCommerce Craftgate payment gateway
 */
function init_woocommerce_craftgate_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Requires dependencies.
    require_once 'includes/lib/craftgate/autoload.php';
    require_once 'includes/class-wc-craftgate-api.php';

    // Checks PHP 7.1 for price formatters.
    if (version_compare(phpversion(), '7.1', '>=')) {
        ini_set('serialize_precision', -1);
    }

    load_plugin_textdomain('woocommerce-gateway-craftgate', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    /**
     * WC_Craftgate_Gateway class.
     */
    class WC_Craftgate_Gateway extends WC_Payment_Gateway
    {
        /**
         * Unique identifier of the merchant.
         *
         * @var string API Key
         */
        private $api_key;

        /**
         * Security credential of the merchant's API request.
         *
         * @var string Secret Key
         */
        private $secret_key;

        /**
         * Information of whether sandbox mode is active.
         * @var boolean true if sandbox mode is active.metodu için
         */
        private $is_sandbox_active;

        /**
         * Abstraction between Craftgate API and client adapters.
         *
         * @var WC_Craftgate_API API abstraction
         */
        private $craftgate_api;

        private $text_domain = 'woocommerce-gateway-craftgate';

        /**
         * WC_Craftgate_Gateway constructor.
         */
        public function __construct()
        {
            // Gets setting values.
            $this->id = 'craftgate_gateway';
            $this->icon = plugins_url('assets/images/card-brands.png', __FILE__);
            $this->has_fields = false;
            $this->method_title = 'Craftgate Payment Gateway';
            $this->method_description = __('Accept debit/credit card payments easily and directly on your WordPress site using Craftgate.', $this->text_domain);
            $this->order_button_text = __('Pay with Debit/Credit Card', $this->text_domain);

            // Inits admin field and settings.
            $this->init_admin_settings_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            // Inits api fields.
            $this->init_craftgate_api();
            $this->define_woocommerce_actions();
        }

        /**
         * Initializes Craftgate API fields.
         */
        private function init_craftgate_api()
        {
            $this->is_sandbox_active = $this->get_option('is_sandbox_active') === 'yes';
            $api_key_option_name = 'live_api_key';
            $secret_key_option_name = 'live_secret_key';
            $api_url = \Craftgate\CraftgateOptions::API_URL;

            // Assigns sandbox properties.
            if ($this->is_sandbox_active) {
                $api_key_option_name = 'sandbox_api_key';
                $secret_key_option_name = 'sandbox_secret_key';
                $api_url = \Craftgate\CraftgateOptions::SANDBOX_API_URL;
            }

            $this->api_key = $this->get_option($api_key_option_name);
            $this->secret_key = $this->get_option($secret_key_option_name);
            $this->craftgate_api = new WC_Craftgate_API($this->api_key, $this->secret_key, $api_url);
        }

        /**
         * Adds woocommerce actions.
         */
        private function define_woocommerce_actions()
        {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_craftgate_gateway', array($this, 'init_craftgate_checkout_form'));
            add_action('woocommerce_api_craftgate_gateway_callback', array($this, 'handle_craftgate_checkout_form_result'));
            add_action('woocommerce_before_thankyou', array($this, 'show_payment_error'));
        }

        /**
         * Initializes Craftgate checkout form.
         *
         * @param null $order_id string Order id
         */
        public function init_craftgate_checkout_form($order_id = null)
        {
            try {
                $request = $this->build_init_checkout_form_request($order_id);
                $response = $this->craftgate_api->init_checkout_form($request);

                if (isset($response->pageUrl)) {
                    echo '<div id="craftgate_payment_form"><iframe src="' . $response->pageUrl . '&iframe=true"></iframe></div>';
                } else {
                    error_log(json_encode($response));
                    $this->render_error_message(__("An error occurred. Error Code: ", $this->text_domain) . $response->errors->errorCode);
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $this->render_error_message(__("An error occurred. Error Code: ", $this->text_domain) . '-1');
            }
        }

        /**
         * Handles Craftgate checkout result.
         */
        public function handle_craftgate_checkout_form_result()
        {
            try {
                $this->validate_handle_checkout_form_result_params();
                $order_id = $_GET["order_id"];
                $order = $this->retrieve_order($order_id);

                $checkout_form_result = $this->craftgate_api->retrieve_checkout_form_result($_POST["token"]);

                $this->validate_order_id_equals_conversation_id($checkout_form_result, $order_id);
                $this->update_order_checkout_form_result_metadata($order, $checkout_form_result);

                // Checks payment error.
                if (!isset($checkout_form_result->paymentError) && $checkout_form_result->paymentStatus === 'SUCCESS') {
                    $order->payment_complete();
                } else {
                    $order->update_meta_data('craftgate_payment_error', json_encode($checkout_form_result->paymentError));
                    $order->update_status('failed', $checkout_form_result->paymentError->errorDescription);
                    $order->save();
                }

                wc_empty_cart();
                echo "<script>window.top.location.href = '" . $this->get_return_url($order) . "';</script>";
                exit;
            } catch (Exception $e) {
                error_log($e->getMessage());
                $this->render_error_message(__('An error occurred. Error Code: ', $this->text_domain) . '-2');
            }
        }

        /**
         * Shows payment error in payment result page.
         *
         * @param $order_id string Order id
         */
        public function show_payment_error($order_id)
        {
            $order = $this->retrieve_order($order_id);
            if ($order->get_status() != 'failed') return;

            $message = "";
            $craftgate_error = $order->get_meta('craftgate_payment_error');

            if (isset($craftgate_error)) {
                $craftgate_error_json = json_decode($craftgate_error);
                $message = !empty($craftgate_error_json->errorDescription) ? $craftgate_error_json->errorDescription : $craftgate_error_json->errorGroup;
            } ?>
            <div class="craftgate-alert">
                <?php
                _e('Your payment could not be processed.', $this->text_domain);
                echo '<br/>';
                echo $message ?>
            </div>
            <?php
        }

        /**
         * Checks if API request is valid.
         *
         * @return bool Whether available or not
         */
        public function is_available()
        {
            return $this->is_current_currency_supported() && $this->enabled == "yes" && $this->api_key && $this->secret_key;
        }

        /**
         * Retrieves order and prepares checkout data.
         *
         * @param $order_id string Order id
         * @return array Result
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Renders error message.
         *
         * @param $message string Message
         */
        private function render_error_message($message)
        {
            echo "<div class='craftgate-alert'>$message</div>";
        }

        /**
         * Builds payment items.
         *
         * @param $order object WooCommerce Order
         * @return array Items
         */
        private function build_items($order)
        {
            $order_items = $order->get_items();
            $items = [];

            if ($order_items) {
                foreach ($order_items as $order_item => $item) {
                    $items[] = [
                        'externalId' => $item->get_id(),
                        'name' => $item->get_name(),
                        'price' => $this->format_price($item->get_total())
                    ];
                }
            }
            return $items;
        }

        /**
         * Build initialize checkout form request.
         *
         * @param $order_id string Order id
         * @return array Checkout form request
         */
        private function build_init_checkout_form_request($order_id)
        {
            $order = $this->retrieve_order($order_id);

            return array(
                'price' => $this->format_price($order->get_total()),
                'paidPrice' => $this->format_price($order->get_total()),
                'currency' => \Craftgate\Model\Currency::TL,
                'paymentGroup' => \Craftgate\Model\PaymentGroup::LISTING_OR_SUBSCRIPTION,
                'conversationId' => $order_id,
                'callbackUrl' => get_bloginfo('url') . "?wc-api=craftgate_gateway_callback&order_id=" . $order_id,
                'items' => $this->build_items($order)
            );
        }

        /**
         * Checks if TRY currency is processed.
         *
         * @return bool Whether currency is supported or not
         */
        private function is_current_currency_supported()
        {
            return in_array(get_woocommerce_currency(), array(\Craftgate\Model\Currency::TL));
        }

        /**
         * Retrieves order.
         *
         * @param $order_id string Order id
         */
        private function retrieve_order($order_id)
        {
            return wc_get_order($order_id);
        }

        /**
         * Validates checkout form result parameters.
         */
        private function validate_handle_checkout_form_result_params()
        {
            if (!isset($_GET["order_id"]) || !isset($_POST["token"])) {
                throw new Exception(__('Your payment could not be processed.', $this->text_domain));
            }
        }

        /**
         * Validates if order id is equals to conversation id.
         *
         * @param $checkout_form_result array Result
         * @param $order_id string Order id
         */
        private function validate_order_id_equals_conversation_id($checkout_form_result, $order_id)
        {
            if (!isset($checkout_form_result->conversationId) || $checkout_form_result->conversationId != $order_id) {
                throw new Exception(__('Your payment could not be processed.', $this->text_domain));
            }
        }

        /**
         * Adds checkout form result json to metadata.
         *
         * @param $order object WooCommerce order
         * @param $checkout_form_result object Checkout Form result
         */
        private function update_order_checkout_form_result_metadata($order, $checkout_form_result)
        {
            $order->update_meta_data('craftgate_checkout_form_callback_params', json_encode($_POST));
            if (isset($checkout_form_result->id)) {
                $craftgate_payment_info = array(
                    'is_sandbox_payment' => $this->is_sandbox_active,
                    'payment_id' => $checkout_form_result->id,
                );
                $order->update_meta_data('craftgate_payment_info', json_encode($craftgate_payment_info));
            }
            $order->save();
        }

        /**
         * Formats price.
         *
         * @param $number float Price
         * @return float Formatted Price
         */
        private function format_price($number)
        {
            return round($number, 2);
        }

        /**
         * Builds admin options.
         */
        public function admin_options()
        {
            echo '<h3>Craftgate Payment Gateway</h3>';
            if ($this->is_current_currency_supported()) {
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            } else { ?>
                <p>
                    <?php _e('You can create your Craftgate Payment Gateway account <a href="https://craftgate.io" target="_blank"> here</a>.', $this->text_domain) ?>
                </p>
                <div class="inline error">
                    <p>
                        <strong><?php _e('Craftgate Payment Gateway is not available to use', $this->text_domain) ?></strong>: <?php _e('The only supported currency is TRY.', $this->text_domain) ?>
                    </p>
                </div>
            <?php }
        }

        /**
         * Initializes admin settings.
         */
        public function init_admin_settings_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', $this->text_domain),
                    'type' => 'checkbox',
                    'label' => __('Enable Craftgate', $this->text_domain),
                    'description' => __('Enable or disable the gateway.', $this->text_domain),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', $this->text_domain),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->text_domain),
                    'desc_tip' => false,
                    'default' => __('Pay with Debit/Credit Card', $this->text_domain),
                ),
                'description' => array(
                    'title' => __('Description', $this->text_domain),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', $this->text_domain),
                    'default' => __('You can pay with Debit and Credit Card', $this->text_domain),
                ),
                'live_api_key' => array(
                    'title' => __('Live API Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Live API Key.', $this->text_domain),
                    'default' => ''
                ),
                'live_secret_key' => array(
                    'title' => __('Live Secret Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Live Secret Key.', $this->text_domain),
                    'default' => ''
                ),
                'sandbox_api_key' => array(
                    'title' => __('Sandbox API Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Sandbox API Key.', $this->text_domain),
                    'default' => ''
                ),
                'sandbox_secret_key' => array(
                    'title' => __('Sandbox Secret Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Sandbox Secret Key.', $this->text_domain),
                    'default' => ''
                ),
                'is_sandbox_active' => array(
                    'title' => __('Sandbox Mode', $this->text_domain),
                    'type' => 'checkbox',
                    'label' => __('Enable Sandbox Mode', $this->text_domain),
                    'default' => 'no',
                    'description' => __('Enable test mode using sandbox API keys.', $this->text_domain),
                )
            );
        }
    }

    /**
     * Display craftgate payment url on the admin order page
     * @param $order
     */

    function show_craftgate_payment_url($order)
    {
        $meta = $order->get_meta('craftgate_payment_info');
        if (empty($meta)) {
            return;
        }

        $craftgate_payment_info = json_decode($meta);
        $url = 'https://panel.craftgate.io/payments/';

        // Checks if sandbox payment.
        if ($craftgate_payment_info->is_sandbox_payment) {
            $url = 'https://sandbox-panel.craftgate.io/payments/';
        }

        $url .= $craftgate_payment_info->payment_id;
        $link = "<a target='_blank' href='$url'>$url</a>";
        echo '<p><strong>' . __('Craftgate Payment URL','woocommerce-gateway-craftgate') . ':</strong> <br/>' . $link . '</p>';
    }

    add_action('woocommerce_admin_order_data_after_billing_address', 'show_craftgate_payment_url', 10, 1);


    /**
     * WooCommerce actions.
     *
     * @param $methods
     * @return mixed
     */
    function add_craftgate_gateway_to_wc_methods($methods)
    {
        $methods[] = 'WC_Craftgate_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_craftgate_gateway_to_wc_methods');

    /**
     * WooCommerce actions for links.
     *
     * @param $links
     * @param $file
     * @return mixed
     */
    function craftgate_plugin_action_links($links, $file)
    {
        static $this_plugin;
        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }
        if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=craftgate_gateway">' . __('Settings', 'woocommerce-gateway-craftgate') . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    add_filter('plugin_action_links', 'craftgate_plugin_action_links', 10, 2);
}

/**
 * Adds custom CSS files.
 */
function custom_style_sheet()
{
    wp_enqueue_style('custom-styling', plugin_dir_url(__FILE__) . '/assets/css/style.css');
}

add_action('wp_enqueue_scripts', 'custom_style_sheet');

/**
 * Uninstalls plugin.
 */
function deactivate_craftgate_plugin()
{
    delete_option('woocommerce_craftgate_gateway_settings');
}

register_deactivation_hook(__FILE__, 'deactivate_craftgate_plugin');