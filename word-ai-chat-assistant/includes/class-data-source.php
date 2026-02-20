<?php
/**
 * Data Source abstraction layer.
 * Returns products from either WooCommerce or XML depending on the admin setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordAiChat_DataSource {

    /** @var string 'woocommerce' | 'xml' */
    private $source;

    /** @var WordAiChat_WooCommerce */
    private $wc_loader;

    /** @var WordAiChat_XmlProducts */
    private $xml_loader;

    public function __construct() {
        $this->source     = get_option( 'word_ai_chat_data_source', 'xml' );
        $this->wc_loader  = new WordAiChat_WooCommerce();
        $this->xml_loader = new WordAiChat_XmlProducts();
    }

    /**
     * Return products array from the configured source.
     *
     * @param int $limit
     * @return array
     */
    public function get_products( $limit = 200 ) {
        if ( 'woocommerce' === $this->source && function_exists( 'wc_get_products' ) ) {
            return $this->wc_loader->get_products( $limit );
        }
        return $this->xml_loader->get_products( $limit );
    }

    /**
     * Return coupons from the configured source.
     *
     * @return array
     */
    public function get_coupons() {
        if ( 'woocommerce' === $this->source && class_exists( 'WC_Coupon' ) ) {
            return $this->wc_loader->get_coupons();
        }
        return array();
    }

    /**
     * Build the product knowledge-base string for the OpenAI system prompt.
     *
     * @param int $limit
     * @return string
     */
    public function build_products_context( $limit = 150 ) {
        $products = $this->get_products( $limit );
        if ( empty( $products ) ) {
            return '';
        }

        $lines = array();
        foreach ( $products as $p ) {
            $id          = isset( $p['id'] ) ? $p['id'] : '';
            $name        = isset( $p['name'] ) ? $p['name'] : '';
            $price       = isset( $p['price'] ) ? $p['price'] : '';
            $desc        = isset( $p['description'] ) ? wp_strip_all_tags( $p['description'] ) : '';
            $url         = isset( $p['url'] ) ? $p['url'] : '';
            $image       = isset( $p['image'] ) ? $p['image'] : '';
            $category    = isset( $p['categories'] ) ? $p['categories'] : ( isset( $p['category'] ) ? $p['category'] : '' );
            $stock       = isset( $p['stock_status'] ) ? $p['stock_status'] : ( isset( $p['availability'] ) ? $p['availability'] : '' );
            $sale_price  = isset( $p['sale_price'] ) ? $p['sale_price'] : '';

            $line = "ID:{$id}|NAME:{$name}|PRICE:{$price}";
            if ( $sale_price && $sale_price !== $price ) {
                $line .= "|SALE:{$sale_price}";
            }
            if ( $category ) {
                $line .= "|CAT:{$category}";
            }
            if ( $stock ) {
                $line .= "|STOCK:{$stock}";
            }
            if ( $desc ) {
                $short_desc = mb_substr( strip_tags( $desc ), 0, 120 );
                $line .= "|DESC:{$short_desc}";
            }
            if ( $url ) {
                $line .= "|URL:{$url}";
            }
            if ( $image ) {
                $line .= "|IMG:{$image}";
            }
            $lines[] = $line;
        }

        return "=== PRODUCTS ===\n" . implode( "\n", $lines ) . "\n=== END PRODUCTS ===";
    }

    /**
     * Build coupons context string.
     *
     * @return string
     */
    public function build_coupons_context() {
        $coupons = $this->get_coupons();
        if ( empty( $coupons ) ) {
            return '';
        }

        $lines = array();
        foreach ( $coupons as $c ) {
            $line = "CODE:{$c['code']}|TYPE:{$c['type']}|AMOUNT:{$c['amount']}";
            if ( ! empty( $c['expiry'] ) ) {
                $line .= "|EXPIRES:{$c['expiry']}";
            }
            if ( ! empty( $c['min_amount'] ) ) {
                $line .= "|MIN:{$c['min_amount']}";
            }
            $lines[] = $line;
        }

        return "=== COUPONS ===\n" . implode( "\n", $lines ) . "\n=== END COUPONS ===";
    }
}
