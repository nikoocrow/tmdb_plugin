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
    // Opción 1: Desde wp_options (recomendado)
    return get_option('tmdb_api_key', '');
    
    // Opción 2: Hardcodeada (no recomendado para producción)
    // return 'tu_api_key_aqui';
}

// Agregar página de configuración en el admin
add_action('admin_menu', 'tmdb_admin_menu');
function tmdb_admin_menu() {
    add_options_page(
        'TMDb Settings',
        'TMDb Settings',
        'manage_options',
        'tmdb-settings',
        'tmdb_settings_page'
    );
}

// Página de configuración
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
        
        <!-- SECCIÓN DE DEBUG AGREGADA -->
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

// Hook de activación del plugin
register_activation_hook(__FILE__, 'tmdb_plugin_activate');
function tmdb_plugin_activate() {
    // Crear tabla de wishlist
    if (function_exists('tmdb_create_wishlist_table')) {
        tmdb_create_wishlist_table();
    }
    
    // Crear páginas necesarias
    tmdb_create_pages();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Función para crear páginas automáticamente
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
        // Verificar si la página ya existe
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

// Hook de desactivación del plugin
register_deactivation_hook(__FILE__, 'tmdb_plugin_deactivate');
function tmdb_plugin_deactivate() {
    // Limpiar transients
    delete_transient('tmdb_genres_cache');
    flush_rewrite_rules();
}

// FORZAR CREACIÓN DE TABLA SI NO EXISTE (CÓDIGO TEMPORAL)
add_action('wp_loaded', 'tmdb_ensure_table_exists');
function tmdb_ensure_table_exists() {
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    
    if (!$table_exists && function_exists('tmdb_create_wishlist_table')) {
        tmdb_create_wishlist_table();
    }
}

// Verificar si la tabla existe al cargar el admin
add_action('admin_notices', 'tmdb_check_table_exists');
function tmdb_check_table_exists() {
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    
    if (!$table_exists && current_user_can('administrator')) {
        echo '<div class="notice notice-error"><p><strong>TMDb Plugin:</strong> Wishlist table is missing. <a href="' . admin_url('options-general.php?page=tmdb-settings') . '">Click here to go to settings and create it</a></p></div>';
    }
}

// Agregar enlaces útiles en el menú de administración
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
    // Tu CSS
    wp_enqueue_style(
        'tmdb-plugin-styles',
        plugin_dir_url(__FILE__) . 'assets/css/main.min.css',
        array(),
        '1.0.0'
    );
    
    // Lightbox2 desde CDN (SIEMPRE disponible)
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