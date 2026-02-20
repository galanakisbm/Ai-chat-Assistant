<?php
/**
 * XML Products loader.
 * Reads products from an XML file and returns them as a PHP array.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordAiChat_XmlProducts {

    /**
     * Load products from the XML file path stored in options.
     *
     * @param int $limit Maximum products to return (0 = unlimited).
     * @return array
     */
    public function get_products( $limit = 0 ) {
        $xml_path = get_option( 'word_ai_chat_xml_path', '' );
        if ( empty( $xml_path ) || ! file_exists( $xml_path ) ) {
            return array();
        }
        return $this->parse_xml( $xml_path, $limit );
    }

    /**
     * Parse an XML file and map fields according to saved mapping config.
     *
     * @param string $xml_path Absolute path to XML file.
     * @param int    $limit    Max products (0 = all).
     * @return array
     */
    public function parse_xml( $xml_path, $limit = 0 ) {
        $mapping_json = get_option( 'word_ai_chat_xml_field_mapping', '' );
        $mapping = $mapping_json ? json_decode( $mapping_json, true ) : array();

        // Defaults for standard products.xml.example layout
        $defaults = array(
            'id'           => 'id',
            'name'         => 'name',
            'description'  => 'description',
            'price'        => 'price',
            'url'          => 'url',
            'image'        => 'image',
            'category'     => 'categories',
            'availability' => 'availability',
        );
        $mapping = wp_parse_args( $mapping, $defaults );

        $products = array();

        libxml_use_internal_errors( true );
        $xml = simplexml_load_file( $xml_path );
        if ( ! $xml ) {
            return array();
        }

        $count = 0;
        foreach ( $xml as $node ) {
            if ( $limit > 0 && $count >= $limit ) {
                break;
            }

            $product = array();
            foreach ( $mapping as $internal_key => $xml_field ) {
                if ( ! empty( $xml_field ) && isset( $node->{ $xml_field } ) ) {
                    $product[ $internal_key ] = (string) $node->{ $xml_field };
                } else {
                    $product[ $internal_key ] = '';
                }
            }

            // Normalise availability / stock_status
            if ( ! isset( $product['stock_status'] ) ) {
                $avail = strtolower( $product['availability'] ?? '' );
                $product['stock_status'] = ( strpos( $avail, 'in' ) !== false || $avail === '1' ) ? 'instock' : 'outofstock';
            }

            $products[] = $product;
            $count++;
        }

        return $products;
    }

    /**
     * Auto-detect available XML fields from a file.
     *
     * @param string $xml_path
     * @return array List of field names found in the first product node.
     */
    public function detect_fields( $xml_path ) {
        libxml_use_internal_errors( true );
        $xml = simplexml_load_file( $xml_path );
        if ( ! $xml ) {
            return array();
        }
        $fields = array();
        foreach ( $xml as $node ) {
            foreach ( $node as $key => $val ) {
                $fields[] = (string) $key;
            }
            break; // Only need the first product
        }
        return $fields;
    }
}
