<?php
/**
 * WooCommerce products loader.
 * Reads live products from WooCommerce and returns them as a PHP array.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordAiChat_WooCommerce {

    /**
     * Get WooCommerce products.
     *
     * @param int $limit
     * @return array
     */
    public function get_products( $limit = 200 ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return array();
        }

        $products = wc_get_products( array(
            'limit'  => $limit,
            'status' => 'publish',
        ) );

        $result = array();
        foreach ( $products as $product ) {
            $image_url = '';
            $image_id  = $product->get_image_id();
            if ( $image_id ) {
                $image_url = wp_get_attachment_url( $image_id );
            }

            $categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
            $tags       = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );

            $result[] = array(
                'id'             => $product->get_id(),
                'name'           => $product->get_name(),
                'description'    => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
                'price'          => $product->get_price(),
                'sale_price'     => $product->get_sale_price(),
                'regular_price'  => $product->get_regular_price(),
                'url'            => get_permalink( $product->get_id() ),
                'image'          => $image_url ? $image_url : '',
                'categories'     => is_array( $categories ) ? implode( ', ', $categories ) : '',
                'tags'           => is_array( $tags ) ? implode( ', ', $tags ) : '',
                'sku'            => $product->get_sku(),
                'stock_status'   => $product->get_stock_status(),
                'stock_qty'      => $product->get_stock_quantity(),
                'on_sale'        => $product->is_on_sale(),
                'availability'   => ( 'instock' === $product->get_stock_status() ) ? 'in_stock' : 'out_of_stock',
            );
        }

        return $result;
    }

    /**
     * Get active WooCommerce coupons (mirrors PrestaShop cart_rules).
     *
     * @param int $limit
     * @return array
     */
    public function get_coupons( $limit = 50 ) {
        if ( ! class_exists( 'WC_Coupon' ) ) {
            return array();
        }

        $coupon_posts = get_posts( array(
            'post_type'   => 'shop_coupon',
            'post_status' => 'publish',
            'numberposts' => $limit,
        ) );

        $coupons = array();
        foreach ( $coupon_posts as $post ) {
            $coupon    = new WC_Coupon( $post->post_title );
            $expires   = $coupon->get_date_expires();
            $coupons[] = array(
                'code'       => $post->post_title,
                'type'       => $coupon->get_discount_type(),
                'amount'     => $coupon->get_amount(),
                'expiry'     => $expires ? $expires->date( 'Y-m-d' ) : null,
                'min_amount' => $coupon->get_minimum_amount(),
            );
        }

        return $coupons;
    }
}
