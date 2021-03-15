<?php
/*
 * Plugin Name: Woocommerce Gateway Craftgate
 * Plugin URI: https://craftgate.io
 * Description: Craftgate
 * Author: Craftgate
 * Author URI: https://craftgate.io
 * Version: 1.0.0
 */
require_once 'vendor/autoload.php';
include_once 'craftgate-client.php';

use Craftgate\Model\Currency;
use Craftgate\Model\PaymentGroup;

add_action('plugins_loaded', 'init_woocommerce_craftgate_gateway', 0);

function init_woocommerce_craftgate_gateway()
{

    if (!class_exists('WC_Payment_Gateway')) return;

    if (version_compare(phpversion(), '7.1', '>=')) {
        ini_set('serialize_precision', -1);
    }

    class WC_Craftgate_Gateway extends WC_Payment_Gateway
    {

        private $api_key;
        private $secret_key;
        private $craftgate_client;

        public function __construct()
        {
            $this->id = 'craftgate_gateway';
            $this->icon = plugins_url('images/card-brands.png', __FILE__);
            $this->has_fields = false;
            $this->method_title = 'Craftgate Gateway';
            $this->method_description = 'Craftgate Payment Gateway';
            $this->order_button_text = 'Banka/Kredi Kartı ile Öde';

            $this->init_admin_settings_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            $this->init_craftgate_client();
            $this->define_woocommerce_actions();

        }

        private function init_craftgate_client()
        {
            $is_sandbox_active = $this->get_option('is_sandbox_active') === 'yes';
            $api_key_option_name = 'live_api_key';
            $secret_key_option_name = 'live_secret_key';
            $api_url = 'https://api.craftgate.io';
            if ($is_sandbox_active) {
                $api_key_option_name = 'sandbox_api_key';
                $secret_key_option_name = 'sandbox_secret_key';
                $api_url = 'https://sandbox-api.craftgate.io';
            }
            $this->api_key = $this->get_option($api_key_option_name);
            $this->secret_key = $this->get_option($secret_key_option_name);
            $this->craftgate_client = new Craftgate_Client($this->api_key, $this->secret_key, $api_url);
        }

        private function define_woocommerce_actions()
        {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_craftgate_gateway', array($this, 'init_craftgate_checkout_form'));
            add_action('woocommerce_api_craftgate_gateway_callback', array($this, 'handle_craftgate_checkout_form_result'));
            add_action('woocommerce_before_thankyou', array($this, 'show_payment_error'));
        }


        public function init_craftgate_checkout_form($order_id = null)
        {
            try {
                $request = $this->build_init_checkout_form_request($order_id);
                $response = $this->craftgate_client->init_checkout_form($request);
                if (isset($response->pageUrl)) {
                    echo
                        '<div id="craftgate_payment_form">
					                       <iframe src="' . $response->pageUrl . '&iframe=true"></iframe>
					                   </div>';
                } else {
                    error_log(json_encode($response));
                    $this->render_error_message("Beklenmedik bir hata meydana geldi. ErrorCode: " . $response->errors->errorCode);
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $this->render_error_message("Beklenmedik bir hata meydana geldi.");
            }
        }

        public function handle_craftgate_checkout_form_result()
        {
            try {
                $this->validate_handle_checkout_form_result_params();
                $order_id = $_GET["order_id"];
                $order = $this->retrieve_order($order_id);

                $checkout_form_result = $this->craftgate_client->retrieve_checkout_form_result($_POST["token"]);

                $this->validate_order_id_equals_conversation_id($checkout_form_result, $order_id);
                $this->update_order_checkout_form_token($order);

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
                $this->render_error_message("Beklenmedik bir hata meydana geldi");
            }
        }

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
                Ödemeniz alınamamıştır. <br>
                <?php echo $message ?>
            </div>
            <?php
        }

        public function is_available()
        {
            return $this->is_current_currency_supported() && $this->enabled == "yes" && $this->api_key && $this->secret_key;
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        private function render_error_message($message)
        {
            echo "<div class='craftgate-alert'>$message</div>";
        }

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

        private function build_init_checkout_form_request($order_id)
        {
            $order = $this->retrieve_order($order_id);

            return array(
                'price' => $this->format_price($order->get_total()),
                'paidPrice' => $this->format_price($order->get_total()),
                'currency' => Currency::TL,
                'paymentGroup' => PaymentGroup::LISTING_OR_SUBSCRIPTION,
                'conversationId' => $order_id,
                'callbackUrl' => get_bloginfo('url') . "?wc-api=craftgate_gateway_callback&order_id=" . $order_id,
                'items' => $this->build_items($order)
            );
        }


        private function is_current_currency_supported()
        {
            return in_array(get_woocommerce_currency(), array('TRY'));
        }

        private function retrieve_order($order_id)
        {
            return wc_get_order($order_id);
        }


        private function validate_handle_checkout_form_result_params()
        {
            if (!isset($_GET["order_id"]) || !isset($_POST["token"])) {
                throw new Exception("Ödeme tamamlanamadı.");
            }
        }

        private function validate_order_id_equals_conversation_id($checkout_form_result, $order_id)
        {
            if (!isset($checkout_form_result->conversationId) || $checkout_form_result->conversationId != $order_id) {
                throw new Exception("Ödeme tamamlanamadı.");
            }
        }

        private function update_order_checkout_form_token($order)
        {
            $order->update_meta_data('craftgate_checkout_form_callback_params', json_encode($_POST));
            $order->save();
        }

        private function format_price($number)
        {
            return round($number, 2);
        }

        public function admin_options()
        {
            echo '<h3>Craftgate Payment Gateway</h3>';
            if ($this->is_current_currency_supported()) {
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            } else { ?>
                <p>
                    Craftgate Payment Gateway hesabınızı <a href="https://craftgate.io" target="_blank">buradan</a>
                    oluşturabilirsiniz.
                </p>
                <div class="inline error">
                    <p><strong>Craftgate Gateway ödeme methodu kullanılamaz</strong>: Sadece Türk lirası
                        desteklenmektedir.</p>
                </div>
            <?php }
        }

        public function init_admin_settings_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Craftgate Payment Gateway',
                    'description' => 'Enable or disable the gateway.',
                    'desc_tip' => true,
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'desc_tip' => false,
                    'default' => 'Banka/Kredi kartı ile öde'
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Kredi ve Banka Kartı kullanarak ödeme yapabilirsiniz.'
                ),
                'live_api_key' => array(
                    'title' => 'Api Key',
                    'type' => 'text',
                    'description' => 'Enter your API Live Key here.',
                    'default' => ''
                ),
                'live_secret_key' => array(
                    'title' => 'Secret Key',
                    'type' => 'text',
                    'description' => 'Enter your Secret Live Key here.',
                    'default' => ''
                ),
                'sandbox_api_key' => array(
                    'title' => 'Api Key [Sandbox]',
                    'type' => 'text',
                    'description' => 'Enter your API Key here.',
                    'default' => ''
                ),
                'sandbox_secret_key' => array(
                    'title' => 'Secret Key [Sandbox]',
                    'type' => 'text',
                    'description' => 'Enter your Secret Key here.',
                    'default' => ''
                ),
                'testing' => array(
                    'title' => 'Gateway Testing',
                    'type' => 'title',
                    'description' => '',
                ),
                'is_sandbox_active' => array(
                    'title' => 'Sandbox Mode',
                    'type' => 'checkbox',
                    'label' => 'Enable Sandbox Mode',
                    'default' => 'no',
                    'description' => 'Enable test mode.',
                )
            );
        }
    }

    function add_craftgate_gateway_to_wc_methods($methods)
    {
        $methods[] = 'WC_Craftgate_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_craftgate_gateway_to_wc_methods');

    function craftgate_plugin_action_links($links, $file)
    {
        static $this_plugin;
        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }
        if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=craftgate_gateway">Settings</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    add_filter('plugin_action_links', 'craftgate_plugin_action_links', 10, 2);


    function show_admin_panel_notice()
    {
        $craftgate_settings = get_option('woocommerce_craftgate_gateway_settings');
        $is_sandbox_active = $craftgate_settings['is_sandbox_active'] === 'yes';
        $sandbox_api_key = $craftgate_settings['sandbox_api_key'];
        $sandbox_secret_key = $craftgate_settings['sandbox_secret_key'];
        $live_api_key = $craftgate_settings['live_api_key'];
        $live_secret_key = $craftgate_settings['live_secret_key'];
        $api_key = $is_sandbox_active ? $sandbox_api_key : $live_api_key;
        $secret_key = $is_sandbox_active ? $sandbox_secret_key : $live_secret_key;
        if ($is_sandbox_active) {
            ?>
            <div class="error">
                <p>Craftgate test modu aktif.
                    <a href="<?php echo get_bloginfo('wpurl') ?>/wp-admin/admin.php?page=wc-settings&tab=checkout&section=craftgate_gateway">Buradan</a>
                    Canlı modu aktif edebilirsiniz.</p>
            </div>
            <?php
        }
        if (!($api_key && $secret_key)) {
            echo '<div class="error"><p>' . sprintf('Craftgate Payment Gateway metodu için <a href="%s">buradan</a> API Key ve SECRET Key bilgilerinizi giriniz.', admin_url('admin.php?page=wc-settings&tab=checkout&section=craftgate_gateway')) . '</p></div>';
        }
    }

    add_action('admin_notices', 'show_admin_panel_notice');
}

function custom_style_sheet()
{
    wp_enqueue_style('custom-styling', plugin_dir_url(__FILE__) . '/css/style.css');
}

add_action('wp_enqueue_scripts', 'custom_style_sheet');