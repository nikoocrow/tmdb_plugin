<?php
if ( ! function_exists( 'tmdb_get_api_key' ) ) {
    function tmdb_get_api_key() {
        $api_key = get_option( 'tmdb_api_key', '' );
        // Puedes agregar validaciones o filtros aquí si lo deseas
        return sanitize_text_field( $api_key );
    }
}

function handle_tmdb_search() {
    // Verificar nonce para seguridad
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'tmdb_search_nonce')) {
        wp_die('Security check failed');
    }

    $query = sanitize_text_field($_GET['q'] ?? '');
    $page = intval($_GET['page'] ?? 1);
    
    if ($page < 1) $page = 1;
    if (!$query) {
        echo "<p>No search query provided.</p>";
        wp_die();
    }

    // Usar la función para obtener la API key en lugar de hardcodearla
    $api_key = tmdb_get_api_key();
    if (empty($api_key)) {
        echo "<p>API key not configured. Please configure your TMDB API key.</p>";
        wp_die();
    }

    // Cache de transients para mejorar rendimiento
    $cache_key = 'tmdb_search_' . md5($query . $page);
    $cached_results = get_transient($cache_key);
    
    if ($cached_results !== false) {
        echo $cached_results;
        wp_die();
    }

    // Buscar películas y actores
    $movies_url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&language=es-ES&page={$page}&query=" . urlencode($query);
    $actors_url = "https://api.themoviedb.org/3/search/person?api_key={$api_key}&language=es-ES&page={$page}&query=" . urlencode($query);
    
    $movies = wp_remote_get($movies_url, array('timeout' => 15));
    $actors = wp_remote_get($actors_url, array('timeout' => 15));
    
    $results = [];
    $total_pages = 1;

    // Procesar películas
    if (!is_wp_error($movies)) {
        $movies_data = json_decode(wp_remote_retrieve_body($movies), true);
        if (isset($movies_data['results'])) {
            $total_pages = max($total_pages, $movies_data['total_pages']);
            foreach ($movies_data['results'] as $movie) {
                if (empty($movie['poster_path'])) continue; // Skip movies without poster
                
                $views = rand(50, 1000);
                $popularity = floatval($movie['popularity'] ?? 0);
                $release_date = $movie['release_date'] ?? date('Y-m-d');
                $days = max(1, (time() - strtotime($release_date)) / 86400);
                $score = ($views * $popularity) / $days;
                
                $results[] = [
                    'type' => 'movie',
                    'title' => $movie['title'] ?? 'Unknown Title',
                    'snippet' => wp_trim_words($movie['overview'] ?? 'No description available.', 30),
                    'image' => "https://image.tmdb.org/t/p/w200" . $movie['poster_path'],
                    'link' => "https://www.themoviedb.org/movie/" . $movie['id'],
                    'score' => $score,
                    'release_date' => $release_date,
                    'rating' => $movie['vote_average'] ?? 0,
                ];
            }
        }
    }

    // Procesar actores
    if (!is_wp_error($actors)) {
        $actors_data = json_decode(wp_remote_retrieve_body($actors), true);
        if (isset($actors_data['results'])) {
            $total_pages = max($total_pages, $actors_data['total_pages']);
            foreach ($actors_data['results'] as $actor) {
                if (empty($actor['profile_path'])) continue; // Skip actors without photo
                
                $views = rand(50, 1000);
                $popularity = floatval($actor['popularity'] ?? 0);
                $known_for_date = '';
                $known_for_titles = [];
                
                if (isset($actor['known_for']) && is_array($actor['known_for'])) {
                    foreach ($actor['known_for'] as $work) {
                        if (isset($work['title'])) {
                            $known_for_titles[] = $work['title'];
                            if (empty($known_for_date) && isset($work['release_date'])) {
                                $known_for_date = $work['release_date'];
                            }
                        } elseif (isset($work['name'])) {
                            $known_for_titles[] = $work['name'];
                            if (empty($known_for_date) && isset($work['first_air_date'])) {
                                $known_for_date = $work['first_air_date'];
                            }
                        }
                    }
                }
                
                if (empty($known_for_date)) $known_for_date = date('Y-m-d');
                $days = max(1, (time() - strtotime($known_for_date)) / 86400);
                $score = ($views * $popularity) / $days;
                
                $results[] = [
                    'type' => 'actor',
                    'title' => $actor['name'] ?? 'Unknown Actor',
                    'snippet' => 'Known for: ' . (empty($known_for_titles) ? 'Various works' : implode(', ', array_slice($known_for_titles, 0, 3))),
                    'image' => "https://image.tmdb.org/t/p/w200" . $actor['profile_path'],
                    'link' => "https://www.themoviedb.org/person/" . $actor['id'],
                    'score' => $score,
                    'popularity' => $popularity,
                ];
            }
        }
    }

    // Verificar si hay resultados
    if (empty($results)) {
        $output = "<p>No results found for '" . esc_html($query) . "'</p>";
        set_transient($cache_key, $output, 300); // Cache por 5 minutos
        echo $output;
        wp_die();
    }

    // Ordenar por score
    usort($results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // Generar HTML de resultados
    ob_start();
    ?>
    <div class="tmdb-results-container">
        <div class="tmdb-results-info">
            <p>Found <?php echo count($results); ?> results for "<?php echo esc_html($query); ?>" (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)</p>
        </div>
        
        <?php foreach ($results as $r): ?>
        <div class="tmdb-result-item">
            <div class="tmdb-result-image">
                <img src="<?php echo esc_url($r['image']); ?>" 
                     alt="<?php echo esc_attr($r['title']); ?>" 
                     width="100" 
                     height="150"
                     style="border-radius:4px; object-fit:cover;"
                     loading="lazy">
            </div>
            <div class="tmdb-result-content">
                <h3>
                    <?php echo esc_html($r['title']); ?>
                    <span class="tmdb-result-type">
                        <?php echo ucfirst($r['type']); ?>
                    </span>
                </h3>
                <p> <?php echo esc_html($r['snippet']); ?></p>
                
                <?php if ($r['type'] === 'movie' && isset($r['rating']) && $r['rating'] > 0): ?>
                <p class="rating-info">
                    <strong>Rating:</strong> <?php echo number_format($r['rating'], 1); ?>/10 ⭐
                    <?php if (isset($r['release_date'])): ?>
                    | <strong>Release:</strong> <?php echo date('Y', strtotime($r['release_date'])); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                
                <a href="<?php echo esc_url($r['link']); ?>" 
                   target="_blank" 
                   rel="noopener">
                    More info about this <?php echo esc_html($r['type']); ?> 
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div id="tmdb-pagination">
            <?php if ($page > 1): ?>
            <button class="tmdb-nav-btn" onclick="changePage(<?php echo $page - 1; ?>)" 
                Previous
            </button>
            <?php endif; ?>
            
            <span class="page">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            
            <?php if ($page < $total_pages): ?>
            <button class="tmdb-nav-btn" onclick="changePage(<?php echo $page + 1; ?>)" >
                Next 
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    
    $output = ob_get_clean();
    
    // Cache el resultado por 5 minutos
    set_transient($cache_key, $output, 300);
    
    echo $output;
    wp_die();
}

// Registrar la acción AJAX
add_action('wp_ajax_search_tmdb', 'handle_tmdb_search');
add_action('wp_ajax_nopriv_search_tmdb', 'handle_tmdb_search');