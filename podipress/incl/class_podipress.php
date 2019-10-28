<?php

// --- fo --- 2018-01-15 ---
// --- fo --- 2018-01-16 --- Fix: Sprache, load_plugin_textdomain
// --- fo --- 2018-02-22 --- ajax-Call
// --- uk --- 2018-02-23 --- http_build_query for GET parameters
// --- uk --- 2018-08-16 --- add cache for faster page builds 

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;

global $fo_podipress_db_version;
$fo_podipress_db_version = '1.0';

class PodiPress {

	var $options = array();

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
        add_filter( 'plugin_action_links', array( $this, 'fo_podipress_action_links' ), 10, 2 );
		add_action( 'plugins_loaded', array( $this, 'fo_podipress_init' ) );
		add_action( 'admin_menu', array( $this, 'fo_podipress_menu' ), 5 );
		add_action( 'admin_init', array( $this, 'fo_podipress_settings' ) );
        add_action( 'wp_ajax_podipress', array( $this, 'wp_ajax_podipress_data' ) );
        add_action( 'wp_ajax_nopriv_podipress', array( $this, 'wp_ajax_podipress_data' ) );
		add_action( 'wp_ajax_podipress_clear_cache', array( $this, 'wp_ajax_podipress_clear_cache' ) );
		add_shortcode( 'podipress', array( $this, 'fo_podipress_shortcode' ) );

		$this->options = get_option( 'podio_settings' );
	}

	
	static function fo_podipress_install() {
		global $wpdb;
		global $jal_db_version;

		$table_name = $wpdb->prefix . 'podipresscache';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . $table_name . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			expires datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			cache_value mediumtext DEFAULT '' NOT NULL,
			cache_key varchar(100) DEFAULT '' NOT NULL,
			PRIMARY KEY (id)
		) " . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		add_option( 'fo_podipress_db_version', $fo_podipress_db_version );
	}

	static function fo_podipress_install_data() {
		global $wpdb;

		$welcome_name = 'Mr. WordPress';
		$welcome_text = 'Congratulations, you just completed the installation!';

		$table_name = $wpdb->prefix . 'podipresscache';

		$wpdb->insert( 
			$table_name, 
			array( 
				'expires' => current_time( 'mysql' ), 
				'cache_key' => $welcome_name, 
				'cache_value' => $welcome_text, 
			) 
		);
	}
	
	public function wp_ajax_podipress_clear_cache() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'podipresscache';
		
		$delete = $wpdb->query("TRUNCATE TABLE `" . $table_name . "`");
		return __('PodiPress cache cleared.', 'podipress');
		wp_die();
	}
	
	public function wp_ajax_podipress_data() {
        if ( isset( $_GET[ 'p' ] ) ) {
            $parameter = $_GET[ 'p' ];
            if ( ! empty( $parameter ) && ( ! empty( $this->options[ 'podipress_name' ] ) ) ) {
                $url_parameter = '';
                if ( ! empty( $_GET ) ) {
                    $url_parameter_list =  $_GET;
                    $url_parameter = '/?';
                    foreach( $url_parameter_list as $pkey => $pvalue ) {
                        if ( ( 'action' != $pkey ) && ( 'p' != $pkey ) )
                            $url_parameter .= '&' . $pkey . '=' . $pvalue;
                    }
                }
            $request_url = $this->options[ 'podipress_url' ] . '/api/v1/' . $this->options[ 'podipress_name' ] . '/' . $parameter . $url_parameter;
            $response = wp_remote_get(
				$request_url, 
				array('headers' => array( 'Authorization' => 'Bearer ' . $this->options[ 'podipress_token' ] ), ) 
			);
            $http_status =  wp_remote_retrieve_response_code( $response );
            if ( 200 == $http_status )
                echo wp_remote_retrieve_body( $response );
            }
        }
        die();
	}

	public function fo_podipress_action_links( $links, $file ) {
	/* Static so we don't call plugin_basename on every plugin row. */
	static $this_plugin;
        if ( ! $this_plugin ) {
            $this_plugin = 'podipress/fo_podipress_project.php';
        }

        if ( $file == $this_plugin ) {
            $settings_link = '<a href="options-general.php?page=podio_project_config">' . __( 'Settings', 'podipress' ) . '</a>';
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

	public function fo_podipress_init() {
 		load_plugin_textdomain( 'podipress', false, 'podipress/languages' );
	}

	public function fo_podipress_menu() {
        add_options_page( 'PodiPress', 'PodiPress', 'manage_options', 'podio_project_config', array( $this, 'fo_create_podipress_settings' ) );
	}

	public function fo_podipress_settings() {
		register_setting( 'podio_project_settings', 'podio_settings', array( $this, 'podio_project_sanitize' ) );

		add_settings_section(
            'setting_section_id', // ID
            __('PodiPress settings', 'podipress'), // Title
            array( $this, 'print_section_info' ), // Callback
            'podio_project_config' // Page
		);
		add_settings_field(
            'podipress_url',
            __('Server URL', 'podipress'), 
            array( $this, 'podipress_url_callback' ),
            'podio_project_config', 
            'setting_section_id'
		);      
		add_settings_field(
            'podipress_name',
            __('Team name', 'podipress'), 
            array( $this, 'podipress_name_callback' ),
            'podio_project_config', 
            'setting_section_id'
		);      
		add_settings_field(
            'podipress_token',
            __('PodiPress access token', 'podipress'),
            array( $this, 'podipress_token_callback' ),
            'podio_project_config', 
            'setting_section_id'
		);
		add_settings_field(
            'podipress_cache_enabled',
            __('Enable the local cache', 'podipress'),
            array( $this, 'podipress_cache_enabled_callback' ),
            'podio_project_config', 
            'setting_section_id'
		);
		add_settings_field(
            'podipress_cache_duration',
            __('Maximum cache age in minutes', 'podipress'),
            array( $this, 'podipress_cache_duration_callback' ),
            'podio_project_config', 
            'setting_section_id'
		);  
		add_settings_field(
            'podipress_cache_clear',
            __('Delete the cached content', 'podipress'),
            array( $this, 'podipress_cache_clear_callback' ),
            'podio_project_config', 
            'setting_section_id'
		);  

	}

	/** 
	 * Get the settings option array and print one of its values
	 */
	public function podipress_url_callback() {
		printf(
		'<input type="text" id="podipress_url" name="podio_settings[podipress_url]" value="%s" style="width: 400px;" placeholder="https://podipress.com"/>',
		isset( $this->options['podipress_url'] ) ? esc_attr( $this->options['podipress_url']) : ''
		);
	}

	/** 
	 * Get the settings option array and print one of its values
	 */
	public function podipress_name_callback() {
		printf(
		'<input type="text" id="podipress_name" name="podio_settings[podipress_name]" value="%s" style="width: 400px;" placeholder="team"/>',
		isset( $this->options['podipress_name'] ) ? esc_attr( $this->options['podipress_name']) : ''
		);
	}

	/** 
	 * Get the settings option array and print one of its values
	 */
	public function podipress_token_callback() {
		printf(
		'<input type="text" id="podipress_token" name="podio_settings[podipress_token]" value="%s" style="width: 400px;" placeholder="token"/>',
		isset( $this->options['podipress_token'] ) ? esc_attr( $this->options['podipress_token']) : ''
		);
	}
	
	/** 
	 * Get the settings option array and print one of its values
	 */
	public function podipress_cache_enabled_callback() {
		printf(
		'<input type="checkbox" id="podipress_cache_enabled" name="podio_settings[podipress_cache_enabled]" value="true" %s/>',
		isset( $this->options['podipress_cache_enabled'] ) ? 'checked' : ''
		);
	}
	
	/** 
	 * Get the settings option array and print one of its values
	 */
	public function podipress_cache_duration_callback() {
		printf(
		'<input type="number" id="podipress_cache_duration" name="podio_settings[podipress_cache_duration]" value="%s" style="width: 100px;" placeholder="Minutes" step="1"/>',
		isset( $this->options['podipress_cache_duration'] ) ? esc_attr( $this->options['podipress_cache_duration']) : ''
		);
	}
	
	public function podipress_cache_clear_callback() {
		
		echo '<a id="podipress-clear-cache" class="button" href="#">'. __('Clear cache', 'podipress') . '</a>';
		echo '<script>jQuery(document).ready(function($) {           //wrapper
    			  $("#podipress-clear-cache").click(function() {             //event
					  var this2 = this;                      //use in callback
					  $.post(ajaxurl, {         		//POST request
					    _ajax_nonce: "'. wp_create_nonce( 'title_example' ) . '",     //nonce
						action: "podipress_clear_cache"            //action
					}, function(data) {                    //callback
						alert("'. __('Cache was cleared.', 'podipress') . '");
					});
			    });
			  });
			  </script>';
	}
	

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function podio_project_sanitize( $input ) {
		$new_input = array();

		if( isset( $input['podipress_url'] ) )
			$new_input['podipress_url'] = sanitize_text_field( $input['podipress_url'] );

		if( isset( $input['podipress_name'] ) )
			$new_input['podipress_name'] = sanitize_text_field( $input['podipress_name'] );

		if( isset( $input['podipress_token'] ) )
			$new_input['podipress_token'] = sanitize_text_field( $input['podipress_token'] );
		
		if( isset( $input['podipress_cache_enabled'] ) )
			$new_input['podipress_cache_enabled'] = sanitize_text_field( $input['podipress_cache_enabled'] );
		
		if( isset( $input['podipress_cache_duration'] ) )
			$new_input['podipress_cache_duration'] = sanitize_text_field( $input['podipress_cache_duration'] );

		return $new_input;
	}

	public function print_section_info() {
	}

	public function fo_create_podipress_settings() {
	
		$new_options = get_option( 'podio_settings' );
		$this->options = get_option( 'podio_settings' );

		if ( isset( $_POST[ 'podio_settings' ] ) ) {
			if ( wp_verify_nonce( $_POST[ '_wpnonce' ], 'podio_settings-options' ) ) {
				$new_options = $_POST[ 'podio_settings' ];

				if ( isset( $_POST[ 'podio_settings' ][ 'podipress_url' ] ) ) {
// 					update_option( 'podipress_url', $_POST[ 'podio_settings' ][ 'podipress_url' ], 'yes' );
                }
				if ( isset( $_POST[ 'podio_settings' ][ 'podipress_name' ] ) ) {
// 					update_option( 'podipress_name', $_POST[ 'podio_settings' ][ 'podipress_name' ], 'yes' );
                }
				if ( isset( $_POST[ 'podio_settings' ][ 'podipress_token' ] ) ) {
// 					update_option( 'podipress_token', $_POST[ 'podio_settings' ][ 'podipress_token' ], 'yes' );
                }
                update_option( 'podio_settings', $_POST[ 'podio_settings' ], 'yes' );
            }
			else {
				die( 'Fehler' );
			}
        }

		$this->options = get_option( 'podio_settings' );
		
		?>
		<div class="wrap">
			<form method="post" action="admin.php?page=podio_project_config">
			<?php
				// This prints out all hidden setting fields
				settings_fields( 'podio_settings' );
				do_settings_sections( 'podio_project_config' );
				submit_button();
			?>
			</form>
		</div>
		<?php
    }

	public function fo_podipress_shortcode( $args ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'podipresscache';
		
        if ( empty( $args ) ) {
            return __( 'Please check the Shortcode.', 'podipress' );
        }
        else {
            $out = '';
            $parameter = $args[ "0" ];
            if ( ! empty( $parameter ) && ( ! empty( $this->options[ 'podipress_name' ] ) ) ) {
                $url_parameters = array();
                if ( ! empty( $_GET ) ) {
                    $url_parameter_list =  $_GET;
                    $url_parameter = '/?';
                    foreach( $url_parameter_list as $pkey => $pvalue ) {
                        $url_parameters[$pkey] = $pvalue;
                    }
                }
				foreach( $args as $pkey => $pvalue ) {
					if ( $pkey == "0" ) {
						continue;
					}
                	$url_parameters[$pkey] = $pvalue;
					$tmp = $tmp . $pkey . ' => ' . $pvalue . "<br>\n";
                }

                $url_get_parameters = http_build_query($url_parameters);

                if (strlen($url_get_parameters) > 0) {
                    $url_get_fragment = '?' . $url_get_parameters;
                }
				else {
					$url_get_fragment = '';
				}
				$tmp = $tmp . $url_get_fragment;

                $request_url = $this->options[ 'podipress_url' ] . '/api/v1/' . $this->options[ 'podipress_name' ] . '/' . $parameter . '/' . $url_get_fragment;
				
				$cached = null;
				if($this->options[ 'podipress_cache_enabled' ] == 'true') {
					$cached = $wpdb->get_row( 
						$wpdb->prepare("SELECT * FROM `$table_name` WHERE cache_key = %s", $request_url)
					);
					if ( !is_null($cached) && ($cached->expires > current_time('mysql', true)) ) {
						return '<!-- PodiPress cache, expires ' . $cached->expires .' -->' . $cached->cache_value;
					}
				}
				
				
                $response = wp_remote_get( $request_url, array('timeout' => 30,
                    'headers' => array( 'Authorization' => 'Bearer ' . $this->options[ 'podipress_token' ] ), ) );
                $http_status =  wp_remote_retrieve_response_code( $response );
                if ( 200 == $http_status ) {
					$resp_body = wp_remote_retrieve_body( $response );
					// TODO: make this configurable
					$minutes = intval($this->options[ 'podipress_cache_duration' ]);
					$expires = date("Y-m-d H:i:s", time() + ($minutes * 60) );
					if (is_null($cached)) {
						$wpdb->insert( 
							$table_name, 
							array( 
								'expires' => $expires, 
								'cache_key' => $request_url, 
								'cache_value' => $resp_body, 
							) 
						);
					} else {
						$wpdb->update(
							$table_name,
							array( 
								'expires' => $expires, 
								'cache_key' => $request_url, 
								'cache_value' => $resp_body, 
							),
							array( 'id' => $cached->id )
						);
					}
                    return $resp_body;
				}
                elseif ( ( 404 == $http_status ) && ( is_array( $response ) ) ) {
					return $http_status . ' ' . wp_remote_retrieve_body( $response );
				}
                     
            }
            return $out;
        }
        return '';
	}

	static function fo_podio_project_activate_it() {
	}

	static function fo_podio_project_deactivate_it() {
	}

}


