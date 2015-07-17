<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Plugin Name: Woo Download Credits
 * Plugin URI: http://muth.co/
 * Version: 1.0
 * Description: Lets your customers buy "download credits" in bulk, then use those credits for downloadable/virtual products in your Woo Commerce shop.
 * Author: http://muth.co/
 * Author URI: http://muth.co/
*/


if ( ! class_exists( 'WC_Dependencies' ) ){
    require_once( dirname(__FILE__) . '/includes/class-wc-dependencies.php');
}


if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		return WC_Dependencies::woocommerce_active_check();
	}
}

if ( is_woocommerce_active() ) {
    
     global $woocommerce;
    

    
    if ( ! class_exists( 'WC_Product' ) ){
      require_once( WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-product.php');
    }
    
        if( class_exists('WC_Product') ){
            class Woo_Download_Credits_Product_Credits extends WC_Product {

                public $virtual = 'yes';	
                public $downloadable = 'yes';
                public $sold_individually = 'yes';

                public function __construct( $product ) {
                    parent::__construct( $product );
                    $this->product_type = 'credits';
                    $this->tax_status   = '';
                }

                public function exists() {
                    return true;
                }

                public function is_purchasable() {
                    return true;
                }

                public function get_title() {
                    return __( 'Buy Download Credits', 'woo-credits' );
                }

                public function get_tax_status() {
                    return '';
                }

                public function is_visible() {
                    return false;
                }
            } 
        }
    
    if ( ! class_exists( 'WC_Payment_Gateway' ) ){
         require_once( WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-settings-api.php');
         require_once( WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php');         
    }
    
    
        if( class_exists('WC_Payment_Gateway') ):
            class Woo_Download_Credits_Gateway extends WC_Payment_Gateway {


                public function __construct() {
                    $this->id                 = 'woo_credits';
                    $this->icon               = apply_filters( 'woocommerce_cod_icon', '' );
                    $this->method_title       = __( 'Download Credits', 'woocommerce' );
                    $this->method_description = __( 'Have your customers pay with their Download Credits balance.', 'woo-credits' );
                    $this->has_fields         = false;

                    $this->init_form_fields();
                    $this->init_settings();

                    $this->title              = $this->get_option( 'title' );
                    $this->description        = $this->get_option( 'description' );

                }    



                  public function init_form_fields() {

                    $this->form_fields = array(
                            'enabled' => array(
                                'title'       => __( 'Enable Download Credits', 'woo-credits' ),
                                'label'       => __( 'Enable Download Credits', 'woo-credits' ),
                                'type'        => 'checkbox',
                                'description' => '',
                                'default'     => 'yes'
                            ),
                            'title' => array(
                                'title'       => __( 'Title', 'woo-credits' ),
                                'type'        => 'text',
                                'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-credits' ),
                                'default'     => __( 'Download Credits', 'woo-credits' ),
                                'desc_tip'    => true,
                            ),
                            'description' => array(
                                'title'       => __( 'Description', 'woo-credits' ),
                                'type'        => 'textarea',
                                'description' => __( 'Payment method description that the customer will see on your website.', 'woo-credits' ),
                                'default'     => __( 'Pay using your Download Credits.', 'woo-credits' ),
                                'desc_tip'    => true,
                            ),
                       );
                   }


                public function process_payment( $order_id ) 
                {


                    if ( Woo_Download_Credits::cart_contains_credits() ) {
                        wc_add_notice( __('<strong>Payment error:</strong>', 'woo-credits') . ' You can not purchase  Download Credits with Download Credits. Please choose another payment method.                   IMPORTANT: To successfully purchase Download Credits, please make sure there are <a href="'.get_permalink(wc_get_page_id( 'cart' )).'" style="text-decoration: underline;">no other products</a> in your cart upon checkout
                    ', 'error' );
                        return;			
                    }


                    $order = wc_get_order( $order_id );
                    $user_id = $order->user_id;

                    $items          = $order->get_items();

                     $total_credits_amount = 0;

                    foreach ( $items as $item ) {
                        $product = $order->get_product_from_item( $item );	
                          $credits_amount = get_post_meta( $product->id, '_credits_amount', true );
                        if($credits_amount){
                            $total_credits_amount += $credits_amount;
                        }else{
                            wc_add_notice( __('<strong>Payment error:</strong>', 'woo-credits') . ' You can not purchase product without credits set. Please choose another payment method.', 'error' );
                            return;
                        }
                    }		


                    $download_credits = floatval(get_user_meta($user_id, "_download_credits", true));
                    $cart_total = floatval(WC()->cart->total);


                    if ($total_credits_amount > $download_credits)
                    {
                        wc_add_notice( __('<strong>Payment error:</strong>', 'woo-credits') . ' Insufficient Credits. Please purchase more credits or use a different payment method.', 'error' );
                        return;
                    }


                    $new_user_download_credits = $download_credits-$total_credits_amount;
                    update_user_meta( $user_id, '_download_credits', $new_user_download_credits );


                    if (get_user_meta($user_id,'_download_credits', true) != $new_user_download_credits)
                    {
                        wc_add_notice( __('<strong>System error:</strong>', 'woo-credits') . ' There was an error procesing the payment. Please try another payment method.', 'error' );
                        return;
                    }

                    $order->update_status( 'completed', __( 'Payment completed use Download Credits', 'woo-credits' ) );


                    $order->reduce_order_stock();


                    WC()->cart->empty_cart();


                    return array(
                        'result' 	=> 'success',
                        'redirect'	=> $this->get_return_url( $order )
                    );
                }


                public function get_icon() 
                {
                    $link = null;
                    global $woocommerce;
                    $download_credits = get_user_meta(get_current_user_id(), '_download_credits', true) ;
                    return apply_filters( 'woocommerce_gateway_icon', ' | Your Current Balance: <strong>'.$download_credits.' </strong> | <a class="buy-more-credits" href="'.get_permalink(wc_get_page_id( 'myaccount' )).'">Buy More</a> Credits', $this->id );
                }

            }
        endif;    


    class Woo_Download_Credits
    {
        public static $_instance = null;

        public function __construct ()
        {

            add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'register_styles'));


            add_action( 'wp', array( $this, 'credits_buy_form_handler' ) );
            add_action( 'woocommerce_before_my_account', array( $this, 'before_my_account' ) );
            add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );

            add_filter ('woocommerce_add_cart_item_data', array( $this,'add_cart_item_data'));     


            add_action( 'woocommerce_product_options_general_product_data', array( $this,'add_custom_general_fields') );
            add_action( 'woocommerce_process_product_meta', array( $this,'add_custom_general_fields_save') ); 
            add_action( 'wp_insert_post', array( $this,'product_custom_meta_data_save') );
            add_action( 'save_post', array( $this,'product_custom_meta_data_save') );    

            add_filter( 'woocommerce_product_class', array( $this, 'woocommerce_product_class_for_credits' ), 10, 4 );
            add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );   

            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'woocommerce_checkout_update_order_meta' ), 10, 2 );
            add_action( 'woocommerce_payment_complete', array( $this, 'woocommerce_payment_complete' ) );
            add_action( 'woocommerce_order_status_completed', array( $this, 'order_status_completed_remove_credits' ) );
            add_action( 'woocommerce_order_status_completed', array( $this, 'order_status_completed_add_credits' ) );      
            
            add_filter( 'woocommerce_payment_gateways', array( $this,  'woo_credits_init_gateway' ));              
            add_action('woocommerce_single_product_summary', array($this, 'single_product_summary'),31);            
            add_filter( 'woocommerce_get_price_html', array($this,'get_price_html'), 100, 2 );            
            add_filter('woocommerce_cart_total',array($this,'cart_total'),10,1);            
            add_filter('woocommerce_cart_subtotal',array($this,'cart_subtotal'),10,3);            
            add_filter('woocommerce_cart_item_quantity',array($this,'cart_item_quantity'),10,2);
            add_filter('woocommerce_checkout_cart_item_quantity',array($this,'checkout_cart_item_quantity'),10,3);
            add_filter('woocommerce_cart_item_subtotal',array($this,'cart_item_subtotal'),10,3);
            add_filter('woocommerce_get_formatted_order_total',array($this,'get_formatted_order_total'),10,2);
            add_action('woocommerce_thankyou_woo_credits',array($this,'thankyou_woo_credits'),10,1);
            add_filter('woocommerce_order_formatted_line_subtotal',array($this,'order_formatted_line_subtotal'),10,3);
            add_filter('woocommerce_order_subtotal_to_display',array($this,'order_subtotal_to_display'),10,3);
           add_filter('woocommerce_get_item_count',array($this,'get_item_count'),10,3);
            
             add_filter('woocommerce_order_item_quantity_html',array($this,'order_item_quantity_html'),10,2);

        }
        
        public function thankyou_woo_credits($order_id){
            if ( self::order_using_credits($order_id) ) {
            
              $order = wc_get_order($order_id);    
              $user_id =  $order->get_user_id();
              $credits = floatval(get_user_meta($user_id, "_download_credits", true));
                ?>
                    <ul class="order_details credits_remaining">                
                        <li class="method">
                            <?php _e( 'Credits Remaining:', 'woo-credits' ); ?>
                            <strong><?php echo $credits; ?></strong>
                        </li>                
                    </ul>
               <?php
            }
        }
        
        
        public function order_item_quantity_html($product_quantity, $item){     
            
            $product_id = $item['product_id'];
            $product = wc_get_product( $product_id );
            
               if ( $product->is_type( 'credits' ) ) {
                   $options1 = get_option( 'mwdc_options' );
                   $credit_number = wc_clean( $options1['credit_number'] );
                   $product_quantity = ' <strong class="product-quantity"> x ' . $credit_number. ' credits' . '</strong>' ;
               }
            return $product_quantity;
        }        
        
        public function get_item_count($count, $type, $order){        
                if ( self::order_contains_credits($order->id) ) {
                      $options1 = get_option( 'mwdc_options' );
                      $credit_number = wc_clean( $options1['credit_number'] );
                      $credit_number = $credit_number*$count;
                    $count = '&nbsp; '.$credit_number.' credit ';
                }
            return $count;
        }        
        
        
        public function order_subtotal_to_display($subtotal, $compound, $order){        
                if ( self::order_using_credits($order->id) ) {
                    $credits_used = self::order_get_total_used_credits($order->id);
                    $subtotal .= '&nbsp;&nbsp;&nbsp; (or '.$credits_used.' credits) ';
                }
            return $subtotal;
        }          
        
        public function get_formatted_order_total($formatted_total, $order){        
                if ( self::order_using_credits($order->id) ) {
                    $credits_used = self::order_get_total_used_credits($order->id);
                    $formatted_total .= '&nbsp;&nbsp;&nbsp; (or '.$credits_used.' credits) ';
                }
            return $formatted_total;
        }  
        
        public function order_formatted_line_subtotal($subtotal, $item, $order){        
            $prod_id = ( isset( $item['variation_id'] ) && $item['variation_id'] != 0 ) ? $item['variation_id'] : $item['product_id'];            
            $credits_amount = get_post_meta( $prod_id, '_credits_amount', true );            
            if($credits_amount){
                $subtotal .= ' &nbsp;&nbsp;&nbsp;(or '.$credits_amount.' credits)';
            }
             
            return $subtotal;
        }         
        
        
        
        public function cart_item_subtotal($sub_total, $cart_item, $cart_item_key){        
            $prod_id = ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] != 0 ) ? $cart_item['variation_id'] : $cart_item['product_id'];            
            $credits_amount = get_post_meta( $prod_id, '_credits_amount', true );            
            if($credits_amount){
                $sub_total .= ' &nbsp;&nbsp;&nbsp;(or '.$credits_amount.' credits)';
            }
             
            return $sub_total;
        }     
        
        public function checkout_cart_item_quantity($product_quantity, $cart_item, $cart_item_key){        
            $cart_item = WC()->cart->cart_contents[$cart_item_key];
               if ( $cart_item['data']->is_type( 'credits' ) ) {
                   $options1 = get_option( 'mwdc_options' );
                   $credit_number = wc_clean( $options1['credit_number'] );
                   $product_quantity = ' <strong class="product-quantity"> x ' . $credit_number. ' credits' . '</strong>' ;
               }
            return $product_quantity;
        }
             
                  
        
        
        public function cart_item_quantity($product_quantity, $cart_item_key){        
            $cart_item = WC()->cart->cart_contents[$cart_item_key];
               if ( $cart_item['data']->is_type( 'credits' ) ) {
                   $options1 = get_option( 'mwdc_options' );
                   $credit_number = wc_clean( $options1['credit_number'] );
                   $product_quantity = $credit_number. ' credits';
               }
             
            return $product_quantity;
        }
        
        public function get_price_html($price, $product){
            
            $credits_amount = get_post_meta( $product->id, '_credits_amount', true );            
            if($credits_amount){
                return str_replace( '</span>', ' &nbsp;&nbsp;&nbsp; (or '.$credits_amount.' credits)</span>', $price );
            }    
             
        }
        
        public function cart_subtotal($cart_subtotal, $compound, $obj){   
            
               $total_credits_amount = 0; 
            
                foreach ( WC()->cart->get_cart() as  $item ) {	
                    
      
                    if ( !$item['data']->is_type( 'credits' ) ) {
                            $prod_id = ( isset( $item['variation_id'] ) && $item['variation_id'] != 0 ) ? $item['variation_id'] : $item['product_id'];
                            $credits_amount = get_post_meta( $prod_id, '_credits_amount', true );
                            $total_credits_amount += $credits_amount;
                    }
                }            
            
            if($total_credits_amount > 0 ){
                $cart_subtotal .= '&nbsp;&nbsp;&nbsp; (or '.$total_credits_amount.' credits)';                
            }   
            
            
            return $cart_subtotal;               
        }         
        
        
        public function cart_total($total){   
               $total_credits_amount = 0; 
            
                foreach ( WC()->cart->get_cart() as $item ) {	
                    if ( !$item['data']->is_type( 'credits' ) ) {
                            $prod_id = ( isset( $item['variation_id'] ) && $item['variation_id'] != 0 ) ? $item['variation_id'] : $item['product_id'];
                            $credits_amount = get_post_meta( $prod_id, '_credits_amount', true );
                            $total_credits_amount += $credits_amount;
                    }
                }            
            
            if($total_credits_amount > 0 ){
                $total .= '&nbsp;&nbsp;&nbsp; (or '.$total_credits_amount.' credits)';                
            }           
            return $total;               
        }        
        
        public function single_product_summary(){            
            global $product;            
            $credits_amount = get_post_meta( $product->id, '_credits_amount', true );            
            if($credits_amount){
                
                if(is_user_logged_in()){
                    $user_id = get_current_user_id();
                    $credits = floatval(get_user_meta($user_id, "_download_credits", true));
                    echo '<p class="download-credits"> You have '.$credits.' credits remaining <br/><a class="buy-more-credits" href="'.get_permalink(wc_get_page_id( 'myaccount' )).'">Buy More</a> Credits</p>';
                }else{
                     echo '<p class="download-credits"><a class="buy-more-credits" href="'.get_permalink(wc_get_page_id( 'myaccount' )).'">Buy More</a> Credits</p>';
                }
                
                
            }            
        }
        
        public function woo_credits_init_gateway ( $methods ) 
        {
            $methods[] = 'Woo_Download_Credits_Gateway'; 
            return $methods;
        }        

        public static function instance ()
        {
            if ( is_null( self::$_instance ) ) 
                self::$_instance = new self();

            return self::$_instance;
        }

        public function register_styles ()
        {
            wp_register_style('mwdc_admin', plugins_url( '/assets/css/admin.css', __FILE__ )  );
        }

        public function register_scripts ()
        {
            wp_register_script('mwdc_admin', plugins_url( '/assets/js/admin.js', __FILE__ ) );
        }


        public static function get_account_credits( $user_id = null, $formatted = true, $exclude_order_id = 0 ) {
            $user_id = $user_id ? $user_id : get_current_user_id();

            if ( $user_id ) {				
                $credits = floatval(get_user_meta($user_id, "_download_credits", true));				


                $orders_with_pending_credits = get_posts( array(
                    'numberposts' => -1,
                    'post_type'   => 'shop_order',
                    'post_status' => array_keys( wc_get_order_statuses() ),
                    'fields'      => 'ids',
                    'meta_query'  => array(
                        array(
                            'key'   => '_customer_user',
                            'value' => $user_id
                        ),
                        array(
                            'key'   => '_credits_removed',
                            'value' => '0',
                        ),
                        array(
                            'key'     => '_credits_used',
                            'value'   => '0',
                            'compare' => '>'
                        )
                    )
                ) );

                foreach ( $orders_with_pending_credits as $order_id ) {
                    if ( null !== WC()->session && ! empty( WC()->session->order_awaiting_payment ) && $order_id == WC()->session->order_awaiting_payment ) {
                        continue;
                    }
                    if ( $exclude_order_id === $order_id ) {
                        continue;
                    }
                    $credits = $credits - floatval( get_post_meta( $order_id, '_credits_used', true ) );
                }
            } else {
                $credits = 0;
            }

            return $formatted ? wc_price( $credits ) : $credits;
        }


        public static function add_credits( $customer_id, $amount ) {
            $credits = floatval(get_user_meta($customer_id, "_download_credits", true));
            $credits = $credits ? $credits : 0;
            $credits += floatval( $amount );
            update_user_meta( $customer_id, '_download_credits', $credits );
        }


        public static function remove_credits( $customer_id, $amount ) {
            $credits = floatval(get_user_meta($customer_id, "_download_credits", true));
            $credits = $credits ? $credits : 0;
            $credits = $credits - floatval( $amount );
            update_user_meta( $customer_id, '_download_credits', max( 0, $credits ) );
        }    



        public static function cart_contains_credits() {
            foreach ( WC()->cart->get_cart() as $item ) {	
                if ( $item['data']->is_type( 'credits' ) ) {
                    return true;
                }
            }
            return false;
        }


        public static function using_credits() {
            return ! is_null( WC()->session ) && WC()->session->get( 'use-download-credits' ) && self::can_apply_credits();
        }

        public static function can_apply_credits() {

            if ( self::cart_contains_credits() || ! is_user_logged_in() ) {
                $can_apply = false;
            }

            if ( ! self::get_account_credits( get_current_user_id(), false ) ) {
                $can_apply = false;
            }

            return $can_apply;
        }

        public static function used_credits_amount() {
            return WC()->session->get( 'used-download-credits' );
        }   

        public function add_cart_item_data($cart_item_data){		
            return $cart_item_data;
        }

        public function add_cart_item( $cart_item ) {
            if ( ! empty( $cart_item['credits_amount'] ) ) {
                $cart_item['data']->set_price( $cart_item['credits_amount'] );
            }
            return $cart_item;
        }

        public function credits_buy_form_handler() {
            if ( isset( $_POST['wdc_download_credits_buy'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'download-credits-buy' ) ) {			
                $options1 = get_option( 'mwdc_options' );
                $credits_amount = wc_clean( $options1['credit_price'] );
                WC()->cart->add_to_cart( wc_get_page_id( 'myaccount' ), true, '', '', array( 'credits_amount' => $credits_amount ) );
                wp_redirect( get_permalink( wc_get_page_id( 'cart' ) ) );
                exit;
            }
        }

        public function before_my_account() {
            $user_id =  get_current_user_id();
            $credits = floatval(get_user_meta($user_id, "_download_credits", true));
            echo '<h2>'. __( 'Download Credits', 'woo-credits' ) .'</h2>';
            echo '<p>'. sprintf( __( 'You have <strong>%s</strong> Download Credits.', 'woo-credits' ), $credits ) . '</p>';
            $this->download_credits_buy_form();
        }


        private function download_credits_buy_form() {
            ?>
            <form method="post">
                <h3><label for="credits_amount"><?php _e( 'Buy Download Credits', 'woo-credits' ); ?></label></h3>
                <p class="form-row">
                    <input type="hidden" name="wdc_download_credits_buy" value="true" />
                    <input style="float: left;" id="credit-buy-now" type="submit" class="button" value="<?php _e( 'Buy Now', 'woo-credits' ); ?>" />
                    <span class="imp-msg" style="display: inline-block; float: left; margin: -4px 0 0; padding: 0 0 0 10px;">
                      IMPORTANT: To successfully purchase Download Credits,<br/>
                        please make sure there are <a href="<?php echo get_permalink(wc_get_page_id( 'cart' )); ?>" style="text-decoration: underline;">no other products</a> in your cart upon checkout
                    </span>                    
                </p>

                <?php wp_nonce_field( 'download-credits-buy' ); ?>
            </form>
            <?php
        }    


        public function add_custom_general_fields() 
        {
            global $woocommerce, $post;

                echo '<div class="options_group">';
                woocommerce_wp_text_input(
                    array(
                        'id'          => '_credits_amount',
                        'label'       => __( 'Credit Required For Download ', 'woo-credits' ),
                        'placeholder' => '',
                        'desc_tip'    => 'true',
                        'description' => __( 'The credits for this product to download.', 'woo-credits' ),
                        'type'              => 'text',

                    )
                );
                echo '</div>';

        }


        public function product_custom_meta_data_save($post_id){

            if ( 'product' != $_POST['post_type'] ){ return; }

            $woocommerce_credits_amount = $_POST['_credits_amount'];
            if(!empty( $woocommerce_credits_amount ) ){
                update_post_meta( $post_id, '_credits_amount', esc_attr( $woocommerce_credits_amount ) );
                update_post_meta ( $post_id, '_downloadable', "yes" );
                update_post_meta ( $post_id, '_virtual', "yes" );
                update_post_meta ( $post_id, '_sold_individually', "yes" );
            }else{
                delete_post_meta( $post_id, '_credits_amount');
            }

        }

        public function add_custom_general_fields_save ( $post_id )
        {
            $woocommerce_credits_amount = $_POST['_credits_amount'];
            if(!empty( $woocommerce_credits_amount ) ){
                update_post_meta( $post_id, '_credits_amount', esc_attr( $woocommerce_credits_amount ) );
            }else{
                delete_post_meta( $post_id, '_credits_amount');
            }
        }    

        public function woocommerce_product_class_for_credits( $classname, $product_type, $post_type, $product_id ) {
            if ( wc_get_page_id( 'myaccount' ) === $product_id ) {
                return 'Woo_Download_Credits_Product_Credits';
              }
            return $classname;
        }


        public function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
            if ( ! empty( $values['credits_amount'] ) ) {
                $cart_item['credits_amount'] = $values['credits_amount'];
                $cart_item                  = $this->add_cart_item( $cart_item );
            }
            return $cart_item;
        }    



        public function woocommerce_payment_complete( $order_id ) {
            if ( null !== WC()->session ) {
                WC()->session->set( 'use-download-credits', false );
                WC()->session->set( 'used-download-credits', false );
            }

            $order       = wc_get_order( $order_id );
            $customer_id = $order->get_user_id();

            if ( $customer_id && ! get_post_meta( $order_id, '_credits_removed', true ) ) {
                if ( $credits = get_post_meta( $order_id, '_credits_used', true ) ) {
                    self::remove_credits( $customer_id, $credits );
                    $order->add_order_note( sprintf( __( 'Removed %s credits from user #%d', 'woo-credits' ), wc_price( $credits ), $customer_id ) );
                }
                update_post_meta( $order_id, '_credits_removed', 1 );
            }
        }	


        public function order_status_completed_remove_credits( $order_id ) {
            if ( null !== WC()->session ) {
                WC()->session->set( 'use-download-credits', false );
                WC()->session->set( 'used-download-credits', false );
            }

            $order       = wc_get_order( $order_id );
            $customer_id = $order->get_user_id();

            if ( $customer_id && ! get_post_meta( $order_id, '_credits_removed', true ) ) {
                if ( $credits = get_post_meta( $order_id, '_credits_used', true ) ) {
                    self::remove_credits( $customer_id, $credits );
                }
                update_post_meta( $order_id, '_credits_removed', 1 );
            }
        }


        public function woocommerce_checkout_update_order_meta( $order_id, $posted ) {
            if ( $posted['payment_method'] !== 'woo_credits' && self::using_credits() ) {
                $used_credits = self::used_credits_amount();
                update_post_meta( $order_id, '_credits_used', $used_credits );
                add_post_meta( $order_id, '_credits_removed', 0 );
            }
        }
        

        public static function order_contains_credits( $order_id ) {
            $order           = wc_get_order( $order_id );
            $credits_product = false;
            foreach ( $order->get_items() as $item ) {
                $product = $order->get_product_from_item( $item );
                if ( $product->is_type( 'credits' ) ) {
                    $credits_product = true;
                    break;
                }
            }
            return $credits_product;
        }
        
        public static function order_using_credits( $order_id ) {
            $order           = wc_get_order( $order_id );
            $credits_product = false;
            foreach ( $order->get_items() as $item ) {
                $product = $order->get_product_from_item( $item );
                if ( get_post_meta( $product->id, '_credits_amount', true ) ) {
                    $credits_product = true;
                    break;
                }
            }
            return $credits_product;
        }     
        
        public static function order_get_total_used_credits( $order_id ) {
            $order           = wc_get_order( $order_id );
            $credits_used = 0;
            foreach ( $order->get_items() as $item ) {
                $product = $order->get_product_from_item( $item );
                if ( $credits_amount = get_post_meta( $product->id, '_credits_amount', true ) ) {
                    $credits_used += $credits_amount;
                }
            }
            return $credits_used;
        }        


        public function order_status_completed_add_credits( $order_id ) {
            $order          = wc_get_order( $order_id );
            $items          = $order->get_items();
            $customer_id    = $order->get_user_id();

            if ( $customer_id && ! get_post_meta( $order_id, '_credits_added', true ) ) {
                foreach ( $items as $item ) {
                    $product = $order->get_product_from_item( $item );	

                    if ( $product &&  $product->is_type( 'credits'  ) ) {					
                        $options1 = get_option( 'mwdc_options' );					
                        $amount = $options1['credit_number'];	
                        self::add_credits( $customer_id, $amount );
                        update_post_meta( $order_id, '_credits_added', 1 );
                    }
                }
            }
        }
    }

    Woo_Download_Credits::instance();
    
    
class Woo_Download_Credits_Admin_Options {

	protected $option_name = 'mwdc_options';

	public static function init ()
	{
		add_action('admin_menu', array(new self, 'add_page'));
	}
    
	public function add_page() {
	    add_submenu_page('woocommerce', 'Download Credits Options', 'Download Credits', 'manage_options', 'mwdc_settings', array($this, 'mwdc_options_page_cb'));
	}


	public function admin_head ()
	{
		wp_enqueue_style( 'mwdc_admin' );
		wp_enqueue_script( 'mwdc_admin' );
	}


	public function mwdc_options_page_cb() {
		
		
		if ( isset( $_POST['mwdc_options_submit'] ) &&  $_POST['mwdc_options_submit']) {
			
			if(!$_POST['credit_name']){
				$credit_name = 'Download Credits';
			}else{
				$credit_name = $_POST['credit_name'];
			}			
			$credit_number = $_POST['credit_number'];
			$credit_price = $_POST['credit_price'];				
			$arr = array( 
					'credit_name' => $credit_name ,
					'credit_number' => $credit_number,
					'credit_price' => $credit_price
					
			);			
			update_option( $this->option_name,$arr );				
		}
		
	    $options1 = get_option( $this->option_name );

    	$this->admin_head();
	    ?>
	    
	    
<div class="wrap">

	<div id="icon-options-general" class="icon32"></div>
	<h2><?php esc_attr_e( 'Download Credits', 'woo-credits' ); ?></h2>

	<div id="poststuff">

		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<div class="handlediv" title="Click to toggle"><br></div>		
						<h3 class="hndle"><span><?php esc_attr_e( 'Manage Download Credits Below', 'woo-credits' ); ?></span></h3>
						<div class="inside">
							   <form method="post" action="">				
							      <div id="wo_tabs">
							         <table class="form-table">
							            <tr valign="top">
							               <td class="td td1">
							                  <label>Name <span>(if left empty, default name will be "Download Credits")</span></label>
							                  <input type="text" id="credit_name" name="credit_name" value="<?php echo $options1['credit_name']; ?>" /> 
							               </td>
							               <td class="td td2">
							                  <label>No. of Credits</label>
							                  <input type="text" id="credit_number" name="credit_number" value="<?php echo $options1['credit_number']; ?>" /> 
							               </td>
							               <td class="td td3">
							                  <label>Price</label>
							                  <input type="text" id="credit_price" name="credit_price" value="<?php echo $options1['credit_price']; ?>" /> 
							               </td>

							            </tr>
                                         <tr valign="top">
							               <td class="td td4">
							                  <p class="submit">
							                     <input type="hidden" name="mwdc_options_submit" value="true" />
							                     <input type="submit" class="button-primary" value="<?php _e('Save') ?>" />
							                  </p>
							               </td>                                             
                                             
                                             
                                         </tr>     
							         </table>
							      </div>
							   </form>
						</div>
					</div>
				</div>
			</div>

			<div id="postbox-container-1" class="postbox-container">
				<div class="meta-box-sortables">
					<div class="postbox">
						<div class="handlediv" title="Click to toggle"><br></div>
						<h3 class="hndle"><span><?php esc_attr_e('About', 'woo-credits'	); ?></span></h3>
						<div class="inside">
							 <p>Tutorial Video<br/>
								 <small>Please visit<a href="http://www.muth.co/tools/woo-download-credits" target="_blank">www.muth.co/tools/woo-download-credits</a> for screencast on how to use the Woo Download Credits plugin.</small>
							 </p>
							 <p>Upgrade<br/>
								 <small>To offer multiple/unlimited tiers of credit packages upgrade to the Professional version at <a href="http://www.muth.co/tools/woo-download-credits" target="_blank">www.muth.co/tools/woo-download-credits</a>.</small>
							 </p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<br class="clear">
	</div>


</div>
	    
	    

	    <?php
	}

}
Woo_Download_Credits_Admin_Options::init();    
    
    


}






