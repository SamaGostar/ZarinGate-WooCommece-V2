<?php
/*
Plugin Name: (زرین گیت)درگاه پرداخت زرین پال ووکامرس
Plugin URI: http://www.Zarinpal.com
Description: افزونه درگاه پرداخت زرین پال فروشگاه ساز ووکامرس
Version: 2.3
Author: MasoudAmini
Author URI: http://www.masoudamini.ir
 */

if (!class_exists('nusoap_client')) {
	require_once("nusoap.php");
}
add_action('plugins_loaded', 'woocommerce_zarinpalzg_init', 0);

function woocommerce_zarinpalzg_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	if($_GET['msg']!=''){
        add_action('the_content', 'showzarinpalzgMessage');
    }

    function showzarinpalzgMessage($content){
            $neclss = htmlentities($_GET['type']);
            if($neclss == 'success')
                $neclss = 'message';
            return '<div class="box '.htmlentities($_GET['type']).'-box woocommerce-'.$neclss.'">'.urldecode($_GET['msg']).'</div>'.$content;
    }

    class WC_Zarinpalwg_Pay extends WC_Payment_Gateway {
	protected $msg = array();
        public function __construct(){
            // Go wild in here
            $this -> id = 'zarinpalzg';
            $this -> method_title = __('زرین پال', 'mrova');
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];

            $this -> merchantid = $this -> settings['merchantid'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];

            $this -> msg['reversal'] = "";
			$this -> msg['status'] = "";
			$this -> msg['message'] = "";
            $this -> msg['class'] = "";

            add_action( 'woocommerce_api_wc_zarinpalzg_pay' , array( $this, 'check_zarinpalzg_response' ) );
            add_action('valid-zarinpalzg-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_zarinpalzg', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_zarinpalzg',array($this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('فعال / غیر فعال کردن', 'mrova'),
                    'type' => 'checkbox',
                    'label' => __('انتخاب وضعیت درگاه پرداخت زرین پال', 'mrova'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('عنوان درگاه', 'mrova'),
                    'type'=> 'text',
                    'description' => __('عنوان درگاه در هنگام انتخاب درگاه پرداخت', 'mrova'),
                    'default' => __('پرداخت از طریق درگاه پرداخت زرین پال', 'mrova')),
                'description' => array(
                    'title' => __('توضیحات درگاه', 'mrova'),
                    'type' => 'textarea',
                    'description' => __('توضیحات نوشته شده در زیر لوگوی درگاه هنگام پرداخت', 'mrova'),
                    'default' => __('پرداخت با استفاده از درگاه برداخت زرین پال از طریق کلبه کارت های بانکی عضو شتاب', 'mrova')),
                'merchantid' => array(
                    'title' => __('شناسه درگاه', 'mrova'),
                    'type' => 'text',
                    'description' => __('شناسه درگاه يا همان MerchantID')),
                'redirect_page_id' => array(
                    'title' => __('برگه بازگشت'),
                    'type' => 'select',
                    'options' => $this -> get_pages('انتخاب برگه'),
                    'description' => __('ادرس بازگشت از پرداخت در هنگام پرداخت')
                )
            );


        }
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('درگاه پرداخت زرین پال', 'mrova').'</h3>';

            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for zarinpalzg, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){
            echo '<p>'.__('از سفارش شما متشکريم ، تا انتقال به درگاه پرداخت چند لحظه منتظر بمانيد ...', 'mrova').'</p>';
            echo $this -> generate_zarinpalzg_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
        }
        /**
         * Check for valid zarinpalzg server callback
         **/
       function check_zarinpalzg_response(){
        global $woocommerce;
		$client = new nusoap_client('https://de.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
		$client->soap_defencoding = 'UTF-8';
        $Status = $_GET['Status'];
		$Authority = $_GET['Authority'];

		$MerchantID = $this -> merchantid;
		$order_id = $woocommerce->session->zarinpalzg_woo_id;
		$order = new WC_Order($order_id);
		if($Status != "OK"){
print_r($Status);
    		$this -> msg['status'] = -1;
    		$this -> msg['message']= 'خطا در عمليات پرداخت ! عمليا پرداخت با موفقيت به پايان نرسيده است !';
		}
		if( $Status == "OK")
        {
            	if($order -> status !=='completed')
                {
				
							$result = $client->call("PaymentVerification", array(
					array(
							'MerchantID'	 => $MerchantID ,
							'Authority' 	 => $Authority ,
							'Amount'	 	=> $order->order_total
						)
					));
					
				

						// Check for a fault
                        $Status2 = $result['Status'];
                        $PayPrice = $result['verifyPaymentResult']['PayementedPrice'];

						if(strtolower($Status2) == 100 )
						{
                                unset($woocommerce->session->zarinpalzg_woo_id);
                                $this -> msg['status'] = 1;
                                $this -> msg['message'] ='پرداخت با موفقیت انجام گردید.';
                                $order -> payment_complete();
                                $order -> add_order_note('پرداخت انجام گردید<br/>شماره رسيد پرداخت: '.$result['RefID'] );
                                $order -> add_order_note($this -> msg['message']);
                                $woocommerce -> cart -> empty_cart();
						}
						else
						{
                                $this -> msg['status'] = -88;
                                $this -> msg['message'] = 'خطايي در اعتبار سنجي پرداخت به وجود آمده است ! وضعيت خطا : '.$Status2;
						}

					}

            }else
    		{
    			$this -> msg['status'] = -80;
    			$this -> msg['message'] = 'خطا در عمليات پرداخت ! عمليات پرداخت با موفقيت به پايان نرسيده است !';
    		}



            if ($this -> msg['status'] == 1){

				$this -> msg['class']='success';
			}
            else
            {
			    $order -> add_order_note($this -> msg['message']);
				$this -> msg['class']='error';
			}
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
            //For wooCoomerce 2.0
            $redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );

            wp_redirect( $redirect_url );
            exit;
}



        
        /**
         * Generate zarinpalzg button link
         **/

      public function generate_zarinpalzg_form($order_id){
            global $woocommerce;
            $order = &new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );



		$client = new nusoap_client('https://de.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
		$client->soap_defencoding = 'UTF-8';
		$MerchantID = $this -> merchantid;
		$orderId=rand(1, 9999999999999);
		unset($woocommerce->session->zarinpalzg_woo_id);
		$woocommerce->session->zarinpalzg_woo_id = $order_id;
		$amount = str_replace(".00", "", $order -> order_total);;
		$callBackUrl = $redirect_url;
		$payerId = '0';

		
		$result = $client->call("PaymentRequest", array(
                array(
                                        'MerchantID'         => $MerchantID ,
                                        'Amount'         => $amount ,
                                        'Description'         => 'پرداخت سفارش شماره '. $order_id ,
                                        'Email'         => $order->billing_email ,
                                        'Mobile'         => $order->billing_phone ,
                                        'CallbackURL'         => $callBackUrl

                                        )
         ));
		 

		// Check for a fault
		if ($client->fault) {
			echo '<h2>Fault</h2><pre>';
            print_r($result);
            echo '</pre>';
		}
		else {
			// Check for errors

            $Status2 = $result['Status'];
			$PayPath = 'https://www.zarinpal.com/pg/StartPay/' . $result['Authority'] .'/ZarinGate';
			if ($Status2 != 100 ) {
				$this -> msg['class'] = 'error';
                $this -> msg['message'] = $Status;
                echo '<div class="woocommerce-error"> در اتصال به درگاه پرداخت خطايي رخ داده است ! وضعيت خطا : '.$Status2.'</div>';
			} 
			else {
				// Display the result

                $send_atu="<script language='JavaScript' type='text/javascript'>
                <!--
                document.getElementById('checkout_confirmation').submit();
                //-->
                </script>";
                echo '
                <form id="checkout_confirmation" method="post" action="'.$PayPath.'" style="margin:0px"  >
                <input type="submit" value="در صورت عدم انتقال اینجا کلیک کنید"  />
                </form>'.$send_atu ;
			}
		}

			
			
	        if($this -> msg['class']=='error')
	            $order -> add_order_note($this->msg['message']);

        }

	private function western_to_persian($str) {
	$alphabet = array (
		'Û°' => '۰', 'Û±' => '۱', 'Û²' => '۲', 'Û³' => '۳', 'Û´' => '۴', 'Ûµ' => '۵', 'Û¶' => '۶', 'Û·' => '۷', 'Û¸' => '۸',
		'Û¹' => '۹', 'Ø¢' => 'آ', 'Ø§' => 'ا', 'Ø£' => 'أ', 'Ø¥' => 'إ', 'Ø¤' => 'ؤ', 'Ø¦' => 'ئ', 'Ø¡' => 'ء', 'Ø¨' => 'ب',
		'Ù¾' => 'پ', 'Øª' => 'ت', 'Ø«' => 'ث', 'Ø¬' => 'ج', 'Ú†' => 'چ', 'Ø­' => 'ح', 'Ø®' => 'خ', 'Ø¯' => 'د', 'Ø°' => 'ذ',
		'Ø±' => 'ر', 'Ø²' => 'ز', 'Ú˜' => 'ژ', 'Ø³' => 'س', 'Ø´' => 'ش', 'Øµ' => 'ص', 'Ø¶' => 'ض', 'Ø·' => 'ط', 'Ø¸' => 'ظ',
		'Ø¹' => 'ع', 'Øº' => 'غ', 'Ù' => 'ف', 'Ù‚' => 'ق', 'Ú©' => 'ک', 'Ú¯' => 'گ', 'Ù„' => 'ل', 'Ù…' => 'م', 'Ù†' => 'ن',
		'Ùˆ' => 'و', 'Ù‡' => 'ه', 'ÛŒ' => 'ی', 'ÙŠ' => 'ي', 'Û€' => 'ۀ', 'Ø©' => 'ة', 'ÙŽ' => 'َ', 'Ù' => 'ُ', 'Ù' => 'ِ',
		'Ù‘' => 'ّ', 'Ù‹' => 'ً', 'ÙŒ' => 'ٌ', 'Ù' => 'ٍ', 'ØŒ' => '،', 'Ø›' => '؛', ',' => ',', 'ØŸ' => '؟'
	);

	foreach($alphabet as $western => $fa)
		$str = str_replace($western, $fa, $str);

	return $str;
}
        // get all pages
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_zarinpalzg_gateway($methods) {
        $methods[] = 'WC_Zarinpalwg_Pay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_zarinpalzg_gateway' );
}

?>
