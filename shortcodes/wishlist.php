<?php
// REMOVER el hook de activación de aquí - debe estar en el archivo principal
// register_activation_hook(__FILE__, 'tmdb_create_wishlist_table'); // ELIMINAR ESTA LÍNEA

// Función para crear tabla wishlist (será llamada desde el archivo principal)
function tmdb_create_wishlist_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        movie_id int(11) NOT NULL,
        movie_title varchar(255) NOT NULL,
        movie_poster varchar(500),
        date_added datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_movie (user_id, movie_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// AJAX: Agregar/quitar de wishlist
add_action('wp_ajax_tmdb_wishlist_toggle', 'tmdb_wishlist_toggle');
add_action('wp_ajax_nopriv_tmdb_wishlist_toggle', 'tmdb_wishlist_toggle_nopriv');

function tmdb_wishlist_toggle() {
    // Verificar nonce para seguridad
    if (!wp_verify_nonce($_POST['nonce'], 'tmdb_wishlist_nonce')) {
        wp_die(json_encode(['success' => false, 'message' => 'Security check failed']));
    }
    
    if (!is_user_logged_in()) {
        wp_die(json_encode(['success' => false, 'message' => 'Login required']));
    }
    
    $movie_id = intval($_POST['movie_id']);
    $movie_title = sanitize_text_field($_POST['movie_title']);
    $movie_poster = esc_url_raw($_POST['movie_poster']);
    $user_id = get_current_user_id();
    
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND movie_id = %d",
        $user_id, $movie_id
    ));
    
    if ($exists) {
        $wpdb->delete($table, ['user_id' => $user_id, 'movie_id' => $movie_id]);
        $action = 'removed';
    } else {
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'movie_id' => $movie_id,
            'movie_title' => $movie_title,
            'movie_poster' => $movie_poster
        ]);
        $action = 'added';
    }
    
    wp_die(json_encode(['success' => true, 'action' => $action]));
}

function tmdb_wishlist_toggle_nopriv() {
    wp_die(json_encode(['success' => false, 'message' => 'Please login to use wishlist']));
}

// Script AJAX
add_action('wp_footer', 'tmdb_wishlist_script');
function tmdb_wishlist_script() {
    if (!is_user_logged_in()) return;
    ?>
    <script>
    function toggleWishlist(btn, movieId, title, poster) {
        const formData = new FormData();
        formData.append('action', 'tmdb_wishlist_toggle');
        formData.append('movie_id', movieId);
        formData.append('movie_title', title);
        formData.append('movie_poster', poster);
        formData.append('nonce', '<?php echo wp_create_nonce('tmdb_wishlist_nonce'); ?>');
        
        btn.disabled = true;
        btn.textContent = '...';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.textContent = data.action === 'added' ? '❤️ Remove' : '🤍 Add to Wishlist';
                btn.classList.toggle('in-wishlist', data.action === 'added');
                
                // Mostrar mensaje de éxito
                const message = data.action === 'added' ? 'Added to wishlist!' : 'Removed from wishlist!';
                showToast(message);
            } else {
                alert(data.message);
            }
            btn.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            btn.disabled = false;
        });
    }
    
    // Función para mostrar mensajes toast
    function showToast(message) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;top:20px;right:20px;background:#4CAF50;color:white;padding:12px 20px;border-radius:4px;z-index:9999;';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    </script>
    <?php
}

// Función helper: botón wishlist
function tmdb_get_wishlist_button($movie_id, $title, $poster = '') {
    if (!is_user_logged_in()) {
        return '<div class="item__login_btn"><a  href="' . wp_login_url() . '">Login to add to wishlist</a></div>';
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $user_id = get_current_user_id();
    
    $in_wishlist = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND movie_id = %d",
        $user_id, $movie_id
    ));
    
    $class = $in_wishlist ? 'wishlist-btn in-wishlist' : 'wishlist-btn';
    $text = $in_wishlist ? '❤️ Remove' : '🤍 Add to Wishlist';
    
    return "<button class='$class' onclick='toggleWishlist(this, $movie_id, \"" . esc_js($title) . "\", \"" . esc_js($poster) . "\")'>$text</button>";
}

// DEBUG: Shortcode temporal para ver datos
add_shortcode('debug_wishlist', 'tmdb_debug_wishlist');
function tmdb_debug_wishlist() {
    if (!is_user_logged_in()) return 'Not logged in';
    
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $user_id = get_current_user_id();
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    
    if (!$table_exists) {
        return "❌ Table '$table' does not exist!";
    }
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $user_movies = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id));
    
    return "✅ Table exists | Total movies in wishlist table: $count | Your movies: $user_movies | Your user ID: $user_id";
}

// Función para forzar creación de tabla (temporal)
add_shortcode('force_create_wishlist_table', 'tmdb_force_create_table');
function tmdb_force_create_table() {
    if (!current_user_can('administrator')) {
        return 'Only administrators can use this function.';
    }
    
    tmdb_create_wishlist_table();
    
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    
    if ($table_exists) {
        return '<div style="background:#d4edda;padding:15px;border-radius:4px;color:#155724;">✅ Wishlist table created successfully!</div>';
    } else {
        return '<div style="background:#f8d7da;padding:15px;border-radius:4px;color:#721c24;">❌ Failed to create wishlist table.</div>';
    }
}

// Shortcode: Mostrar wishlist del usuario
add_shortcode('user_wishlist', 'tmdb_show_user_wishlist');
function tmdb_show_user_wishlist() {
    if (!is_user_logged_in()) {
        return '<div style="text-align:center;padding:40px;background:#f9f9f9;border-radius:8px;">
                    <h3>Please login to view your wishlist</h3>
                    <p><a href="' . wp_login_url() . '" style="background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Login</a></p>
                </div>';
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'tmdb_wishlist';
    $user_id = get_current_user_id();
    
    // Verificar que la tabla existe antes de hacer consultas
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    if (!$table_exists) {
        return '<div style="background:#f8d7da;padding:15px;border-radius:4px;color:#721c24;">❌ Wishlist table does not exist. Please contact administrator.</div>';
    }
    
    $movies = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY date_added DESC",
        $user_id
    ));
    
    if (empty($movies)) {
        return '<div style="text-align:center;padding:40px;background:#f9f9f9;border-radius:8px;">
                    <h3>Your wishlist is empty</h3>
                    <p>Start adding movies you want to watch!</p>
                    <p><a href="' . home_url('/all-movies') . '" style="background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">Browse Movies</a></p>
                </div>';
    }
    
    $output = '<div style="margin-bottom:20px;"><h2>My Wishlist (' . count($movies) . ' movies)</h2></div>';
    $output .= '<div class="wishlist-movies">';
    
    foreach ($movies as $movie) {
        $detail_url = home_url('/movie-detail-page?movie_id=' . $movie->movie_id);
        $poster = $movie->movie_poster ?: 'https://placehold.co/300x450?text=No+Image';
        
        $output .= '<div class="wishlist-movies__item">';
        $output .= "<a href='$detail_url'><img src='$poster' alt='" . esc_attr($movie->movie_title)."'></a>";
        $output .= "<a href='$detail_url'><h3>" . esc_html($movie->movie_title) . "</h3></a>";
        $output .= "<small>Added: " . date('M j, Y', strtotime($movie->date_added)) . "</small><br>";
        $output .= tmdb_get_wishlist_button($movie->movie_id, $movie->movie_title, $movie->movie_poster);
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
}
?>