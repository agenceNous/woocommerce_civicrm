<?php

/**
 * Woocommerce CiviCRM Orders Contact Tab class.
 *
 * @since 2.0
 */
class Woocommerce_CiviCRM_Orders_Contact_Tab {

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks
	 *
	 * @since 0.2
	 */
	public function register_hooks() {
		// register custom php directory
		add_action( 'civicrm_config', array( $this, 'register_custom_php_directory' ), 10, 1 );
		// register custom template directory
		add_action( 'civicrm_config', array( $this, 'register_custom_template_directory' ), 10, 1 );
    	// register menu callback
		add_filter( 'civicrm_xmlMenu', array( $this, 'register_callback' ), 10, 1 );
		// Add Civicrm settings tab
		add_filter( 'civicrm_tabset', array( $this, 'add_orders_contact_tab' ), 10, 3 );
	}

	/**
	 * Register php directory.
	 *
	 * @since 2.0
	 * @param object $config The CiviCRM config object
	 */
	public function register_custom_php_directory( &$config ){
		WCI()->helper->fix_site();
		$custom_path = WOOCOMMERCE_CIVICRM_PATH . 'custom_php';
		$include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $include_path );
		WCI()->helper->unfix_site();
	}

	/**
	 * Register template directory.
	 *
	 * @since 2.0
	 * @param object $config The CiviCRM config object
	 */
	public function register_custom_template_directory( &$config ){
		WCI()->helper->fix_site();
		$custom_path = WOOCOMMERCE_CIVICRM_PATH . 'custom_tpl';
		$template = CRM_Core_Smarty::singleton()->addTemplateDir( $custom_path );
		$include_template_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $include_template_path );
		WCI()->helper->unfix_site();
	}

  	/**
	 * Register XML file.
	 *
	 * @since 2.0
	 * @param array $files The array for files used to build the menu
	 */
  	public function register_callback( &$files ){
		WCI()->helper->fix_site();
		$files[] = WOOCOMMERCE_CIVICRM_PATH . 'xml/menu.xml';
		WCI()->helper->unfix_site();
	}

	/**
	 * Add CiviCRM tab to the settings page.
	 *
	 * @since 2.0
	 * @uses 'woocommerce_settings_tabs_array' filter
	 * @param array $setting_tabs The setting tabs array
	 * @return array $setting_tabs The setting tabs array
	 */
	public function add_orders_contact_tab( $tabsetName, &$tabs, $context ) {
		$uid = abs(CRM_Core_BAO_UFMatch::getUFId( $context['contact_id'] ));

		$url = CRM_Utils_System::url( 'civicrm/contact/view/purchases', "reset=1&uid=$uid&no_redirect=1");

		$tabs[] = array( 'id'    => 'woocommerce-orders',
			'url'   => $url,
			'title' => __('Woocommerce Orders', 'woocommerce-civicrm'),
			'count' => $uid ? $this->count_orders($uid) : 0,
			'weight' => 99
		);
	}

	/**
	 * Get Customer raw orders.
	 *
	 * @since 2.2
	 * @param int $uid The User id for a contact (UFMatch)
	 * @return array $orders The raw orders
	 */
	private function _get_orders( $uid ){
		WCI()->helper->fix_site();
		$order_statuses = apply_filters( 'wc_order_statuses', array(
			'wc-pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
			'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
			'wc-on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
			'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
			'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
			'wc-refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
			'wc-failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
		));
		$customer_orders = get_posts( apply_filters( 'woocommerce_my_account_my_orders_query', array(
			'numberposts' => -1,
			'meta_key'    => '_customer_user',
			'meta_value'  => $uid,
			'post_type'   => 'shop_order',
			'post_status' => array_keys( $order_statuses )
		) ) );
		WCI()->helper->unfix_site();

		return $customer_orders;
	}

	/**
	 * Get Customer orders count.
	 *
	 * @since 2.2
	 * @param int $uid The User id for a contact (UFMatch)
	 * @return int $orders_count The number of orders
	 */
	public function count_orders( $uid ){
		return count($this->_get_orders( $uid ));
	}

	/**
	 * Get Customer orders.
	 *
	 * @since 2.1
	 * @param int $uid The User id for a contact (UFMatch)
	 * @return array $orders The orders
	 */
	public function get_orders( $uid ) {
		$customer_orders = $this->_get_orders( $uid );
		$orders = array();
		$date_format = get_option('date_format').' '.get_option('time_format');

		// If woocommerce is in another blog, ftech the order remotely
		// FIXME: for now, Partial datas
		// TODO: Fetch real datas
		if( WCI()->helper->is_remote_wc() ){
			WCI()->helper->fix_site();
			$site_url = get_site_url();
			foreach ( $customer_orders as $customer_order ) {
				$order = $customer_order;
				// $item_count = $order->get_item_count();
				// $total = $order->get_total();
				$orders[$customer_order->ID]['order_number'] = $order->ID;
				$orders[$customer_order->ID]['order_date'] = date_i18n($date_format , strtotime($order->post_date));
				$orders[$customer_order->ID]['order_billing_name'] = get_post_meta($order->ID, '_billing_first_name', true).' '.get_post_meta($order->ID, '_billing_last_name', true);
				$orders[$customer_order->ID]['order_shipping_name'] = get_post_meta($order->ID, '_shipping_first_name', true).' '.get_post_meta($order->ID, '_shipping_last_name', true);
				$orders[$customer_order->ID]['item_count'] = '--';
				$orders[$customer_order->ID]['order_total'] = get_post_meta($order->ID, '_order_total', true);
				$orders[$customer_order->ID]['order_status'] = $order->post_status;
				$orders[$customer_order->ID]['order_link'] = $site_url."/wp-admin/post.php?action=edit&post=".$order->ID;
			}
			return $orders;

			WCI()->helper->unfix_site();
		}

		// Else continue the main way
		$site_url = get_site_url();
		foreach ( $customer_orders as $customer_order ) {
			$order = new WC_Order($customer_order);
			//$order->populate( $customer_order );
			//$status = get_term_by( 'slug', $order->get_status(), 'shop_order_status' );
			$item_count = $order->get_item_count();
			$total = $order->get_total();
			$orders[$customer_order->ID]['order_number'] = $order->get_order_number();
			$orders[$customer_order->ID]['order_date'] = date_i18n($date_format , strtotime($order->get_date_created()));
			$orders[$customer_order->ID]['order_billing_name'] = $order->get_formatted_billing_full_name();
			$orders[$customer_order->ID]['order_shipping_name'] = $order->get_formatted_shipping_full_name();
			$orders[$customer_order->ID]['item_count'] = $item_count;
			$orders[$customer_order->ID]['order_total'] = $total;
			$orders[$customer_order->ID]['order_status'] = $order->get_status();
			$orders[$customer_order->ID]['order_link'] = $site_url."/wp-admin/post.php?action=edit&post=".$order->get_order_number();
		}
		if( ! empty( $orders ) ) return $orders;

		return false;
	}
}
