<?php
/*
 * Plugin Name: Cart Limit By Product Condition
 * Plugin URI:  
 * Description: 設定商品合併限制
 * Text Domain: wcl-plugin
 * Version:     1.0
 * Author:      nomad ju
 * Author URI:  
 * License:     GPL2
 * License URI: 
 */

defined( 'ABSPATH' ) || exit;

if(! class_exists('cart_limit_by_produt_condition')) {
    class cart_limit_by_produt_condition {
        public function __construct() {
            
            //Create the custom tab
            add_filter('woocommerce_product_data_tabs', array($this, 'create_cart_limit_tab'));
            //Add the custom fields
            add_action('woocommerce_product_data_panels', array($this, 'display_cart_limit_fields'));
            //Save tje custom fields
            add_action('woocommerce_process_product_meta', array($this, 'save_fields'));

            //-----------------------------------------
		    add_action( 'woocommerce_check_cart_items', array($this,'check_cart_limit'),15,0 );

        }

        public function create_cart_limit_tab($tabs) {
            $tabs['clbpc'] = array(
                'label'     => __('合并订购', 'clbpc'), // tab name
                'target'    => 'clbpc_panel',// anchor link,
                'class'     => array('show_if_simple', 'show_if_variable'),
                'priority'  => 80,
            );
            return $tabs;
        }

        public function display_cart_limit_fields() { 
            $product = wc_get_product();
            ?>
                <div id = 'clbpc_panel' class = 'panel woocommerce_options_panel'>
                    <div class="options_group">
                        <?php
                        woocommerce_wp_checkbox(
                            array(
                                'id'        => 'include_clbpc_option',
                                'label'     => __('开启限制', 'clbpc'),
                            )
                        );
                        ?>
                        <hr>
                        <?php
                        woocommerce_wp_checkbox(
                            array(
                                'id'        => 'clbpc_include_solo_option',
                                'label'     => __('不可单独下单', 'clbpc'),
                            )
                        );
                        woocommerce_wp_text_input(
                            array(
                                'id'        => 'clbpc_condition_cats',
                                'label'     => __('合并的必要分类', 'clbpc'),
                                'description'  => __('使用英文半角逗号隔开分类，空白则是不可与任何分类合并', 'clbpc')
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php 
        }

        public function save_fields($post_id) {
            $product = wc_get_product($post_id);

            $include_clbpc_option = isset($_POST['include_clbpc_option']) ? 'yes' : 'no';
            $product -> update_meta_data('include_clbpc_option', sanitize_text_field($include_clbpc_option));

            $clbpc_include_solo_option = isset($_POST['clbpc_include_solo_option']) ? 'yes' : 'no';
            $product -> update_meta_data('clbpc_include_solo_option', sanitize_text_field($clbpc_include_solo_option));

            $clbpc_condition_cats = isset($_POST['clbpc_condition_cats']) ? $_POST['clbpc_condition_cats'] : '';
            $product -> update_meta_data('clbpc_condition_cats', sanitize_text_field($clbpc_condition_cats));

            $product -> save();
        }

        // ------------------------------------------------------------------------------------------
        public function check_cart_limit() {
		    $is_violation = 0;
            $cart_items = WC()->cart->get_cart();

            foreach( $cart_items as $cart_item ){
                $product = wc_get_product($cart_item['product_id']);
                $is_limit = wc_string_to_bool($product->get_meta('include_clbpc_option'));
                $solo_limit = wc_string_to_bool($product->get_meta('clbpc_include_solo_option'));
                $tag_data = $product->get_meta('clbpc_condition_cats');
                $condition_cats = explode(',', $tag_data);
                $condition_cats = array_filter($condition_cats);
                if ($is_limit && count($cart_items) > 1) {
                    if (!self::is_other_item_fix_limit($condition_cats, $cart_items, $cart_item)) $is_violation = 1;
                }elseif ($is_limit && $solo_limit) {
                    $is_violation = 1;
                }
            }

		    if ($is_violation) {
		        wc_add_notice(__('不符合商品合并规范，请检查并删除不可合并的商品', 'clbpc'), 'error');
            }
        }

        private function is_other_item_fix_limit($condition_cats, $cart_items, $cart_item) {
            if (!empty($condition_cats)) {
                foreach( $cart_items as $other_cart_item ){
                    if($other_cart_item['product_id'] != $cart_item['product_id']) {
                        if (self::is_fix_cat($other_cart_item, $condition_cats)) return 0;
                    }
                }
                return 1;
            }
            return 0;
        }

        private function is_fix_cat($cart_item, $condition_cats) {
            foreach( $condition_cats as $cat ){
                if (!has_term( $cat, 'product_cat', $cart_item['product_id'] ) ) {
                    return 1;
                }
            }
            return 0;
        }
    }

}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	$cart_limit_by_produt_condition = new cart_limit_by_produt_condition(); 	
}
