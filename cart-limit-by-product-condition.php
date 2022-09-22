<?php
/*
 * Plugin Name: Cart Limit By Product Condition for Woocommerce
 * Plugin URI:  https://wordpress.org/plugins/cart-limit-by-product-condition-for-woocommerce/
 * Description: Set limits for product consolidation.
 * Version:     1.0.0
 * Author:      LibbyWu
 * Author URI:  https://profiles.wordpress.org/nomadbd/
 */

defined( 'ABSPATH' ) || exit;

if(! class_exists('cart_limit_by_produt_condition')) {

    class cart_limit_by_produt_condition {
        
        public function __construct() {
            add_filter('woocommerce_product_data_tabs', array($this, 'create_cart_limit_tab'));
            add_action('woocommerce_product_data_panels', array($this, 'display_cart_limit_fields'));
            add_action('woocommerce_process_product_meta', array($this, 'save_fields'));
            add_action('woocommerce_product_after_variable_attributes', array($this, 'variation_settings_fields'), 10, 3 );
            
		    add_action( 'woocommerce_check_cart_items', array($this,'check_cart_limit'),15,0 );
		    add_action( 'woocommerce_save_product_variation', array($this,'save_variation_settings_fields'),10, 2 );
        }

        /**
         * Add new tab for clbpc.
         *
         * @version 1.0.0
         * @since   1.0.0
         */
        public function create_cart_limit_tab($tabs) {
            $tabs['clbpc'] = array(
                'label'     => __('Product Consolidation', 'clbpc'), // tab name
                'target'    => 'clbpc_panel',// anchor link,
                'class'     => array('show_if_simple', 'show_if_variable'),
                'priority'  => 80,
            );
            return $tabs;
        }

        /**
         * display fields.
         *
         * @version 1.0.0
         * @since   1.0.0
         * @todo hide/show options
         */
        public function display_cart_limit_fields() { 
            ?>
                <div id = 'clbpc_panel' class = 'panel woocommerce_options_panel'>
                    <div class="options_group">
                        <?php
                        woocommerce_wp_checkbox(
                            array(
                                'id'        => 'include_clbpc_option',
                                'label'     => __('enable product limit', 'clbpc'),
                                'desc_tip'    => 'true',
                                'description'  => __('Limit between other products.', 'clbpc')
                            )
                        );
                        woocommerce_wp_checkbox(
                            array(
                                'id'        => 'clbpc_include_solo_option',
                                'label'     => __('is add-on product', 'clbpc'),
                                'desc_tip'    => 'true',
                                'description'  => __('Cannot place an order separately, must combine "non" add-on products to place an order.', 'clbpc')
                            )
                        );
                        woocommerce_wp_text_input(
                            array(
                                'id'        => 'clbpc_condition_cats',
                                'label'     => __('allowed categories', 'clbpc'),
                                'desc_tip'    => 'true',
                                'description'  => __('Use "," to separate categories, blanks cannot be combined with any product', 'clbpc')
                            )
                        );
                        ?>
                        <hr/>
                        <?php
                        woocommerce_wp_checkbox(
                            array(
                                'id'        => 'clbpc_include_varitaion_limit',
                                'label'     => __('enable variation limit', 'clbpc'),
                                'desc_tip'    => 'true',
                                'description'  => __('To activate order merging restrictions between variable products, please go to the variable block to set the group ID', 'clbpc')
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php 
        }

        /**
         * save fields data.
         *
         * @version 1.0.0
         * @since   1.0.0
         */
        public function save_fields($post_id) {
            $product = wc_get_product($post_id);

            $include_clbpc_option = isset($_POST['include_clbpc_option']) ? 'yes' : 'no';
            $product -> update_meta_data('include_clbpc_option', sanitize_text_field($include_clbpc_option));

            $clbpc_include_solo_option = isset($_POST['clbpc_include_solo_option']) ? 'yes' : 'no';
            $product -> update_meta_data('clbpc_include_solo_option', sanitize_text_field($clbpc_include_solo_option));

            $clbpc_include_varitaion_limit = isset($_POST['clbpc_include_varitaion_limit']) ? 'yes' : 'no';
            $product -> update_meta_data('clbpc_include_varitaion_limit', sanitize_text_field($clbpc_include_varitaion_limit));

            $clbpc_condition_cats = isset($_POST['clbpc_condition_cats']) ? $_POST['clbpc_condition_cats'] : '';
            $product -> update_meta_data('clbpc_condition_cats', sanitize_text_field($clbpc_condition_cats));

            $product -> save();
        }

        // ------------------------------------------------------------------------------------------
        /**
         * set variable group id.
         *
         * @version 1.0.0
         * @since   1.0.0
         * @todo show/hide with "clbpc_include_varitaion_limit"
         */
        public function variation_settings_fields( $loop, $variation_data, $variation ) {
            // Number Field
            woocommerce_wp_text_input( 
                array( 
                'id'          => 'clbpc_variation_group_id['. $loop .']', 
                'label'       => __( 'variable order limit id', 'clbpc' ), 
                'desc_tip'    => true,
                'wrapper_class' => 'form-row form-row-last',
                'description' => __( 'Orders with the same group number can be combined in one order', 'clbpc' ),
                'type'        => 'number', 
                'value'       => get_post_meta($variation->ID, 'clbpc_variation_group_id', true),
                'custom_attributes' => array(
                        'step'  => '1',
                        'min' => '0'
                        ) 
                )
            );
            
        }

        /**
         * save variable group id data.
         *
         * @version 1.0.0
         * @since   1.0.0
         */
        public function save_variation_settings_fields($variation_id, $i) {
            $number_field = $_POST['clbpc_variation_group_id'][$i];
            update_post_meta( $variation_id, 'clbpc_variation_group_id', esc_attr( $number_field ) );
        }

        // ------------------------------------------------------------------------------------------
        /**
         * check limit and show error notice.
         *
         * @version 1.0.0
         * @since   1.0.0
         */
        public function check_cart_limit() {
		    $is_violation = 0;
            $cart_items = WC()->cart->get_cart();

            foreach( $cart_items as $cart_item ){
                $product = wc_get_product($cart_item['product_id']);
                $is_limit = wc_string_to_bool($product->get_meta('include_clbpc_option'));
                $solo_limit = wc_string_to_bool($product->get_meta('clbpc_include_solo_option'));
                $varitation_limit = wc_string_to_bool($product->get_meta('clbpc_include_varitaion_limit'));
                $tag_data = $product->get_meta('clbpc_condition_cats');
                $condition_cats = explode(',', $tag_data);
                $condition_cats = array_filter($condition_cats);
                if ($is_limit && count($cart_items) > 1) {
                    if (!self::is_other_item_fix_limit($condition_cats, $cart_items, $cart_item, $solo_limit)) $is_violation = 1;
                }elseif ($is_limit && $solo_limit) {
                    $is_violation = 1;
                }
                if ($varitation_limit) {
                    if (!self::is_same_item_fix_limit($cart_items, $cart_item)) $is_violation = 1;
                }
            }
		    if ($is_violation) {
		        wc_add_notice(__('Does not meet product combination specifications, please check and delete non-mergeable products', 'clbpc'), 'error');
            }
        }

        private function is_other_item_fix_limit($condition_cats, $cart_items, $cart_item, $solo_limit) {
            $is_pass = 0;
            foreach( $cart_items as $other_cart_item ){
                if($other_cart_item['product_id'] != $cart_item['product_id']) {
                    if (empty($condition_cats)) return 0;
                    if (self::is_fix_cat($other_cart_item, $condition_cats)) return 0;
                    if (!self::is_solo_limit_product($other_cart_item['product_id'])) $is_pass = 1;
                }
            }
            if ($solo_limit && !$is_pass) return 0;
            return 1;
        }

        private function is_same_item_fix_limit($cart_items, $cart_item) {
            $product = wc_get_product($cart_item['product_id']);
            if($product->get_type() == "variable" && !empty($product->get_available_variations())) {
                foreach( $cart_items as $other_cart_item ){
                    if($other_cart_item['product_id'] == $cart_item['product_id']) {
                        if (!self::is_fix_varitation_id($cart_item, $other_cart_item)) return 0;
                    }
                }
            }
            return 1;
        }

        private function is_fix_cat($cart_item, $condition_cats) {
            foreach( $condition_cats as $cat ){
                if (!has_term( $cat, 'product_cat', $cart_item['product_id'] ) ) {
                    return 1;
                }
            }
            return 0;
        }

        private function is_solo_limit_product($product_id) {
            $product = wc_get_product($product_id);
            return wc_string_to_bool($product->get_meta('clbpc_include_solo_option'));
        }

        private function is_fix_varitation_id($cart_item, $other_cart_item) {
            $my_limit_id = get_post_meta($cart_item['variation_id'], 'clbpc_variation_group_id', true);
            $other_limit_id = get_post_meta($other_cart_item['variation_id'], 'clbpc_variation_group_id', true);
            return $my_limit_id == $other_limit_id;
        }
    }

}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	$cart_limit_by_produt_condition = new cart_limit_by_produt_condition(); 	
}


