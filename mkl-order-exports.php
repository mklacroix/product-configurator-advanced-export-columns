<?php
/*
Plugin Name: Advanced Order Export For WooCommerce - Add configurator items as columns
Version: 1.0.1
Author: Marc
Requires at least: 4.9
Tested up to: 6.5
WC tested up to: 8.8
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


class MKL_Export_Fields {
	/** Example columns, added programatically */
	public $the_fields = [
		'size' => array( 'label' => 'Size', 'colname' => 'Size', 'checked' => 1),
		'width'  => array( 'label' => 'Width', 'colname' => 'Width' ,'checked' => 1),	
	];

	public function __construct() {
		add_filter('woe_get_order_product_fields', [ $this, 'add_fields' ] );
		add_filter( "woe_fetch_order_products", [ $this, 'populate_fields' ], 10, 5 );

		add_filter( 'mkl_pc_layer_default_settings', array( $this, 'configurator_layer_settings' ), 200 );
		add_filter( 'mkl_pc_choice_default_settings', array( $this, 'configurator_choice_settings' ), 200 );
	}

	/**
	 * Add a setting to the admin 
	 *
	 * @param array $settings
	 * @return array
	 */
	public function configurator_layer_settings( $settings ) {
		$settings['aoe_column_id'] = array(
			'label' => __('Advanced Order Export Column ID', 'product-configurator-for-woocommerce' ),
			'type' => 'text',
			'priority' => 46,
			'condition' => '!data.not_a_choice',
			'section' => 'advanced',
		);
		return $settings;
	}

	/**
	 * Add a setting to the admin 
	 *
	 * @param array $settings
	 * @return array
	 */
	public function configurator_choice_settings( $settings ) {
		$settings['aoe_column_id'] = array(
			'label' => __('Advanced Order Export Column ID', 'product-configurator-for-woocommerce' ),
			'type' => 'text',
			'priority' => 46,
			'condition' => '!data.not_a_choice && "form" == data.layer_type',
			'section' => 'advanced',
		);
		return $settings;
	}

	/**
	 * Add the fields defined above to the available fields in the export plugin
	 *
	 * @param array $fields
	 * @return array
	 */
	public function add_fields( $fields ) {
		return array_merge( $fields, $this->the_fields );
	}

	/**
	 * Add the content
	 *
	 * @param array $products
	 * @param WC_Order $order
	 * @param array $labels
	 * @param [type] $format
	 * @param [type] $static_vals
	 * @return array
	 */
	public function populate_fields ( $products, $order, $labels, $format, $static_vals ) {
		
		// Make sure to not break the export if the configurator plugin isn't installed
		if ( ! function_exists( 'mkl_pc' ) ) return $products;
		// logdebug( ['export thingy', $labels, $format, $static_vals] );
		$fields = array_keys( $labels );
		$new_products_list = [];
		$line_items = $order->get_items( 'line_item' );
	
		foreach( $products as $item_id => $product ) {
			if ( $config_data = $line_items[$item_id]->get_meta( '_configurator_data' ) ) {
				$rwc = $line_items[$item_id]->get_meta( '_configurator_data_raw' );
				foreach( $config_data as $item_index => $selection ) {

					// Export ID
					$id = $selection->get_layer( 'aoe_column_id' );

					// Form fields
					if ( ! $id && 'form' === $selection->get_layer( 'type' ) ) {
						$id = $selection->get_choice( 'aoe_column_id' );
					}

					// If the layer ID is empty, try compiling an ID by replacing spaces in the layer name
					if ( ! $id ) {
						$id = trim( $selection->get_layer( 'name' ) );
						$id = strtolower( $id );
						$id = str_replace( ' ', '_', $id );
					}
					if ( $id && in_array( $id, $fields ) ) {
						if ( empty( $selection->layer_data ) && is_callable( [$selection, 'set_layer_data' ] ) && isset( $rwc[ $item_index ] ) ) {
							$selection->set_layer_data( $rwc[ $item_index ] );
						}
						if ( 'form' == $selection->get_layer( 'type' ) ) {
							if ( $selection->get( 'field_value' ) ) {
								if ( in_array( $selection->get_choice( 'text_field_type' ),[ 'select', 'radio' ] ) ) {
									$product[$id] = $selection->get( 'option_label' );
								} elseif ( 'calculation' == $selection->get_choice( 'text_field_type' ) ) {
									$product[$id] = $selection->get( 'extra_price' );
								} else {
									$product[$id] = $selection->get( 'field_value' );
								}
							}
						} else {
							if ( ! $product[$id] ) {
								$product[$id] = $selection->get_choice( 'name' );
							} else {
								$product[$id] .= ', ' . $selection->get_choice( 'name' );

							}
							if ( $selection->get_choice( 'has_text_field' ) && $selection->get( 'field_value' ) ) {
								if ( in_array( $selection->get_choice( 'text_field_type' ),[ 'select', 'radio' ] ) ) {
									$product[$id] .= ': ' . $selection->get( 'option_label' );
								} else {
									$product[$id] .= ': ' . $selection->get( 'field_value' );
								}
							}
						}
					}
					// Get the item name. E.g. Farve, or Vaelg overdel 
	
				}
	
				$new_products_list[$item_id] = $product;
			}
		}
	
		return $new_products_list;
	}
	
}

new MKL_Export_Fields();
