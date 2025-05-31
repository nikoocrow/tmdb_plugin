<?php
/*
Plugin Name: TMDb Upcoming Movies
Description: Displays upcoming movies from TMDb using a shortcode. Includes admin settings to securely store your API key.
Version: 1.2
Author: Nicolas Castro - nikocrow
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

foreach ( glob( plugin_dir_path( __FILE__ ) . 'shortcodes/*.php' ) as $file ) {
	require_once $file;
}

require_once plugin_dir_path( __FILE__ ) . 'tmdb-functions.php';


/* ---------------------------------------------------
 * ADMIN PAGE FOR API KEY SETTINGS
 * -------------------------------------------------- */
add_action( 'admin_menu', 'tmdb_create_menu' );

function tmdb_create_menu() {
	add_menu_page(
		'TMDb Settings',
		'TMDb',
		'manage_options',
		'tmdb-settings',
		'tmdb_settings_page',
		'dashicons-video-alt2',
		80
	);
}

function tmdb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['tmdb_save'] ) && check_admin_referer( 'tmdb_save_action', 'tmdb_nonce' ) ) {
		update_option( 'tmdb_api_key', sanitize_text_field( $_POST['tmdb_api_key'] ) );
		echo '<div class="updated notice"><p>API key saved successfully.</p></div>';
	}

	$api_key = esc_attr( get_option( 'tmdb_api_key', '' ) );
	?>
	<div class="wrap">
		<h1>TMDb Settings</h1>
		<form method="post">
			<?php wp_nonce_field( 'tmdb_save_action', 'tmdb_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tmdb_api_key">TMDb API Key</label></th>
					<td><input type="text" id="tmdb_api_key" name="tmdb_api_key" value="<?php echo $api_key; ?>" class="regular-text" /></td>
				</tr>
			</table>
			<?php submit_button( 'Save API Key', 'primary', 'tmdb_save' ); ?>
		</form>
	</div>
	<?php
}













