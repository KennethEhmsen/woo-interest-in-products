<?php
/**
 * Our helper functions to use across the plugin.
 *
 * @package WooInterestInProducts
 */

// Declare our namespace.
namespace LiquidWeb\WooInterestInProducts\Helpers;

// Set our aliases.
use LiquidWeb\WooInterestInProducts as Core;

/**
 * Check a product ID to see if it enabled.
 *
 * @param  integer $product_id  The ID of the product.
 * @param  boolean $strings     Optional return of yes/no strings.
 *
 * @return mixed
 */
function maybe_product_enabled( $product_id = 0, $strings = false ) {

	// Check the meta.
	$meta   = get_post_meta( $product_id, Core\PROD_META_KEY, true );

	// Return the string variant if requested.
	if ( $strings ) {
		return ! empty( $meta ) ? 'yes' : 'no';
	}

	// Return the boolean result.
	return ! empty( $meta ) ? true : false;
}

/**
 * Check the products provided for enabled items.
 *
 * @param  array $cart    The total array of cart data.
 * @param  array $enable  The enabled products.
 *
 * @return array
 */
function filter_product_cart( $cart = array(), $enable = array() ) {

	// Make sure we have everything required.
	if ( empty( $cart ) || empty( $enable ) ) {
		return false;
	}

	// Set an empty variable.
	$data   = array();

	// Loop our cart and look for products.
	foreach ( $cart as $key => $item ) {

		// Set my ID.
		$id = absint( $item['product_id'] );

		// If we have meta, add to the data array.
		if ( in_array( $id, $enable ) ) {
			$data[] = $id;
		}
	}

	// Return the array (or empty).
	return ! empty( $data ) ? $data : false;
}

/**
 * Return our base link, with function fallbacks.
 *
 * @return string
 */
function get_admin_menu_link() {

	// Bail if we aren't on the admin side.
	if ( ! is_admin() ) {
		return false;
	}

	// Set my slug.
	$slug   = trim( Core\MENU_SLUG );

	// Build out the link if we don't have our function.
	if ( ! function_exists( 'menu_page_url' ) ) {

		// Set my args.
		$args   = array( 'post_type' => 'product', 'page' => $slug );

		// Return the link with our args.
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	// Return using the function.
	return menu_page_url( $slug, false );
}

/**
 * Handle our redirect within the admin settings page.
 *
 * @param  array $args  The query args to include in the redirect.
 *
 * @return void
 */
function admin_page_redirect( $args = array(), $response = true ) {

	// Don't redirect if we didn't pass any args.
	if ( empty( $args ) ) {
		return;
	}

	// Handle the setup.
	$redirect_args  = wp_parse_args( $args, array( 'post_type' => 'product', 'page' => trim( Core\MENU_SLUG ) ) );

	// Add the default args we need in the return.
	if ( $response ) {
		$redirect_args  = wp_parse_args( array( 'wc-product-interest-response' => 1 ), $redirect_args );
	}

	// Now set my redirect link.
	$redirect_link  = add_query_arg( $redirect_args, admin_url( 'edit.php' ) );

	// Do the redirect.
	wp_safe_redirect( $redirect_link );
	exit;
}

/**
 * Check if we are on the admin settings tab.
 *
 * @param  string $hook  Optional hook sent from some actions.
 *
 * @return boolean
 */
function maybe_admin_settings_page( $hook = '' ) {

	// Can't be the admin page if we aren't admin, or don't have a hook.
	if ( ! is_admin() || empty( $hook ) ) {
		return false;
	}

	// Check the hook if we passed one.
	return 'product_page_product-interest-list' === sanitize_text_field( $hook ) ? true : false;
}

/**
 * Set up a recursive callback for multi-dimensional text arrays.
 *
 * @param  array   $input   The data array.
 * @param  boolean $filter  Whether to filter the empty values out.
 *
 * @return array
 */
function sanitize_text_recursive( $input, $filter = false ) {

	// Set our base output.
	$output = array();

	// Loop the initial data input set.
	// If our data is an array, kick it again.
	foreach ( $input as $key => $data ) {

		// Handle the setup.
		$setup  = is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : sanitize_text_field( $data );

		// Skip if are empty and said no filter.
		if ( empty( $setup ) && ! empty( $filter ) ) {
			continue;
		}

		// Add the setup to the data array.
		$output[ $key ] = $setup;
	}

	// Return the entire set.
	return $output;
}

/**
 * Run our individual strings through some clean up.
 *
 * @param  string $string  The data we wanna clean up.
 *
 * @return string
 */
function clean_export( $string ) {

	// Original PHP code by Chirp Internet: www.chirp.com.au
	// Please acknowledge use of this code by including this header.

	// Handle my different string checks.
	switch ( $string ) {

		case 't':
			$string = 'TRUE';
			break;

		case 'f':
			$string = 'FALSE';
			break;

		case preg_match( "/^0/", $string ):
		case preg_match( "/^\+?\d{8,}$/", $string ):
		case preg_match( "/^\d{4}.\d{1,2}.\d{1,2}/", $string ):
			$string = "'$string";
			break;

		case strstr( $string, '"' ):
			$string = '"' . str_replace( '"', '""', $string ) . '"';
			break;

		default:
			$string = mb_convert_encoding( $string, 'UTF-16LE', 'UTF-8' );

		// End all case breaks.
	}
}
