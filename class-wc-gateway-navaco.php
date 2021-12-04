<?php
if (!defined('ABSPATH')) {
    exit;
}

function Load_navaco_Gateway()
{
    if (!function_exists('Woocommerce_Add_navaco_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_PN_navaco'))
	{
        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_navaco_Gateway');

        function Woocommerce_Add_navaco_Gateway($methods)
        {
            $methods[] = 'WC_PN_navaco';

            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_navaco_IR_currency');

        function add_navaco_IR_currency($currencies)
        {
            $currencies['IRR'] 	= __('ریال', 'woocommerce');
            $currencies['IRT'] 	= __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_navaco_IR_currency_symbol', 10, 2);

        function add_navaco_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }

            return $currency_symbol;
        }

        class WC_PN_navaco extends WC_Payment_Gateway
        {
            private $url = "https://fcp.shaparak.ir/nvcservice/Api/v2/";
            private $merchantCode;
            private $username;
            private $password;
            private $failedMassage;
            private $successMassage;

            public function __construct()
            {
                $this->id 					= 'WC_PN_navaco';
                $this->method_title 		= __('پرداخت امن navaco.ir', 'woocommerce');
                $this->method_description 	= __('تنظیمات درگاه پرداخت navaco.ir برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon 				= apply_filters('WC_PN_navaco_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');
                $this->has_fields 			= false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title 				= $this->settings['title'];
                $this->description 			= $this->settings['description'];

                $this->merchantCode 		= $this->settings['merchantcode'];
                $this->username 		    = $this->settings['username'];
                $this->password 		    = $this->settings['password'];

                $this->successMassage 		= $this->settings['success_massage'];
                $this->failedMassage 		= $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
				{
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_navaco_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_navaco_Gateway'));
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_PN_navaco_Config', array(
                        'base_config' => array(
                            'title' 		=> __('تنظیمات پایه ای', 'woocommerce'),
                            'type' 			=> 'title',
                            'description' 	=> '',
                        ),
                        'enabled' => array(
                            'title' 		=> __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' 			=> 'checkbox',
                            'label' 		=> __('فعالسازی درگاه navaco.ir', 'woocommerce'),
                            'description' 	=> __('برای فعالسازی درگاه پرداخت navaco.ir باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' 		=> 'yes',
                            'desc_tip' 		=> true,
                        ),
                        'title' => array(
                            'title' 		=> __('عنوان درگاه', 'woocommerce'),
                            'type' 			=> 'text',
                            'description' 	=> __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' 		=> __('پرداخت امن navaco.ir', 'woocommerce'),
                            'desc_tip' 		=> true,
                        ),
                        'description' => array(
                            'title' 		=> __('توضیحات درگاه', 'woocommerce'),
                            'type' 			=> 'text',
                            'desc_tip' 		=> true,
                            'description' 	=> __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' 		=> __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه navaco.ir', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' 		=> __('تنظیمات حساب navaco.ir', 'woocommerce'),
                            'type' 			=> 'title',
                            'description' 	=> '',
                        ),
                        'merchantcode' => array(
                            'title' 		=> __('مرچنت کد', 'woocommerce'),
                            'type' 			=> 'text',
                            'description' 	=> __(' کد درگاه navaco.ir', 'woocommerce'),
                            'default' 		=> '',
                            'desc_tip' 		=> true
                        ),
                        'username' => array(
                            'title' 		=> __('نام کاربری', 'woocommerce'),
                            'type' 			=> 'text',
                            'description' 	=> __(' نام کاربری درگاه navaco.ir', 'woocommerce'),
                            'default' 		=> '',
                            'desc_tip' 		=> true
                        ),
                        'password' => array(
                            'title' 		=> __('پسورد', 'woocommerce'),
                            'type' 			=> 'text',
                            'description' 	=> __(' پسورد درگاه navaco.ir', 'woocommerce'),
                            'default' 		=> '',
                            'desc_tip' 		=> true
                        ),
                        'payment_config' => array(
                            'title' 		=> __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' 			=> 'title',
                            'description' 	=> '',
                        ),
                        'success_massage' => array(
                            'title' 		=> __('پیام پرداخت موفق', 'woocommerce'),
                            'type' 			=> 'textarea',
                            'description' 	=> __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) navaco.ir استفاده نمایید .', 'woocommerce'),
                            'default' 		=> __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' 		=> __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' 			=> 'textarea',
                            'description' 	=> __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت navaco.ir ارسال میگردد .', 'woocommerce'),
                            'default' 		=> __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);

                return array(
                    'result' 	=> 'success',
                    'redirect' 	=> $order->get_checkout_payment_url(true)
                );
            }

            public function SendRequestTonavaco($action, $params)
            {
				try {
					$curl = curl_init();
					curl_setopt($curl, CURLOPT_URL, $this->url.$action);
					curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
					curl_setopt($curl, CURLOPT_TIMEOUT, 30);
					curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Accept: application/json"));

					$curl_exec = curl_exec($curl);
					curl_close($curl);

					return json_decode($curl_exec);

                } catch (Exception $ex) {
                    return false;
                }
            }

            public function Send_to_navaco_Gateway($order_id)
            {
                global $woocommerce;

                $woocommerce->session->order_id_navaco 	= $order_id;

				$order 		= new WC_Order($order_id);
                $currency 	= $order->get_currency();
                $currency 	= apply_filters('WC_PN_navaco_Currency', $currency, $order_id);

                $form = '<form action="" method="POST" class="navaco-checkout-form" id="navaco-checkout-form">
						<input type="submit" name="navaco_submit" class="button alt" id="navaco-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';

				$form = apply_filters('WC_PN_navaco_Form', $form, $order_id, $woocommerce);

                do_action('WC_PN_navaco_Gateway_Before_Form', $order_id, $woocommerce);

				echo $form;

                do_action('WC_PN_navaco_Gateway_After_Form', $order_id, $woocommerce);

                $Amount 			= (int)$order->order_total;
                $Amount 			= apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                $strToLowerCurrency = strtolower($currency);

				if (
                    ($strToLowerCurrency === strtolower('IRT')) ||
                    ($strToLowerCurrency === strtolower('TOMAN')) ||
                    $strToLowerCurrency === strtolower('Iran TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                    $strToLowerCurrency === strtolower('تومان') ||
                    $strToLowerCurrency === strtolower('تومان ایران')
                ) {
                    $Amount *= 1;
                } else if (strtolower($currency) === strtolower('IRHT')) {
                    $Amount *= 1000;
                } else if (strtolower($currency) === strtolower('IRHR')) {
                    $Amount *= 100;
                } else if (strtolower($currency) === strtolower('IRR')) {
                    $Amount /= 10;
                }

                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_navaco_gateway', $Amount, $currency);

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_PN_navaco'));

                $products 		= array();
                $order_items 	= $order->get_items();

                foreach ($order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }

                $products = implode(' - ', $products);

                $Description 	= 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
                $Mobile 		= get_post_meta($order_id, '_billing_phone', true) ?: '-';


                //Hooks for iranian developer
                $Description 	= apply_filters('WC_PN_navaco_Description', $Description, $order_id);
                $Mobile 		= apply_filters('WC_PN_navaco_Mobile', $Mobile, $order_id);

                do_action('WC_PN_navaco_Gateway_Payment', $order_id, $Description, $Mobile);

                $postField = [
                    "CARDACCEPTORCODE"=>$this->merchantCode,
                    "USERNAME"=>$this->username,
                    "USERPASSWORD"=>$this->password,
                    "PAYMENTID"=>$order_id,
                    "AMOUNT"=>$Amount,
                    "CALLBACKURL"=>($CallbackUrl),
                ];

                $result = $this->SendRequestTonavaco('PayRequest', $postField);

			   if ($result === false)
			   {
                    echo 'cURL Error #:' . $err;
                } else if (isset($result->ActionCode) && $result->ActionCode == 0) {
                    wp_redirect($result->RedirectUrl);
                    exit;
                } else {

					$errCheckRes 	= (isset($result->ActionCode) && $result->ActionCode != "") ? $result->ActionCode : "Error connecting to web service";
                    $Message 		= ' تراکنش ناموفق بود- کد خطا : ' . $errCheckRes;
                    $Fault 			= '';
                }

                if (!empty($Message) && $Message)
				{
                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_PN_navaco_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);

                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_PN_navaco_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);

					if ($Notice)
					{
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_PN_navaco_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_navaco_Gateway()
            {
                $InvoiceNumber = isset($_POST['InvoiceNumber']) ? $_POST['InvoiceNumber'] : '';

                global $woocommerce;

                if (isset($_GET['wc_order']))
				{
                    $order_id = $_GET['wc_order'];
                } else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_navaco;
                    unset($woocommerce->session->order_id_navaco);
                }
                if ($order_id)
				{
                    $order 		= new WC_Order($order_id);
                    $currency 	= $order->get_currency();
                    $currency 	= apply_filters('WC_PN_navaco_Currency', $currency, $order_id);

                    if ($order->status !== 'completed')
					{
						if (isset($_POST['Data']) )
						{
                            $data = $_POST['Data'];
                            $data = str_replace('\\',"",$data);
                            $data = json_decode($data);

                            if ($data->ActionCode == 0) {

                                $MerchantID = $this->merchantCode;
                                $username = $this->username;
                                $password = $this->password;

                                $postField = [
                                    "CARDACCEPTORCODE"=>$MerchantID,
                                    "USERNAME"=>$username,
                                    "USERPASSWORD"=>$password,
                                    "PAYMENTID"=>$order_id,
                                    "RRN"=>$data->RRN,
                                ];
                                $result = $this->SendRequestTonavaco('Confirm', $postField);

                                if (isset($result->ActionCode) && $result->ActionCode == 0) {
                                    $Status = 'completed';
                                    $Transaction_ID = $result->RRN;
                                    $Fault = '';
                                    $Message = '';
                                } else {
                                    $errStatus = (isset($result->ActionCode) && $result->ActionCode != "") ? $result->ActionCode : "Error connecting to web service";
                                    $Status = 'failed';
                                    $Fault = $errStatus;
                                    $Message = 'تراکنش ناموفق بود';
                                }
                            }
                            else{
                                $Status 	= 'failed';
                                $Fault 		= 'Transaction Canceled By User';
                                $Message 	= 'تراکنش ناموفق بود';
                            }
						} else {
							$Status 	= 'failed';
							$Fault 		= 'Transaction Canceled By User';
							$Message 	= 'تراکنش ناموفق بود';
						}

                        if ($Status === 'completed' && isset($Transaction_ID) && $Transaction_ID !== 0)
						{
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);

                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_PN_navaco_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);

                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->successMassage));
                            $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);
                            $Notice = apply_filters('WC_PN_navaco_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);

                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_PN_navaco_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }

                        if (($Transaction_ID && ($Transaction_ID != 0)))
						{
                            $tr_id = ('<br/>توکن : ' . $Transaction_ID);
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);
                        $Note = apply_filters('WC_PN_navaco_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);

                        if ($Note)
						{
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));
                        $Notice = str_replace(array('{transaction_id}', '{fault}'), array($Transaction_ID, $Message), $Notice);
                        $Notice = apply_filters('WC_PN_navaco_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);

                        if ($Notice)
						{
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_PN_navaco_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }

                    $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                    $Notice = wpautop(wptexturize($this->successMassage));
                    $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);
                    $Notice = apply_filters('WC_PN_navaco_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);

                    if ($Notice)
					{
                        wc_add_notice($Notice, 'success');
                    }

                    do_action('WC_PN_navaco_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $Fault 	= __('شماره سفارش وجود ندارد .', 'woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_PN_navaco_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);

                if ($Notice)
				{
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_PN_navaco_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }
        }
    }
}

add_action('plugins_loaded', 'Load_navaco_Gateway', 0);