<?php
/**
 * Plugin Name: Craftgate Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/craftgate-payment-gateway/
 * Description: Accept debit/credit card payments easily and directly on your WordPress site using Craftgate.
 * Author: Craftgate
 * Author URI: https://craftgate.io/
 * Version: 1.0.12
 * Requires at least: 4.4
 * Tested up to: 6.0
 * WC requires at least: 3.0.0
 * WC tested up to: 8.6.1
 * Requires PHP: 5.6
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: craftgate-payment-gateway
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_woocommerce_craftgate_gateway', 0);

/**
 * Initializes Craftgate payment gateway
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

    load_plugin_textdomain('craftgate-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

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

        /**
         * Global variable for text domain.
         *
         * @var string Text domain
         */
        private $text_domain = 'craftgate-payment-gateway';

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
            add_action('woocommerce_api_craftgate_gateway_webhook', array($this, 'handle_craftgate_webhook_result'));
        }

        /**
         * Initializes Craftgate checkout form.
         *
         * @param null $order_id string Order id
         */
        public function init_craftgate_checkout_form($order_id = null)
        {
            try {
                $this->set_cookie_same_site();
                $request = $this->build_init_checkout_form_request($order_id);
                $response = $this->craftgate_api->init_checkout_form($request);

                if (isset($response->pageUrl)) {
                    $language = $this->get_option("language");
                    $iframeOptions = $this->get_option("iframe_options");
                    echo '<div id="craftgate_payment_form"><iframe src="' . $response->pageUrl . '&iframe=true&lang=' . $language . '&' . $iframeOptions . '"></iframe></div>';
                    ?>
                    <script>
                        window.addEventListener("message", function (event) {
                            const {type, value} = event.data;
                            if (type === 'HEIGHT_CHANGED') {
                                document.getElementById('craftgate_payment_form').style.height = value + 'px';
                            }
                        });
                    </script>
                    <?php
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
         * Check payment is processed before
         */
        private function is_payment_processed_before($checkout_token)
        {
            $orders = wc_get_orders(array(
                'limit' => -1,
                'meta_key' => 'craftgate_checkout_token',
                'meta_value' => $checkout_token,
                'meta_compare' => '=',
                'return' => 'objects'
            ));

            foreach ($orders as $order) {
                if (!in_array($order->get_status(), ['failed', 'pending'])) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Decide whether process webhook request and return checkout token
         */
        private function should_process_webhook_request($webhook_data)
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return false;
            }

            $event_type = wc_clean($webhook_data['eventType']);
            $status = wc_clean($webhook_data['status']);
            $checkout_token = wc_clean($webhook_data['payloadId']);

            if ($event_type !== 'CHECKOUTFORM_AUTH' || $status !== "SUCCESS" || !isset($checkout_token) || $this->is_payment_processed_before($checkout_token)) {
                return false;
            }
            return true;
        }

        /**
         * Handles Craftgate webhook result.
         */
        public function handle_craftgate_webhook_result()
        {
            $webhook_data = json_decode(file_get_contents('php://input'), true);
            if (!isset($webhook_data) || !$this->should_process_webhook_request($webhook_data)) {
                exit();
            }

            $checkout_token = wc_clean($webhook_data['payloadId']);;
            $checkout_form_result = $this->retrieve_checkout_form_result($checkout_token);
            if ($checkout_form_result->paymentStatus !== 'SUCCESS') {
                exit();
            }

            $conversationId = $checkout_form_result->conversationId;
            if (!isset($conversationId)) {
                exit();
            }
            $order = $this->retrieve_order($conversationId);
            $this->update_order_checkout_form_result_metadata($order, $checkout_form_result, $checkout_token);
            $this->complete_order_for_success_payment($checkout_form_result, $order);
            exit();
        }

        /**
         * Completes order for success payment.
         */
        private function complete_order_for_success_payment($checkout_form_result, $order)
        {
            global $woocommerce;
            if ($order->get_status() !== 'pending' && $order->get_status() !== 'failed') {
                return;
            }

            if ($checkout_form_result->installment > 1) {
                $this->update_order_for_installment($order, $checkout_form_result);
            }

            $customer_id = $order->get_user()->ID;
            if (isset($checkout_form_result->cardUserKey) && $this->retrieve_card_user_key($customer_id, $this->api_key) != $checkout_form_result->cardUserKey) {
                $this->save_card_user_key($customer_id, $checkout_form_result->cardUserKey, $this->api_key);
            }

            $order->payment_complete();
            $orderMessage = 'Payment ID: ' . $checkout_form_result->id;
            $order->add_order_note($orderMessage, 0, true);
            WC()->cart->empty_cart();
            $woocommerce->cart->empty_cart();
            wc_empty_cart();
        }

        /**
         * Handles Craftgate checkout result.
         */
        public function handle_craftgate_checkout_form_result()
        {
            try {
                $this->validate_handle_checkout_form_result_params();
                $order_id = wc_clean($_GET["order_id"]);
                $order = $this->retrieve_order($order_id);
                if ($order->get_status() == 'processing') {
                    echo "<script>window.top.location.href = '" . $this->get_return_url($order) . "';</script>";
                    exit;
                }

                $checkout_token = wc_clean($_POST["token"]);
                $checkout_form_result = $this->retrieve_checkout_form_result($checkout_token);
                $this->validate_order_id_equals_conversation_id($checkout_form_result, $order_id);
                $this->update_order_checkout_form_result_metadata($order, $checkout_form_result, $checkout_token);

                // Checks payment error.
                if (!isset($checkout_form_result->paymentError) && $checkout_form_result->paymentStatus === 'SUCCESS') {
                    $this->complete_order_for_success_payment($checkout_form_result, $order);
                    echo "<script>window.top.location.href = '" . $this->get_return_url($order) . "';</script>";
                } else {
                    $error = $checkout_form_result->paymentError->errorCode . ' - ' . $checkout_form_result->paymentError->errorDescription . ' - ' . $checkout_form_result->paymentError->errorGroup;
                    $order->update_meta_data('craftgate_payment_error', $error);
                    $order->update_status('failed', $error);
                    $order->save();
                    wc_add_notice(__($checkout_form_result->paymentError->errorDescription, $this->text_domain), 'error');
                    echo "<script>window.top.location.href = '" . wc_get_checkout_url() . "';</script>";
                }

                exit;
            } catch (Exception $e) {
                error_log($e->getMessage());
                wc_add_notice(__($e->getMessage(), $this->text_domain), 'error');
                echo "<script>window.top.location.href = '" . wc_get_checkout_url() . "';</script>";
                $this->render_error_message(__('An error occurred. Error Code: ', $this->text_domain) . '-2');
            }
        }

        private function retrieve_checkout_form_result($checkout_token)
        {
            $GLOBALS["cg-lang-header"] = $this->get_option("language");
            return $this->craftgate_api->retrieve_checkout_form_result($checkout_token);
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
                'redirect' => $order->get_checkout_payment_url(true),
            );
        }

        /** Adds tax fee to order.
         * @param $order object WooCommerce Order
         * @param $checkout_form_result object Checkout Form Result
         */
        private function update_order_for_installment($order, $checkout_form_result)
        {
            $installment = $checkout_form_result->installment;
            $order_amount = $this->format_price($order->get_total());
            $installment_fee = $checkout_form_result->paidPrice - $order_amount;
            $order_fee = new stdClass();
            $order_fee->id = 'Installment Fee';
            $order_fee->name = __('Installment Fee', $this->text_domain);
            $order_fee->amount = $installment_fee;
            $order_fee->taxable = false;
            $order_fee->tax = 0;
            $order_fee->tax_data = array();
            $order_fee->tax_class = '';
            $order->add_fee($order_fee);
            $order->calculate_totals(true);
            $order->update_meta_data('craftgate_installment_number', esc_sql($installment));
            $order->update_meta_data('craftgate_installment_fee', $installment_fee);
        }

        private function retrieve_card_user_key($customer_id, $api_key)
        {
            if (!isset($customer_id)) {
                return null;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'craftgate_card';
            $query = $wpdb->prepare("
                 SELECT card_user_key FROM {$table_name}
                 WHERE  customer_id = %d AND api_key = %s 
                 ORDER BY craftgate_card_id DESC LIMIT 1;
                ", $customer_id, $api_key
            );

            $result = $wpdb->get_col($query);
            if (isset($result[0])) {
                return $result[0];
            } else {
                return null;
            }
        }

        private function save_card_user_key($customer_id, $card_user_key, $api_key)
        {
            if (!isset($customer_id)) {
                return;
            }
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'craftgate_card',
                array(
                    'customer_id' => $customer_id,
                    'card_user_key' => $card_user_key,
                    'api_key' => $api_key
                ),
                array(
                    '%d',
                    '%s',
                    '%s',
                )
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
                        'price' => $this->format_price($item->get_total()),
                    ];
                }
            }
            if ($order->get_shipping_total() > 0) {
                $items[] = [
                    'externalId' => 'shipping-total',
                    'name' => __('Shipping Total', $this->text_domain),
                    'price' => $order->get_shipping_total(),
                ];
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
            $customer_id = $order->get_user()->ID;
            $items = $this->build_items($order);
            $total_price = 0;
            foreach ($items as $item) {
                $total_price += $item['price'];
            }
            $init_checkout_form_request = array(
                'price' => $this->format_price($total_price),
                'paidPrice' => $this->format_price($order->get_total()),
                'currency' => $order->get_currency(),
                'paymentGroup' => \Craftgate\Model\PaymentGroup::LISTING_OR_SUBSCRIPTION,
                'conversationId' => $order_id,
                'callbackUrl' => rtrim(get_bloginfo('url'), '/') . '/' . "?wc-api=craftgate_gateway_callback&order_id=" . $order_id,
                'disableStoreCard' => $customer_id == null,
                'items' => $items,
            );

            $card_user_key = $this->retrieve_card_user_key($customer_id, $this->api_key);
            if ($card_user_key != null) {
                $init_checkout_form_request['cardUserKey'] = $card_user_key;
            }

            if ($order->get_billing_email() && strlen(trim($order->get_billing_email())) > 0) {
                $init_checkout_form_request['additionalParams'] = array(
                    'buyerEmail' => $order->get_billing_email()
                );
            }
            return $init_checkout_form_request;
        }

        /**
         * Checks if TRY currency is processed.
         *
         * @return bool Whether currency is supported or not
         */
        private function is_current_currency_supported()
        {
            return in_array(get_woocommerce_currency(), array(\Craftgate\Model\Currency::TL, \Craftgate\Model\Currency::USD, \Craftgate\Model\Currency::EUR, \Craftgate\Model\Currency::GBP));
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
            $clean_orderid = wc_clean($_GET["order_id"]);
            $clean_token = wc_clean($_POST["token"]);
            if (!isset($clean_orderid) || !isset($clean_token)) {
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
        private function update_order_checkout_form_result_metadata($order, $checkout_form_result, $checkout_token)
        {
            if (isset($checkout_form_result->id)) {
                $order->update_meta_data('environment', $this->is_sandbox_active ? 'sandbox' : 'live');
                $order->update_meta_data('craftgate_payment_id', $checkout_form_result->id);
                $order->update_meta_data('craftgate_checkout_token', $checkout_token);
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
                    <?php _e('You can create your Craftgate Payment Gateway account <a href="https://craftgate.io/" target="_blank"> here</a>.', $this->text_domain) ?>
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
                    'default' => 'yes',
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
                    'default' => '',
                ),
                'live_secret_key' => array(
                    'title' => __('Live Secret Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Live Secret Key.', $this->text_domain),
                    'default' => '',
                ),
                'sandbox_api_key' => array(
                    'title' => __('Sandbox API Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Sandbox API Key.', $this->text_domain),
                    'default' => '',
                ),
                'sandbox_secret_key' => array(
                    'title' => __('Sandbox Secret Key', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Enter your Sandbox Secret Key.', $this->text_domain),
                    'default' => '',
                ),
                'is_sandbox_active' => array(
                    'title' => __('Sandbox Mode', $this->text_domain),
                    'type' => 'checkbox',
                    'label' => __('Enable Sandbox Mode', $this->text_domain),
                    'default' => 'no',
                    'description' => __('Enable test mode using sandbox API keys.', $this->text_domain),
                ),
                'language' => array(
                    'title' => __('Language', $this->text_domain),
                    'type' => 'select',
                    'options' => array(
                        'tr' => 'Turkish',
                        'en' => 'English',
                    ),
                    'description' => __('Change the language of payment form.', $this->text_domain),
                    'default' => 'tr',
                ),
                'iframe_options' => array(
                    'title' => __('Iframe Options', $this->text_domain),
                    'type' => 'text',
                    'description' => __('Example: hideFooter=true&animatedCard=true', $this->text_domain),
                    'default' => '',
                ),
                'webhook_url' => array(
                    'title' => __('Webhook URL', $this->text_domain),
                    'type' => 'text',
                    'class' => 'disabled',
                    'css' => 'pointer-events:none',
                    'description' => __('The URL that payment results will be sent to on the server-side. You should enter this webhook address to Craftgate Merchant Panel to get webhook request. You can see details <a href="https://developer.craftgate.io/webhook">here</a>.', $this->text_domain),
                    'default' => rtrim(get_bloginfo('url'), '/') . '/' . "?wc-api=craftgate_gateway_webhook",
                ),
            );
        }

        /**
         * Sets samesite property of woocommerce session related cookie
         */
        private function set_cookie_same_site()
        {
            $wooCommerceCookieKey = 'wp_woocommerce_session_';
            foreach ($_COOKIE as $name => $value) {
                if (stripos($name, $wooCommerceCookieKey) === 0) {
                    $wooCommerceCookieKey = $name;
                }
            }
            $wooCommerceCookieKey = sanitize_text_field($wooCommerceCookieKey);
            $this->set_cookie($wooCommerceCookieKey, $_COOKIE[$wooCommerceCookieKey], time() + 86400, "/", $_SERVER['SERVER_NAME'], true, true);
        }

        /** Sets cookie.
         * @param $name string Name
         * @param $value string Value
         * @param $expire int Expire
         * @param $path string Path
         * @param $domain string Domain
         * @param $secure bool Secure
         * @param $httponly bool HttpOnly
         */
        private function set_cookie($name, $value, $expire, $path, $domain, $secure, $httponly)
        {
            if (PHP_VERSION_ID < 70300) {
                setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
            } else {
                setcookie($name, $value, [
                    'expires' => $expire,
                    'path' => $path,
                    'domain' => $domain,
                    'samesite' => 'None',
                    'secure' => $secure,
                    'httponly' => $httponly,
                ]);

            }
        }

        public function get_icon_url()
        {
            return $this->icon;
        }
    }

    /**
     * Display craftgate payment url on the admin order page
     * @param $order
     */

    function show_craftgate_payment_url($order)
    {
        $environment = $order->get_meta('environment');
        $craftgate_payment_id = $order->get_meta('craftgate_payment_id');

        if (!isset($environment) || !isset($craftgate_payment_id)) {
            return;
        }

        $url = 'https://panel.craftgate.io/payments/';

        // Checks if sandbox payment.
        if ($environment == 'sandbox') {
            $url = 'https://sandbox-panel.craftgate.io/payments/';
        }

        $url .= $craftgate_payment_id;
        $link = "<a target='_blank' href='$url'>$url</a>";
        echo '<p><strong>' . __('Craftgate Payment URL', 'craftgate-payment-gateway') . ':</strong> <br/>' . $link . '</p>';
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
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=craftgate_gateway">' . __('Settings', 'craftgate-payment-gateway') . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    add_filter('plugin_action_links', 'craftgate_plugin_action_links', 10, 2);

    /**
     * Add blocks compatibility support
     */
    function add_blocks_compatibility_support()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    /**
     * Add custom order tables compatibility support
     */
    function add_custom_order_tables_compatibility_support()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    add_action('before_woocommerce_init', 'add_blocks_compatibility_support');
    add_action('before_woocommerce_init', 'add_custom_order_tables_compatibility_support');

    /**
     * Register payment method type
     */
    function register_payment_method()
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
        require_once plugin_dir_path(__FILE__) . 'includes/blocks/class-wc-craftgate-blocks.php';

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_Craftgate_Gateway_Blocks_Support());
            }
        );
    }

    // Hook the custom function to the 'woocommerce_blocks_loaded' action
    add_action('woocommerce_blocks_loaded', 'register_payment_method');
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

/**
 * Activate plugin.
 */
function activate_craftgate_plugin()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'craftgate_card';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
                craftgate_card_id INT(11) NOT NULL AUTO_INCREMENT,
                customer_id INT(11) NOT NULL,
                card_user_key varchar(255) NOT NULL,
                api_key varchar(50) NOT NULL,
                created_at  TIMESTAMP DEFAULT current_timestamp,
               PRIMARY KEY (craftgate_card_id)
            ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_deactivation_hook(__FILE__, 'deactivate_craftgate_plugin');
register_activation_hook(__FILE__, 'activate_craftgate_plugin');
