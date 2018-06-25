<?php

/**
 * Woocommerce CiviCRM Helper class.
 *
 * @since 2.0
 */

 class Woocommerce_CiviCRM_Helper {

	/**
	 * The class instance.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $instance The class instance
	 */
	public static $instance;

	/**
	 * The active Financial Types.
	 *
	 * Array of key/value pairs holding the active financial types.
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The financial types
	 */
	public $financial_types;

	/**
	 * The Address Location Type.
	 *
	 * Array of key/value pairs holding the address location types.
	 * @since 2.0
	 * @access public
	 * @var array $location_types The location types
	 */
	public $location_types;

	/**
	 * Woocommerce/CiviCRM mapped address location types.
	 *
	 * Array of key/value pairs holding the woocommerce/civicrm address location types.
	 * @since 2.0
	 * @access public
	 * @var array $mapped_location_types The mapped location types
	 */
	public $mapped_location_types;

	/**
	 * CiviCRM states.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $states The CiviCRM states
	 */
	public $civicrm_states = array();

	public $country_table_name = 'woocommerce_civicrm_country';
	public $state_table_name = 'woocommerce_civicrm_state';

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct(){

		// Prepare table names
		global $wpdb;
		$this->country_table_name = $wpdb->prefix.$this->country_table_name;
		$this->state_table_name = $wpdb->prefix.$this->state_table_name;

		//$this->financial_types = $this->get_financial_types();
		//$this->location_types = $this->get_address_location_types();
		//$this->civicrm_states = $this->get_civicrm_states();
		//$this->mapped_location_types = $this->set_mapped_location_types();

	}

	/**
	 * Returns a single instance of this object when called.
	 *
	 * @since 2.0
	 * @return object $instance Woocommerce_CiviCRM_Helper instance
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			// instantiate
			self::$instance = new Woocommerce_CiviCRM_Helper;
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
		}
		// always return instance
		return self::$instance;
	}

 	/**
 	 * Get CiviCRM contact_id.
 	 *
 	 * @since 2.0
 	 * @param object $order The order object
 	 * @return int $cid The contact_id
 	 */
 	public function civicrm_get_cid( $order ){

		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$match = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
				$current_user,
				$current_user->ID,
				$current_user->user_email,
				'WordPress', FALSE, 'Individual'
			);

			if ( ! is_object( $match ) ) {
				return FALSE;
			}

			return $match->contact_id;
		}

		// The customer is anonymous.  Look in the CiviCRM contacts table for a
		// contact that matches the billing email.
		$params = array(
			'email' => $order->get_billing_email(),
			'return.contact_id' => TRUE,
			'sequential' => 1,
		);

		try{
			$contact = civicrm_api3( 'Contact', 'get', $params );
		}
		catch ( Exception $e ) {
			return FALSE;
		}

		// No matches found, so we will need to create a contact.
		if ( count( $contact ) == 0 ) {
			return 0;
		}
		$cid = $contact['values'][0]['id'];

		return $cid;

	}

	/**
	 * Get CiviCRM UFMatch.
	 *
	 * Get UFMatch for contact_id or WP user_id.
	 * @since 2.0
	 * @param int $id The CiviCRM contact_id or WP user_id
	 * @param string $property 'contact_id' | 'uf_id'
	 * @return array $uf_match The UFMatch
	 */
	public function get_civicrm_ufmatch( $id, $property ){

	if( ! in_array( $property, array( 'contact_id', 'uf_id' ) ) ) return;
    
		try {
			$uf_match = civicrm_api3( 'UFMatch', 'getsingle', array(
				'sequential' => 1,
				$property => $id,
			)); 
		} catch ( Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		if( isset( $uf_match ) && is_array( $uf_match ) && ! $uf_match['is_error'] ) return $uf_match;
	}

	/**
	 * Function to get CiviCRM country ID for Woocommerce country ISO Code.
	 *
	 * @since 2.0
	 * @param string $woocommeerce_country WooCommerce country ISO code
	 * @return int $id CiviCRM country_id
	 */
	public function get_civi_country_id( $woocommerce_country ){

		if( empty( $woocommerce_country ) ) return;

		$result = civicrm_api3( 'Country', 'getsingle', array(
			'sequential' => 1,
			'iso_code' => $woocommerce_country,
		));

		if( ! $result['id'] ) return;

		return $result['id'];

	}

	/**
	 * Function to get CiviCRM country ISO Code for country_id.
	 *
	 * @since 2.0
	 * @param string $country_id CiviCRM country_id
	 * @return int $iso_code CiviCRM country ISO Code
	 */
	public function get_civi_country_iso_code( $country_id ){

		if( empty( $country_id ) ) return;

		$result = civicrm_api3( 'Country', 'getsingle', array(
			'sequential' => 1,
			'id' => $country_id,
		));

		if( ! $result['iso_code'] ) return;

		return $result['iso_code'];

	}

	/**
	 * Function to get CiviCRM state_province_id.
	 *
	 * @since 2.0
	 * @param string $woocommerce_state Woocommerce state
	 * @param int $country_id CiviCRM country_id
	 * @return int $id CiviCRM state_province_id
	 */
	public function get_civi_state_province_id( $woocommerce_state, $country_id ){

		if( empty( $woocommerce_state ) ) return;

		if( empty( $this->civicrm_states ) ) $this->civicrm_states = $this->get_civicrm_states();

		foreach ( $this->civicrm_states as $state_id => $state ) {
			if( $state['country_id'] == $country_id && $state['abbreviation'] == $woocommerce_state ) return $state['id'];

			if( $state['country_id'] == $country_id && $state['name'] == $woocommerce_state ) return $state['id'];
		}
	}

	/**
	 * Function to get CiviCRM State/Province name or abbreviation.
	 *
	 * @since 2.0
	 * @param int $state_province_id CiviCRM state id
	 * @return string $name CiviCRM State/Province name or abbreviation
	 */
	public function get_civi_state_province_name( $state_province_id ){

		if( empty( $state_province_id ) ) return;

		if( empty( $this->civicrm_states ) ) $this->civicrm_states = $this->get_civicrm_states();

		$civi_state = $this->civicrm_states[$state_province_id];

		$woocommerce_countries = new WC_Countries();

		foreach ( $woocommerce_countries->get_states() as $country => $states ) {
			$found = array_search( $civi_state['name'], $states );
			if( ! empty( $states ) && $found ) return $found;
		}
    
		return $civi_state['name'];
	}

	/**
	 * Function to get Woocommerece CiviCRM address map.
	 *
	 * @since 2.0
	 * @param  string $address_type Woocommerce address type 'billing' || 'shipping'
	 * @return array $mapped_address The address maps
	 */
	public function get_mapped_address( $address_type ){

		/**
		 * Filter address map.
		 *
		 * @since 2.0
		 * @param array $mapped_address
		 */
		return  apply_filters( 'woocommerce_civicrm_address_map', array(
			$address_type . '_address_1' => 'street_address',
			$address_type . '_address_2' => 'supplemental_address_1',
			$address_type . '_city' => 'city',
			$address_type . '_postcode' => 'postal_code',
			$address_type . '_country' => 'country_id',
			$address_type . '_state' => 'state_province_id',
			$address_type . '_company' => 'name',
		) );
	}

	/**
	 * Get CiviCRM states.
	 *
	 * Build multidimentional array of CiviCRM states | array( 'state_id' => array( 'name', 'id', 'abbreviation', 'country_id' ) )
	 * @since 2.0
	 */
	public function get_civicrm_states(){

		if( ! empty( $this->civicrm_states ) ) return $this->civicrm_states;

		$civicrm_states = array();

		// Get states from cache, if set
		if ($this->check_yes_no_value(get_option('woocommerce_civicrm_cache_data'))) {
			$this->get_cached_civicrm_state($civicrm_states);
		} else {
			// If cache is not set, get states from civicrm
			$query = 'SELECT id, name, country_id, abbreviation FROM civicrm_state_province';

			$dao = CRM_Core_DAO::executeQuery( $query );

			while( $dao->fetch() ){
				$civicrm_states[$dao->id] = array(
					'id' => $dao->id,
					'name' => $dao->name,
					'abbreviation' => $dao->abbreviation,
					'country_id' => $dao->country_id
				);
			}
		}
    
		return $civicrm_states;
	}

	/**
	 * Set Woocommerce CiviCRM mapped location types.
	 *
	 * @since 2.0
	 * @return array $mapped_location_types The mapped location types
	 */
	private function set_mapped_location_types(){

		/**
		 * Filter Woocommerce CiviCRM location types
		 *
		 * @since 2.0
		 * @param array $mapped_location_types
		 */
		return apply_filters( 'woocommerce_civicrm_mapped_location_types',  array(
			'billing' => get_option( 'woocommerce_civicrm_billing_location_type_id' ),
			'shipping' => get_option( 'woocommerce_civicrm_shipping_location_type_id' )
		) );
	}

	/**
	 * Get CiviCRM Financial Types.
	 * 
	 * @since 2.0
	 * @return array $financialTypes The financial types
	 */
	public function get_financial_types(){

		if ( isset( $this->financial_types ) ) return $this->financial_types;

		$params = array(
			'sequential' => 1,
			'is_active' => 1,
		);

		/**
		 * Filter Financial type params before calling the Civi's API.
		 *
		 * @since 2.0
		 * @param array $params The params to be passsed to the API
		 */
		$financialTypesResult = civicrm_api3( 'FinancialType', 'get', apply_filters( 'woocommerce_civicrm_financial_types_params', $params ) );

		$financialTypes = array();
		foreach( $financialTypesResult['values'] as $key => $value ) {
			$financialTypes[$value['id']] = $value['name'];
		}

		return $financialTypes;

	}
  
	/**
	 * Get CiviCRM Address Location Types.
	 * 
	 * @since 2.0
	 * @return array $addressTypes The address location types
	 */
	public function get_address_location_types(){

		if ( isset( $this->location_types ) ) return $this->location_types;

		$addressTypesResult = civicrm_api3( 'Address', 'getoptions', array( 'field' => 'location_type_id' ) );
		return $addressTypesResult['values'];

	}
  
	/**
	 * Function to check whether a value is (string) 'yes'.
	 * 
	 * @param  string $value
	 * @return bool true | false
	 */
	public function check_yes_no_value( $value ){
		if( $value == 'yes' ) return true;
		return false;
	}

	/**
	 * Function to cache civicrm data
	 */
	public function cache_civicrm_data(){
		// Cache civicrm country
		$this->cache_civicrm_country();

		// Cache civicrm country
		$this->cache_civicrm_state();
	}

	/**
	 * Function to cached civicrm country
	 */
	public function get_cached_civicrm_country(&$civicrm_countries){
		global $wpdb;
		// Check if country table exists in WP DB
		if ($this->check_if_table_exists($this->country_table_name)) {
			$query = "SELECT * FROM {$this->country_table_name} ORDER BY `name`";
			$result = $wpdb->get_results($query);
			foreach ($result as $instance) {
				$civicrm_countries[$instance->id] = $instance->iso_code;
			}
		}
	}

	/**
	 * Function to cached civicrm state
	 */
	public function get_cached_civicrm_state(&$civicrm_states){
		global $wpdb;
		// Check if state table exists in WP DB
		if ($this->check_if_table_exists($this->state_table_name)) {
			$query = "SELECT * FROM {$this->state_table_name} ORDER BY `name`";
			$result = $wpdb->get_results($query);
			foreach ($result as $instance) {
				$civicrm_states[$instance->id] = array(
					'id' => $instance->id,
					'name' => $instance->name,
					'abbreviation' => $instance->abbreviation,
					'country_id' => $instance->country_id
				);
			}
		}
	}

	/**
	 * Function to cache civicrm country
	 */
	public function cache_civicrm_country(){

		global $wpdb;

		// Check if country table exists in WP DB
		if (!$this->check_if_table_exists($this->country_table_name)) {
			//table not in database. Create new table
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->country_table_name} (
`id` mediumint(9) NOT NULL AUTO_INCREMENT,
`name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Country Name',
`iso_code` char(2) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'ISO Code',
`country_code` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'National prefix to be used when dialing TO this country.',
UNIQUE KEY id (id)
) $charset_collate;";

			$wpdb->query($wpdb->prepare($sql));
		}

		$wpdb->query($wpdb->prepare("TRUNCATE TABLE {$this->country_table_name}"));

		// Get country list from civicrm
		$countries = civicrm_api3( 'Country', 'get', array(
			'sequential' => 1,
			'options' => array( 'limit' => 0 ),
		));
		foreach($countries['values'] as $country) {
			$id = $country['id'];
			$country_name = $country['name'];
			$iso_code = $country['iso_code'];
			$insertSql = "INSERT INTO {$this->country_table_name} (id, name, iso_code)
			VALUES({$id}, '{$country_name}', '{$iso_code}')";
			$wpdb->query($wpdb->prepare($insertSql));
		}
	}


	/**
	 * Function to cache civicrm state
	 */
	public function cache_civicrm_state(){

		global $wpdb;

		// Check if country table exists in WP DB
		if (!$this->check_if_table_exists($this->state_table_name)) {
			//table not in database. Create new table
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->state_table_name} (
`id` mediumint(9) NOT NULL AUTO_INCREMENT,
`name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Name of State/Province',
`abbreviation` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '2-4 Character Abbreviation of State/Province',
`country_id` int(10) unsigned NOT NULL COMMENT 'ID of Country that State/Province belong',
UNIQUE KEY id (id)
) $charset_collate;";

			$wpdb->query($wpdb->prepare($sql));
		}

		$wpdb->query($wpdb->prepare("TRUNCATE TABLE {$this->state_table_name}"));

		$query = 'SELECT id, name, country_id, abbreviation FROM civicrm_state_province';

		$dao = CRM_Core_DAO::executeQuery( $query );
		$civicrm_states = array();
		while( $dao->fetch() ){
			$state_id = $dao->id;
			$state_name = $dao->name;
			$abbreviation = $dao->abbreviation;
			$country_id = $dao->country_id;

			$insertSql = "INSERT INTO {$this->state_table_name} (id, name, abbreviation, country_id)
			VALUES({$state_id}, '{$state_name}', '{$abbreviation}', $country_id)";
			$wpdb->query($wpdb->prepare($insertSql));
		}
	}

	/**
	 * Function to check if a table exists in WP DB
	 */
	public function check_if_table_exists($table_name){

		global $wpdb;
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
			return TRUE;
		}

		return FALSE;
	}
}
