<?php
/*
Plugin Name: Options Framework Import/Export
Plugin URI:
Description: Import/Export your "Options Framework Theme Options" via copy and paste
Version: 2.0
Author: Kathy Darling
Author URI: http://kathyisawesome.com
Requires at least: 4.0
Tested up to: 4.0.1


Plugin version of the Options Framework Fork by Gilles Vauvarin

 * This code is a plugin version of the Options Framework Fork by Gilles Vauvarinfork
 * which itself borrows heavily from the WooThemes Framework admin-backup.php file.

    Copyright: © 2012 Kathy Darling.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// don't load directly
if ( ! function_exists( 'is_admin' ) ) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit();
}



// only run if OF is active
$active_plugins = (array) get_option( 'active_plugins', array() );

if ( is_multisite() )
	$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

if( ! in_array( 'options-framework/options-framework.php', $active_plugins ) && ! array_key_exists( 'options-framework/options-framework.php', $active_plugins ) )
	return;

if ( ! class_exists( 'OF_Import_Export' ) ) :

class OF_Import_Export {

	/**
	 * @var OF_Import_Export - the single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * variables
	 */
	private $of_options;
	private $theme_options;

	/**
	 * Main OF_Import_Export instance.
	 *
	 * Ensures only one instance of OF_Import_Export is loaded or can be loaded
	 *
	 * @static
	 * @return OF_Import_Export - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ) );
	}


	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ) );
	}


	public function __construct () {

		// Get the theme name from the database.
		$of_options = get_option( 'optionsframework' );
		$this->options_key = $of_options['id'];
		$this->theme_options = get_option( $this->options_key );

		add_filter( 'of_options', array( $this, 'add_options' ), 9999 );

		add_filter( 'optionsframework_import', array( $this, 'import_option_type' ), 10, 3 );
		add_filter( 'optionsframework_export', array( $this, 'export_option_type' ), 10, 3 );
		
		add_action( 'sanitize_option_' . $this->options_key, array( $this, 'import_settings' ), 1 );

		add_action( 'appearance_page_options-framework', array( $this, 'add_save_notice' ) );

		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );
		}

	}

	
	/*
	 * Make Plugin Translation-ready
	 * @since 1.0
	 */

	public function load_text_domain() {
	   load_plugin_textdomain( 'options-framework-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/*
	 * Add import/export options to Theme Options Tab
	 *
	 * @param array $options
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function add_options( $options ){
		$options[] = array(
			'name' => __( 'Import/Export', 'of_import_export' ),
			'type' => 'heading'
		);
		$options[] = array(
			'name' => __( 'Import Settings', 'of_import_export' ),
			'id' => 'import_settings',
			'rows' => 10,
			'type' => 'import'
	     );
		$options[] = array(
			'name' => __( 'Export Settings', 'of_import_export' ),
			'desc' => __( 'Select all and copy to export your settings.', 'of_import_export'  ),
			'id' => 'export_settings',
			'type' => 'export'
	     );
		return $options;
	}




	/*
	 * Define the import type
	 * the markup is a little funny b/c we're sneaking an input into the description
	 *
	 * @param array $options
	 * @return array
	 *
	 * @since 1.0.0
	 */
    public function import_option_type( $option_name, $option, $values ){

		$output = sprintf( '<textarea name="%s[import_settings]" class="of-input" rows="10"></textarea></div><!--.controls-->', $this->options_key );

		$desc = __( 'Paste your exported settings here. When you click "Import" your settings will be imported to this site.', 'of_import_export'  );
		$value = esc_attr__( 'Import', 'of_import_export' );
		$msg = esc_js( __( 'Click OK to import. All current theme settings will be overwritten!', 'of_import_export' ) );

		$output .= sprintf( '<div class="explain">%s<p><input type="submit" name="of_import" class="button button-secondary" value="%s" onclick="return confirm( \'%s\' );" />', $desc, $value, $msg );
				
		return $output;
		 
    } 

	/*
	 * Define the export type
	 *
	 * @param array $options
	 * @return array
	 *
	 * @since 1.0.0
	 */
    public function export_option_type( $option_name, $option, $values ){
	
		if ( $this->theme_options && is_array( $this->theme_options ) ) {
			// Add the theme name
			$this->theme_options['theme-name'] = $this->options_key;

			// Generate the export data.
			$val = base64_encode( maybe_serialize( (array)$this->theme_options ) );
		} else {
			$val = __( 'ERROR! You don\'t have any options to export. Trying saving your options first.', 'of_import_export' );
		}

		$output = '<textarea disabled="disabled" class="of-input" rows="10">' . esc_textarea( $val ) . '</textarea>';
				
		return $output;
		 
    } 


	/*
	 * Import the settings
	 * happens on options validation hook, but re-directs before OF's validation can run
	 *
	 * @param array $input
	 * @return array
	 *
	 * @since 1.0.0
	 */
    public function import_settings( $input ){

		if ( isset( $input['import_settings'] ) && trim( $input['import_settings'] ) !== '' ){ 

			// decode the pasted data
			$data = (array) maybe_unserialize( base64_decode( $input['import_settings'] ) );

			if( is_array( $data ) && isset( $data['theme-name'] ) && $this->options_key == $data['theme-name'] ){
	
				unset( $data['theme-name'] );

				// Update the settings in the database
				update_option( $this->options_key, $data );
				update_option( 'of_import_happened', 'success' );

			} else {

				update_option( 'of_import_happened', 'fail' );
			}

			//remove_action( 'optionsframework_after_validate', array( 'Options_Framework_Admin', 'save_options_notice' ) );

			/**
			 * Redirect back to the settings page that was submitted
			 */
			$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
			wp_redirect( $goback );
			exit;
		
		} 

		return $input;

    }



	/*
	 * Add notices for import success/failure
	 * couldn't go traditional route since we're skipping the OF validation 
	 *
	 * @param array $input
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function add_save_notice(){ 

		$success = get_option( 'of_import_happened', false );
		if( $success ){
			
			remove_filter( 'sanitize_option_' . $this->options_key, array( 'Options_Framework_Admin', 'validate_options' ) );
			
			if( $success === 'success' ) {
				add_settings_error( 'options-framework', 'import_options', __( 'Options imported.', 'of_import_export' ), 'updated fade' );	
			} else {
				add_settings_error( 'options-framework', 'import_options_fail', __( 'Options could not be imported.', 'of_import_export' ), 'error' );
			}
		}
		
		delete_option( 'of_import_happened' );

	}

} // End Class

endif;

/**
 * Returns the main instance of OF_Import_Export to prevent the need to use globals.
 *
 * @return OF_Import_Export
 */
function OF_Import_Export() {
	return OF_Import_Export::instance();
}

// Launch the whole plugin
OF_Import_Export();
?>