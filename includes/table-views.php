<?php
/**
 * Our table setup for the handling the data pieces.
 *
 * @package WooSubscribeToProducts
 */

// Set our aliases.
use LiquidWeb\WooSubscribeToProducts as Core;
use LiquidWeb\WooSubscribeToProducts\Helpers as Helpers;
use LiquidWeb\WooSubscribeToProducts\Database as Database;
use LiquidWeb\WooSubscribeToProducts\Queries as Queries;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Create a new table class that will extend the WP_List_Table.
 */
class SingleProductSubscriptions_Table extends WP_List_Table {

	/**
	 * SingleProductSubscriptions_Table constructor.
	 *
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 */
	public function __construct() {

		// Set parent defaults.
		parent::__construct( array(
			'singular' => __( 'Single Product Subscriptions', 'liquidweb-woocommerce-gdpr' ),
			'plural'   => __( 'Single Product Subscriptions', 'liquidweb-woocommerce-gdpr' ),
			'ajax'     => false,
		) );
	}

	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items() {

		// Roll out each part.
		$columns    = $this->get_columns();
		$hidden     = array();
		$sortable   = $this->get_sortable_columns();
		$dataset    = $this->table_data();

		// Handle our sorting.
		usort( $dataset, array( $this, 'sort_data' ) );

		$paginate   = 10;
		$current    = $this->get_pagenum();

		// Set my pagination args.
		$this->set_pagination_args( array(
			'total_items' => count( $dataset ),
			'per_page'    => $paginate
		));

		// Slice up our dataset.
		$dataset    = array_slice( $dataset, ( ( $current - 1 ) * $paginate ), $paginate );

		// Do the column headers
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Make sure we have the single action running.
		$this->process_single_action();

		// Make sure we have the bulk action running.
		$this->process_bulk_action();

		// And the result.
		$this->items = $dataset;
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table.
	 *
	 * @return Array
	 */
	public function get_columns() {

		// Build our array of column setups.
		$setup  = array(
			'cb'            => '<input type="checkbox" />',
			'visible_name'  => __( 'Customer Name', 'woo-subscribe-to-products' ),
			'product_name'  => __( 'Product Name', 'woo-subscribe-to-products' ),
			'signup_date'   => __( 'Signup Date', 'woo-subscribe-to-products' ),
		);

		// Return filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_column_items', $setup );
	}

	/**
	 * Display all the things.
	 *
	 * @return HTML
	 */
	public function display() {

		// Add a nonce for the bulk action.
		wp_nonce_field( 'wc_product_subs_nonce_action', 'wc_product_subs_nonce_name' );

		// And the parent display (which is most of it).
		parent::display();
	}

	/**
	 * Return null for our table, since no row actions exist.
	 *
	 * @param  object $item         The item being acted upon.
	 * @param  string $column_name  Current column name.
	 * @param  string $primary      Primary column name.
	 *
	 * @return null
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		return apply_filters( Core\HOOK_PREFIX . 'table_row_actions', '', $item, $column_name, $primary );
 	}

	/**
	 * Define the sortable columns.
	 *
	 * @return Array
	 */
	public function get_sortable_columns() {

		// Build our array of sortable columns.
		$setup  = array(
			'visible_name'  => array( 'visible_name', false ),
			'product_name'  => array( 'product_name', true ),
			'signup_date'   => array( 'signup_date', true ),
		);

		// Return it, filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_sortable_columns', $setup );
	}

	/**
	 * Return available bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {

		// Make a basic array of the actions we wanna include.
		$setup  = array( 'wc_product_subs_unsubscribe' => __( 'Unsubscribe', 'woo-subscribe-to-products' ) );

		// Return it filtered.
		return apply_filters( Core\HOOK_PREFIX . 'table_bulk_actions', $setup );
	}

	/**
	 * Handle bulk actions.
	 *
	 * @see $this->prepare_items()
	 */
	protected function process_bulk_action() {

		// Bail if we aren't on the page.
		if ( empty( $this->current_action() ) || 'wc_product_subs_unsubscribe' !== $this->current_action() ) {
			return;
		}

		// Make sure we have the page we want.
		if ( empty( $_GET['page'] ) || Core\MENU_SLUG !== sanitize_text_field( $_GET['page'] ) ) {
			return;
		}

		// Fail on a missing or bad nonce.
		if ( empty( $_POST['wc_product_subs_nonce_name'] ) || ! wp_verify_nonce( $_POST['wc_product_subs_nonce_name'], 'wc_product_subs_nonce_action' ) ) {
			Helpers\admin_page_redirect( array( 'success' => 0, 'errcode' => 'bad_nonce' ) );
		}

		// Check for the array of relationship IDs being passed.
		if ( empty( $_POST['wc_product_subs_relationship_ids'] ) ) {
			Helpers\admin_page_redirect( array( 'success' => 0, 'errcode' => 'no_ids' ) );
		}

		// Set and sanitize my IDs.
		$relationship_ids   = array_map( 'absint', $_POST['wc_product_subs_relationship_ids'] );

		// Now loop and kill each one.
		foreach ( $relationship_ids as $relationship_id ) {
			Database\delete_by_relationship( $relationship_id );
		}

		// If we had customer IDs passed, filter and purge transients.
		if ( ! empty( $_POST['wc_product_subs_customer_ids'] ) ) {
			$this->purge_customer_transients( $_POST['wc_product_subs_customer_ids'] );
		}

		// If we had product IDs passed, filter and purge transients.
		if ( ! empty( $_POST['wc_product_subs_product_ids'] ) ) {
			$this->purge_product_transients( $_POST['wc_product_subs_product_ids'] );
		}

		// Redirect to the success.
		Helpers\admin_page_redirect( array( 'success' => 1, 'action' => 'unsubscribed', 'count' => count( $relationship_ids ) ) );
	}

	/**
	 * Delete the transients for customer IDs.
	 *
	 * @param  array $customer_ids  The array of customer IDs we have.
	 *
	 * @return void
	 */
	protected function purge_customer_transients( $customer_ids = array() ) {

		// First sanitize, then filter.
		$customer_ids   = array_map( 'absint', $customer_ids );
		$customer_ids   = array_unique( $customer_ids );

		// Now loop and purge.
		foreach ( $customer_ids as $customer_id ) {
			delete_transient( 'woo_customer_subscribed_products_' . absint( $customer_id ) );
		}
	}

	/**
	 * Delete the transients for product IDs.
	 *
	 * @param  array $product_ids  The array of product IDs we have.
	 *
	 * @return void
	 */
	protected function purge_product_transients( $product_ids = array() ) {

		// First sanitize, then filter.
		$product_ids    = array_map( 'absint', $product_ids );
		$product_ids    = array_unique( $product_ids );

		// Now loop and purge.
		foreach ( $product_ids as $product_id ) {
			delete_transient( 'woo_product_subscribed_customers_' . absint( $product_id ) );
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @param  array  $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {

		// Set my ID.
		$id = absint( $item['id'] );

		// Return my checkbox.
		return '<input type="checkbox" name="wc_product_subs_relationship_ids[]" class="wc-product-subscriptions-admin-checkbox" id="cb-' . $id . '" value="' . $id . '" /><label for="cb-' . $id . '" class="screen-reader-text">' . __( 'Select subscription', 'woo-subscribe-to-products' ) . '</label>';
	}

	/**
	 * The visible name column.
	 *
	 * @param  array  $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_visible_name( $item ) {

		// Build my markup.
		$setup  = '';

		// Set the display name.
		$setup .= '<span class="wc-product-subscriptions-admin-table-display wc-product-subscriptions-admin-table-name">';
			$setup .= '<strong>' . esc_html( $item['showname'] ) . '</strong>';
		$setup .= '</span>';

		// Add a hidden field with the value.
		$setup .= '<input type="hidden" name="wc_product_subs_customer_ids[]" value="' . absint( $item['customer_id'] ) . '">';

		// Create my formatted date.
		$setup  = apply_filters( Core\HOOK_PREFIX . 'column_visible_name', $setup, $item );

		// Return, along with our row actions.
		return $setup . $this->row_actions( $this->setup_row_action_items( $item ) );
	}

	/**
	 * The product name column.
	 *
	 * @param  array  $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_product_name( $item ) {

		// Get my product info.
		$name   = get_the_title( $item['product_id'] );
		$edit   = get_edit_post_link( $item['product_id'], 'raw' );
		$view   = get_permalink( $item['product_id'] );

		// Build my markup.
		$setup  = '';

		// Set the product name name.
		$setup .= '<span class="wc-product-subscriptions-admin-table-display wc-product-subscriptions-admin-table-product-name">';
			$setup .= '<strong>' . esc_html( $name ) . '</strong>';
		$setup .= '</span>';

		// Break it up.
		$setup .= '<br>';

		// Include the various product links.
		$setup .= '<span class="wc-product-subscriptions-admin-table-display wc-product-subscriptions-admin-table-product-links">';

			// Show the view link.
			$setup .= '<a title="' . __( 'View Product', 'woo-subscribe-to-products' ) . '" href="' . esc_url( $view ) . '">' . esc_html__( 'View Product', 'woo-subscribe-to-products' ) . '</a>';

			$setup .= '&nbsp;|&nbsp;';

			// Show the edit link.
			$setup .= '<a title="' . __( 'Edit Product', 'woo-subscribe-to-products' ) . '" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit Product', 'woo-subscribe-to-products' ) . '</a>';

		$setup .= '</span>';

		// Add a hidden field with the value.
		$setup .= '<input type="hidden" name="wc_product_subs_product_ids[]" value="' . absint( $item['product_id'] ) . '">';

		// Return my formatted date.
		return apply_filters( Core\HOOK_PREFIX . 'column_product_name', $setup, $item );
	}

	/**
	 * The signup date column.
	 *
	 * @param  array  $item  The item from the data array.
	 *
	 * @return string
	 */
	protected function column_signup_date( $item ) {

		// Grab the desired date foramtting.
		$format = apply_filters( Core\HOOK_PREFIX . 'column_date_format', get_option( 'date_format', 'Y-m-d' ) );

		// Set the date to a stamp.
		$stamp  = strtotime( $item['signup_date'] );

		// Get my relative date.
		$show   = sprintf( _x( '%s ago', '%s = human-readable time difference', 'woo-subscribe-to-products' ), human_time_diff( $stamp, current_time( 'timestamp', 1 ) ) );

		// Build my markup.
		$setup  = '';

		// Set the product name name.
		$setup .= '<span class="wc-product-subscriptions-admin-table-display wc-product-subscriptions-admin-table-signup-date">';
			$setup .= date( $format, $stamp ) . '<br>';
			$setup .= '<small><em>' . esc_html( $show ) . '</em></small>';
		$setup .= '</span>';

		// Return my formatted date.
		return apply_filters( Core\HOOK_PREFIX . 'column_signup_date', $setup, $item );
	}

	/**
	 * Get the table data
	 *
	 * @return Array
	 */
	private function table_data() {

		// Pull our list of enabled products.
		$products   = Queries\get_enabled_products();

		// Bail with no data.
		if ( ! $products ) {
			return array();
		}

		// Set my empty.
		$data   = array();

		// Loop my enabled product data.
		foreach ( $products as $product_id ) {

			// Get my single subscription.
			$customers  = Queries\get_customers_for_product( $product_id );

			// Skip this product if no customers are subscribed.
			if ( ! $customers ) {
				continue;
			}

			// Now loop each customer info.
			foreach ( $customers as $customer_data ) {

				// Fetch the individual userdata.
				$user   = get_userdata( absint( $customer_data['customer_id'] ) );

				// Set the array of the data we want.
				$setup  = array(
					'id'            => absint( $customer_data['relationship_id'] ),
					'product_id'    => absint( $product_id ),
					'customer_id'   => absint( $customer_data['customer_id'] ),
					'username'      => $user->user_login,
					'showname'      => $user->display_name,
					'email_address' => $user->user_email,
					'signup_date'   => $customer_data['created'],
				);

				// Run it through a filter.
				$data[] = apply_filters( Core\HOOK_PREFIX . 'table_data_item', $setup );
			}

			// That's the end of our individual loops.
		}

		// Return our data.
		return apply_filters( Core\HOOK_PREFIX . 'table_data_array', $data, $products );
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array  $dataset      Our entire dataset.
	 * @param  string $column_name  Current column name
	 *
	 * @return mixed
	 */
	public function column_default( $dataset, $column_name ) {

		// Run our column switch.
		switch ( $column_name ) {

			case 'display_name' :
			case 'product_name' :
			case 'signup_date' :
				return ! empty( $dataset[ $column_name ] ) ? $dataset[ $column_name ] : '';

			default :
				return apply_filters( Core\HOOK_PREFIX . 'table_column_default', '', $dataset, $column_name );
		}
	}

	/**
	 * Handle the single row action.
	 *
	 * @return void
	 */
	protected function process_single_action() {
	}

	/**
	 * Create the row actions we want.
	 *
	 * @param  array $item  The item from the dataset.
	 *
	 * @return array
	 */
	private function setup_row_action_items( $item ) {

		// Set my links.
		$view   = get_edit_user_link( $item['customer_id'] );
		$email  = 'mailto:' . antispambot( $item['email_address'] );
		$orders = add_query_arg( array( 'post_type' => 'shop_order', 'post_status' => 'all', '_customer_user' => $item['customer_id'] ), admin_url( 'edit.php' ) );

		// Set up our array of items.
		$setup = array(

			'view'   => '<a class="wc-product-subscriptions-admin-table-link wc-product-subscriptions-admin-table-link-view" title="' . __( 'View Customer', 'woo-subscribe-to-products' ) . '" href="' . esc_url( $view ) . '">' . esc_html( 'View Customer', 'woo-subscribe-to-products' ) . '</a>',

			'orders' => '<a class="wc-product-subscriptions-admin-table-link wc-product-subscriptions-admin-table-link-orders" title="' . __( 'View Orders', 'woo-subscribe-to-products' ) . '" href="' . esc_url( $orders ) . '">' . esc_html( 'View Orders', 'woo-subscribe-to-products' ) . '</a>',

			'email'  => '<a class="wc-product-subscriptions-admin-table-link wc-product-subscriptions-admin-table-link-email" title="' . __( 'Email Customer', 'woo-subscribe-to-products' ) . '" href="' . esc_url( $email ) . '">' . esc_html( 'Email Customer', 'woo-subscribe-to-products' ) . '</a>',
		);

		// Return our row actions.
		return apply_filters( Core\HOOK_PREFIX . 'table_row_actions', $setup, $item );
	}

	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @return Mixed
	 */
	private function sort_data( $a, $b ) {

		// Set defaults and check for query strings.
		$ordby  = ! empty( $_GET['orderby'] ) ? $_GET['orderby'] : 'signup_date';
		$order  = ! empty( $_GET['order'] ) ? $_GET['order'] : 'asc';

		// Set my result up.
		$result = strcmp( $a[ $ordby ], $b[ $ordby ] );

		// Return it one way or the other.
		return 'asc' === $order ? $result : -$result;
	}
}