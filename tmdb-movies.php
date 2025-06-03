<?php
/*
Plugin Name: TMDb Movies
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

function tmdb_get_api_key() {
    return get_option('tmdb_api_key', '');
}

add_action('admin_menu', 'tmdb_admin_menu', 9);
function tmdb_admin_menu() {
    add_menu_page(
        'TMDb Movies',
        'TMDb Movies',
        'manage_options',
        'tmdb-movies',
        'tmdb_main_page',
        'dashicons-video-alt3',
        30
    );
    
    add_submenu_page(
        'tmdb-movies',
        'TMDb Settings',
        'Settings',
        'manage_options',
        'tmdb-settings',
        'tmdb_settings_page'
    );
    
    add_submenu_page(
        'tmdb-movies',
        'All Movies',
        'All Movies',
        'manage_options',
        'tmdb-all-movies',
        'tmdb_all_movies_admin_page'
    );
    
    add_submenu_page(
        'tmdb-movies',
        'Upcoming Movies',
        'Upcoming Movies',
        'manage_options',
        'tmdb-upcoming-movies',
        'tmdb_upcoming_movies_admin_page'
    );
    
    add_options_page(
        'TMDb Settings',
        'TMDb Settings',
        'manage_options',
        'tmdb-settings-backup',
        'tmdb_settings_page'
    );
}

function tmdb_main_page() {
    ?>
    <div class="wrap">
        <h1>TMDb Movies Plugin</h1>
        <div class="card" style="max-width: none;">
            <h2>Welcome to TMDb Movies Plugin</h2>
            <p>This plugin allows you to display movie information from The Movie Database (TMDb).</p>
            
            <h3>Auto-created pages:</h3>
            <ul>
                <li><a href="<?php echo home_url('/all-movies'); ?>" target="_blank">All Movies</a></li>
                <li><a href="<?php echo home_url('/upcoming-movies'); ?>" target="_blank">Upcoming Movies</a></li>
                <li><a href="<?php echo home_url('/my-wishlist'); ?>" target="_blank">My Wishlist</a></li>
            </ul>
            
            <h3>Quick actions:</h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=tmdb-settings'); ?>" class="button button-primary">Configure API Key</a>
                <a href="<?php echo admin_url('admin.php?page=tmdb-all-movies'); ?>" class="button">View All Movies</a>
                <a href="<?php echo admin_url('admin.php?page=tmdb-upcoming-movies'); ?>" class="button">Upcoming Movies</a>
            </p>
            
            <h3>System status:</h3>
            <?php
            $api_key = get_option('tmdb_api_key', '');
            if (empty($api_key)) {
                echo '<p style="color: #d63638;"><strong>⚠️ API Key not configured</strong> - <a href="' . admin_url('admin.php?page=tmdb-settings') . '">Configure now</a></p>';
            } else {
                echo '<p style="color: #00a32a;"><strong>✅ API Key configured correctly</strong></p>';
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'tmdb_wishlist';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            
            if ($table_exists) {
                echo '<p style="color: #00a32a;"><strong>✅ Wishlist table created</strong></p>';
            } else {
                echo '<p style="color: #d63638;"><strong>⚠️ Wishlist table does not exist</strong> - Will be created automatically</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

function tmdb_all_movies_admin_page() {
    ?>
    <div class="wrap">
        <h1>All Movies - Preview</h1>
        <p>This is a preview of the "All Movies" page on the frontend:</p>
        <iframe src="<?php echo home_url('/all-movies'); ?>" width="100%" height="600" style="border: 1px solid #ccc;"></iframe>
        <p><a href="<?php echo home_url('/all-movies'); ?>" target="_blank" class="button button-primary">View full page</a></p>
    </div>
    <?php
}

function tmdb_upcoming_movies_admin_page() {
    ?>
    <div class="wrap">
        <h1>Upcoming Movies - Preview</h1>
        <p>This is a preview of the "Upcoming Movies" page on the frontend:</p>
        <iframe src="<?php echo home_url('/upcoming-movies'); ?>" width="100%" height="600" style="border: 1px solid #ccc;"></iframe>
        <p><a href="<?php echo home_url('/upcoming-movies'); ?>" target="_blank" class="button button-primary">View full page</a></p>
    </div>
    <?php
}

function tmdb_settings_page() {
    if (isset($_POST['submit'])) {
        update_option('tmdb_api_key', sanitize_text_field($_POST['tmdb_api_key']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $api_key = get_option('tmdb_api_key', '');
    ?>
    <div class="wrap">
        <h1>TMDb Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">TMDb API Key</th>
                    <td>
                        <input type="text" name="tmdb_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                        <p class="description">Enter your TMDb API key. Get one at <a href="https://www.themoviedb.org/settings/api" target="_blank">TMDb API</a></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <hr>
        <h2>Debug Tools</h2>
        <div style="background:#f9f9f9;padding:15px;border-radius:4px;margin:20px 0;">
            <h3>Wishlist Table Status</h3>
            <p><?php echo do_shortcode('[debug_wishlist]'); ?></p>
            
            <h3>Force Create Table</h3>
            <p><?php echo do_shortcode('[force_create_wishlist_table]'); ?></p>
        </div>
    </div>
    <?php
}

register_activation_hook(__FILE__, 'tmdb_plugin_activate');
function tmdb_plugin_activate() {
    if (function_exists('tmdb_create_wishlist_table')) {
        tmdb_create_wishlist_table();
    }
    
    tmdb_create_pages();
    flush_rewrite_rules();
}

function tmdb_create_pages() {
    $pages = array(
        'all-movies' => array(
            'title' => 'All Movies',
            'content' => '[tmdb_all_movies]'
        ),
        'upcoming-movies' => array(
            'title' => 'Upcoming Movies',
            'content' => '[upcoming_movies number="20"]'
        ),
        'movie-detail-page' => array(
            'title' => 'Movie Detail',
            'content' => '[movie_detail id="{GET}"]'
        ),
        'my-wishlist' => array(
            'title' => 'My Wishlist',
            'content' => '[user_wishlist]'
        )
    );
    
    foreach ($pages as $slug => $page_data) {
        $page = get_page_by_path($slug);
        if (!$page) {
            wp_insert_post(array(
                'post_title' => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $slug
            ));
        }
    }
}

register_deactivation_hook(__FILE__, 'tmdb_plugin_deactivate');
function tmdb_plugin_deactivate() {
    delete_transient('tmdb_genres_cache');
    flush_rewrite_rules();
}

add_action('wp_loaded', 'tmdb_ensure_table_exists');
function tmdb_ensure_table_exists() {
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    
    if (!$table_exists && function_exists('tmdb_create_wishlist_table')) {
        tmdb_create_wishlist_table();
    }
}

add_action('admin_notices', 'tmdb_check_table_exists');
function tmdb_check_table_exists() {
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    
    if (!$table_exists && current_user_can('administrator')) {
        echo '<div class="notice notice-error"><p><strong>TMDb Plugin:</strong> Wishlist table is missing. <a href="' . admin_url('admin.php?page=tmdb-settings') . '">Click here to go to settings and create it</a></p></div>';
    }
}

add_action('admin_bar_menu', 'tmdb_admin_bar_links', 100);
function tmdb_admin_bar_links($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    
    $wp_admin_bar->add_menu(array(
        'id' => 'tmdb-links',
        'title' => 'TMDb Pages',
        'href' => home_url('/all-movies')
    ));
    
    $wp_admin_bar->add_menu(array(
        'parent' => 'tmdb-links',
        'id' => 'tmdb-all-movies',
        'title' => 'All Movies',
        'href' => home_url('/all-movies')
    ));
    
    $wp_admin_bar->add_menu(array(
        'parent' => 'tmdb-links',
        'id' => 'tmdb-upcoming',
        'title' => 'Upcoming Movies',
        'href' => home_url('/upcoming-movies')
    ));
    
    $wp_admin_bar->add_menu(array(
        'parent' => 'tmdb-links',
        'id' => 'tmdb-wishlist',
        'title' => 'My Wishlist',
        'href' => home_url('/my-wishlist')
    ));
}

function tmdb_plugin_enqueue_assets() {
    wp_enqueue_style(
        'tmdb-plugin-styles',
        plugin_dir_url(__FILE__) . 'assets/css/main.min.css',
        array(),
        '1.0.0'
    );
    
    wp_enqueue_style(
        'lightbox2-css',
        'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css',
        array(),
        '2.11.4'
    );
    
    wp_enqueue_script('jquery');
    
    wp_enqueue_script(
        'lightbox2-js',
        'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js',
        array('jquery'),
        '2.11.4',
        true
    );
}
add_action('wp_enqueue_scripts', 'tmdb_plugin_enqueue_assets');

?>