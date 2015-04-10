<?php

/**
 * Update checker for extensions
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once( PBS_PATH . 'inc/EDD_SL_Plugin_Updater.php' );

class GambitPBSandwichExtUpdater {
	
	const UPDATE_CHECKER_TRANSIENT = 'sandwich_update_checker_';
	const LICENSES_OPTION_GROUP = 'sandwich_extension_licenses';
	const LICENSES_ADMIN_SLUG = 'pbsandwich';
	const DEBUG = false;
	
	/**
	 * Extensions array:
	 * 	'store_url' => string URL to the EDD store selling the extension
	 * 	'name' => string The name of the extension. This should match the download name in EDD exactly
	 * 	'file' => string The path of the main script of the extension (__FILE__ called in the main script)
	 * 	'version' => string The current version of the extension plugin
	 * 	'author' => string The author name of the extension
	 * 	'ssl' => boolean Whether to use SSL for authentication
	 */
	protected $extensions;
	
	function __construct() {
		add_action( 'admin_menu', array( $this, 'gatherExtensions' ), 0 );
		add_action( 'admin_init', array( $this, 'checkForUpdates' ), 1 );
		add_action( 'admin_menu', array( $this, 'createLicensesPage' ) );
		add_action( 'admin_init', array( $this, 'activateDeactivateLicense' ) );
	}
	
	public function gatherExtensions() {
		$extensions = apply_filters( 'pbs_extension_updater', array() );
		
		// Generate a slug for the extension to be used for admin pages
		$this->extensions = array();
		foreach ( $extensions as $key => $extension ) {
			$extension['slug'] = strtolower( str_replace( ' ', '_', $extension[ 'name' ] ) );
			$extension['ssl'] = ! isset( $extension['ssl'] ) ? false : $extension['ssl'];
			$this->extensions[ $extension['slug'] ] = $extension;
		}
		
		// Sort by name
		ksort( $this->extensions );
	}
	
	public function checkForUpdates() {
		foreach ( $this->extensions as $extension ) {
		
			// Only check for updates every 3 hours
			$updateChecker = get_transient( self::UPDATE_CHECKER_TRANSIENT . $extension['slug'] );
			if ( false !== $updateChecker && ! self::DEBUG ) {
				continue;
			}
			
			// retrieve our license key from the DB
			$licenseKey = get_option( 'sandwich_license_' . $extension['slug'] );
			if ( empty( $licenseKey ) ) {
				continue;
			}

			// setup the updater
			$eddUpdater = new EDD_SL_Plugin_Updater( $extension['store_url'], $extension['file'],
				array( 
					'version' => $extension['version'], // current version number
					'license' => $licenseKey, // license key (used get_option above to retrieve from DB)
					'item_name' => $extension['name'], // name of this plugin
					'author' => $extension['author'], // author of this plugin
				)
			);
			
			set_transient( self::UPDATE_CHECKER_TRANSIENT . $extension['slug'], '1', 3 * HOUR_IN_SECONDS );
			
		}
	}
	
	public function createLicensesPage() {
		if ( count( $this->extensions ) ) {
			add_plugins_page( 'PB Sandwich', 'PB Sandwich', 'manage_options', self::LICENSES_ADMIN_SLUG, array( $this, 'renderLicensesPage' ) );
		}
	}
	
	public function renderLicensesPage() {
		?>
		<script>
		jQuery(document).ready(function($) {
			$('body').on('keypress', '#pbs_licenses input[type="text"]', function(e) {
				if ( e.which === 13 ) {
					$(this).parent().find('button').trigger('click');
					return false;
				}
			});
			$('body').on('click', '#pbs_licenses .edd_license_activate, #pbs_licenses .edd_license_deactivate', function(e) {
				e.preventDefault();
				$('#extension_being_activated').val( $(this).parent().find('[name="extension"]').val() );
				$('#license_action').val( $(this).is('.edd_license_activate') ? 'activate_license' : 'deactivate_license' );
				$('#pbs_licenses').submit();
			});
		});
		</script>
		<style>
			.form-table th, .form-table td {
			    border: 1px solid #D2D7D3;
			    padding: 20px 15px;
				vertical-align: middle;
			}
			.form-table thead {
				background: #DADFE1;
			}
		</style>
		
		
		<div class="wrap">
			<h2><?php _e( 'Page Builder Sandwich Extension License Activation', 'pbsandwich' ) ?></h2>
			<p class="desc"><?php printf( __( 'Enter the license keys that you have gotten when you purchased your PB Sandwich extensions in this page. You will get plugin updates for all your activated extensions along with our superb support. You can get your license keys when you purchase extensions from our website at %s', 'pbsandwich' ), '<a href="http://www.pbsandwi.ch/extensions" target="_blank">pbsandwi.ch</a>' ) ?></p>
				
			<form method="post" action="<?php admin_url( 'plugins.php?page=' . self::LICENSES_ADMIN_SLUG ) ?>" id="pbs_licenses">
				
				<input type="hidden" id="extension_being_activated" name="extension_being_activated"/>
				<input type="hidden" id="license_action" name="license_action"/>
				<?php wp_nonce_field( 'pbsandwich_license', 'license_nonce' ) ?>
				
				<?php // settings_fields( self::LICENSES_OPTION_GROUP ); ?>
				
				<table class="form-table">
					
					<thead>
						<tr>
							<th><?php _e( 'Extension', 'pbsandwich' ) ?></th>
							<th><?php _e( 'License Status', 'pbsandwich' ) ?></th>
							<th><?php _e( 'License Key', 'pbsandwich' ) ?></th>
						</tr>
					</thead>
					
					<tbody>
						<?php foreach ( $this->extensions as $slug => $extension ) : ?>
							<?php $licenseStatus = get_option( 'sandwich_license_status_' . $slug ); ?>
							<tr valign="top">	
								<th>
									<?php echo $extension['name'] ?>
								</th>
								<td>
									<?php if ( $licenseStatus == 'valid' ) : ?>
										<em style="color: #4DAF7C"><?php _e( 'Active', 'pbsandwich' ) ?></em>
									<?php else : ?>
										<em style="color: #D24D57"><?php echo empty( $licenseStatus ) ? __( 'Inactive', 'pbsandwich' ) : ucfirst( $licenseStatus ) ?></em>
									<?php endif; ?>
								</td>
								<td>
									<input type="hidden" name="extension" value="<?php echo esc_attr( $slug ) ?>"/>
									<input id="license_key_<?php echo esc_attr( $slug ) ?>" name="license_key_<?php echo esc_attr( $slug ) ?>" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'sandwich_license_' . $slug ) ) ?>" />
									<?php if ( $licenseStatus == 'valid' ) : ?>
										<button class="button-secondary edd_license_deactivate"><?php _e( 'Deactivate License', 'pbsandwich' ) ?></button>
									<?php else : ?>
										<button class="button-secondary edd_license_activate"><?php _e( 'Activate License', 'pbsandwich' ) ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					
				</table>
				
			</form>
			
		</div>
		<?php
	}
	
	public function activateDeactivateLicense() {
		
		// Security checks
		if ( ! is_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Check for required post data
		if ( empty( $_POST['extension_being_activated'] ) 
			 || empty( $_POST['license_action'] ) ) {
			return;
		}
		
		// run a quick security check 
	 	if ( ! check_admin_referer( 'pbsandwich_license', 'license_nonce' ) ) {
			return; // get out if we didn't click the Activate button
		}
		
		foreach ( $this->extensions as $slug => $extension ) {
			
			// The license which is being activated/deactivated
			if ( $slug == $_POST['extension_being_activated'] ) {
		
				$license = esc_attr( $_POST['license_key_' . $_POST['extension_being_activated'] ] );
				
				// Save the license
				update_option( 'sandwich_license_' . $slug, $license );
				
				if ( empty( $license ) ) {
					continue;
				}

				// data to send in our API request
				$apiParams = array( 
					'edd_action'=> $_POST['license_action'], 
					'license' => $license, 
					'item_name' => urlencode( $extension['name'] ),
					'url' => home_url()
				);
				
				// Call the license validation API
				$response = wp_remote_get( add_query_arg( $apiParams, $extension['store_url'] ), array( 'timeout' => 15, 'sslverify' => $extension['ssl'] ) );

				// make sure the response came back okay
				if ( is_wp_error( $response ) ) {
					continue;
				}
				
				// decode the license data
				$licenseData = json_decode( wp_remote_retrieve_body( $response ) );

				if ( $_POST['license_action'] == 'activate_license' ) {
					
					// $$licenseData->license will be either "valid" or "invalid"
					update_option( 'sandwich_license_status_' . $slug, $licenseData->license );
		
				} else {
		
					// $license_data->license will be either "deactivated" or "failed"
					if ( $licenseData->license == 'deactivated' ) {
						delete_option( 'sandwich_license_status_' . $slug );
					}
				}
				
				delete_transient( self::UPDATE_CHECKER_TRANSIENT . $slug );
		
			// Just save the licenses of the extensions which are not active
			} else {
				
				// Check if it's license is active
				$licenseStatus = get_option( 'sandwich_license_status_' . $slug );
				if ( $licenseStatus != 'valid' ) {
					update_option( 'sandwich_license_' . $slug, esc_attr( $_POST[ 'license_key_' . $slug ] ) );
				}
			}
		}

		wp_redirect( admin_url( 'plugins.php?page=' . self::LICENSES_ADMIN_SLUG ) );
	}

}

new GambitPBSandwichExtUpdater();