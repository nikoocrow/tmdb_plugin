<?php

function tmdb_actor_list_shortcode() {
    // API Key directa (reemplaza con tu función tmdb_get_api_key() si la tienes)
    
    $api_key = tmdb_get_api_key(); 
    if ( empty($api_key) ) {
        return '<p>API key no configurada.</p>';
    }

    // Parámetros de filtros
    $filter_name = isset($_GET['filter_name']) ? sanitize_text_field($_GET['filter_name']) : '';
    $filter_movie = isset($_GET['filter_movie']) ? sanitize_text_field($_GET['filter_movie']) : '';

    // Parámetro página para paginación (por defecto 1)
    // Usar 'paged' en lugar de 'page' para evitar conflictos con WordPress
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    if ($current_page == 0) $current_page = 1;
    
    // Debug temporal - remover después de probar
    // error_log("Current page: " . $current_page . " - GET params: " . print_r($_GET, true));
    
    // Configuración de límites
    $items_per_page = 20;
    $max_pages = 20; // 20 páginas × 20 items = 400 resultados máximo
    $max_results = $max_pages * $items_per_page; // 400 resultados máximo

    $actors = [];
    $total_pages = 1;
    $total_results = 0;

    if ( $filter_movie ) {
        // Buscar película por nombre para obtener ID
        $search_movie_url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&query=" . urlencode($filter_movie) . "&language=en-US&page=1";
        $movie_response = wp_remote_get($search_movie_url);
        if (!is_wp_error($movie_response) && wp_remote_retrieve_response_code($movie_response) === 200) {
            $movie_data = json_decode(wp_remote_retrieve_body($movie_response), true);
            if (!empty($movie_data['results'])) {
                $movie_id = $movie_data['results'][0]['id'];
                // Obtener créditos para esa película
                $credits_url = "https://api.themoviedb.org/3/movie/{$movie_id}/credits?api_key={$api_key}&language=en-US";
                $credits_response = wp_remote_get($credits_url);
                if (!is_wp_error($credits_response) && wp_remote_retrieve_response_code($credits_response) === 200) {
                    $credits_data = json_decode(wp_remote_retrieve_body($credits_response), true);
                    if (!empty($credits_data['cast'])) {
                        // Aplicar paginación manual a los créditos
                        $all_cast = $credits_data['cast'];
                        $total_cast = count($all_cast);
                        $total_results = min($total_cast, $max_results);
                        $total_pages = ceil($total_results / $items_per_page);
                        
                        // Verificar que la página actual no exceda el total
                        if ($current_page > $total_pages) {
                            $current_page = 1;
                        }
                        
                        $offset = ($current_page - 1) * $items_per_page;
                        $actors = array_slice($all_cast, $offset, $items_per_page);
                    }
                }
            }
        }
    } else {
        // Verificar que la página solicitada no exceda nuestro límite
        if ($current_page > $max_pages) {
            $current_page = 1;
        }
        
        if ( $filter_name ) {
            // URL corregida para búsqueda de personas
            $search_url = "https://api.themoviedb.org/3/search/person?api_key={$api_key}&query=" . urlencode($filter_name) . "&language=en-US&page={$current_page}";
        } else {
            // URL corregida para personas populares (esta es la URL que proporcionaste)
            $search_url = "https://api.themoviedb.org/3/person/popular?api_key={$api_key}&language=en-US&page={$current_page}";
        }

        $search_response = wp_remote_get($search_url);
        if (!is_wp_error($search_response) && wp_remote_retrieve_response_code($search_response) === 200) {
            $search_data = json_decode(wp_remote_retrieve_body($search_response), true);
            if (!empty($search_data['results'])) {
                $actors = $search_data['results'];
                
                // Limitar el total de páginas y resultados
                $api_total_pages = isset($search_data['total_pages']) ? intval($search_data['total_pages']) : 1;
                $api_total_results = isset($search_data['total_results']) ? intval($search_data['total_results']) : 0;
                
                $total_pages = min($api_total_pages, $max_pages);
                $total_results = min($api_total_results, $max_results);
            }
        }
    }

    // Si no hay actores y estamos en una página > 1, redirigir a página 1
    if (empty($actors) && $current_page > 1) {
        $redirect_url = tmdb_build_pagination_url(1, $filter_name, $filter_movie);
        echo "<script>window.location.href = '{$redirect_url}';</script>";
        return '<p>Redirigiendo...</p>';
    }

    // Ordenar actores por nombre (solo si no es filtro por película)
    if ( !$filter_movie && !empty($actors) ) {
        usort($actors, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }

    // Formulario filtros
$output = "<div id='tmdb-search-container-actors' class='search-container-actors'>
  <div class='tmdb-search-form-actors'>
    <form method='get' class='tmdb-search-form-actors__actors'>
     <label class='tmdb-search-form-actors__options-actors'>
    <input class='tmdb-search-input-actors' type='text' name='filter_name' placeholder='filter by name' value='". esc_attr($filter_name) ."' />
    </label>
    <label class='tmdb-search-form-actors__options-actors'>
    <input class='tmdb-search-input-actors' type='text' name='filter_movie' placeholder='filter by movie' value='". esc_attr($filter_movie) ."' />
    </label>
    <div class='tmdb-search-form-actors__actors-filter'>
    <button type='submit'>Filtrar</button>
    " . (!empty($filter_name) || !empty($filter_movie) ? "<a href='". tmdb_build_pagination_url(1, '', '') ."'>Limpiar filtros</a>" : "") . "
    </div>
    </form>
    </div>
 </div>";

    if (empty($actors)) {
        $output .= '<p>No actors were found..</p>';
        return $output;
    }

    // Información de resultados
    $start_result = (($current_page - 1) * $items_per_page) + 1;
    $end_result = min($current_page * $items_per_page, $total_results);
    $output .= "<div class='tmdb-results-info'>
        Showing {$start_result}-{$end_result} of " . number_format($total_results) . " results (maximum 400)
    </div>";

    // Mostrar actores
    $output .= '<div class="tmdb-actors">';
    foreach ($actors as $actor) {
        $name = esc_html($actor['name'] ?? 'Nombre no disponible');
        $profile = !empty($actor['profile_path']) ? esc_url('https://image.tmdb.org/t/p/w300' . $actor['profile_path']) : '';
        $actor_id = intval($actor['id'] ?? 0);
        $actor_url = site_url("/actor-detail?actor_id={$actor_id}");

        $output .= '<div class="tmdb-actors__item">';
        
        if ($profile) {
            $output .= "<a href='{$actor_url}' class='tmdb-actors__link'>";
            $output .= "<img src='{$profile}' alt='{$name}' class='tmdb-actors__image'>";
            $output .= "</a>";
        } else {
            $output .= "<div class='tmdb-actors__image tmdb-actors__image--placeholder'>No Photo</div>";
        }
        
        $output .= "<div class='tmdb-actors__info'>";
        $output .= "<a href='{$actor_url}' class='tmdb-actors__name'>{$name}</a>";
        $output .= "</div>";
        $output .= '</div>';
    }
    $output .= '</div>';

    // Mostrar paginación si hay más de 1 página
    if ( $total_pages > 1 ) {
        $output .= '<div class="tmdb-pagination">';

        // Link a primera página
        if ($current_page > 3) {
            $first_url = tmdb_build_pagination_url(1, $filter_name, $filter_movie);
            $output .= "<a href='{$first_url}' class='tmdb-pagination__link'>1</a>";
            $output .= "<span class='tmdb-pagination__ellipsis'>...</span>";
        }

        // Link a página anterior
        if ($current_page > 1) {
            $prev_url = tmdb_build_pagination_url($current_page - 1, $filter_name, $filter_movie);
            $output .= "<a href='{$prev_url}' class='tmdb-pagination__nav tmdb-pagination__nav--prev'>‹ Back</a>";
        }

        // Mostrar páginas alrededor de la actual
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                $output .= "<span class='tmdb-pagination__current'>{$i}</span>";
            } else {
                $page_url = tmdb_build_pagination_url($i, $filter_name, $filter_movie);
                $output .= "<a href='{$page_url}' class='tmdb-pagination__link'>{$i}</a>";
            }
        }

        // Link a página siguiente
        if ($current_page < $total_pages) {
            $next_url = tmdb_build_pagination_url($current_page + 1, $filter_name, $filter_movie);
            $output .= "<a href='{$next_url}' class='tmdb-pagination__nav tmdb-pagination__nav--next'>Next ›</a>";
        }

        // Link a última página
        if ($current_page < $total_pages - 2) {
            $output .= "<span class='tmdb-pagination__ellipsis'>...</span>";
            $last_url = tmdb_build_pagination_url($total_pages, $filter_name, $filter_movie);
            $output .= "<a href='{$last_url}' class='tmdb-pagination__link'>{$total_pages}</a>";
        }

        $output .= "<div class='tmdb-pagination__info'>Página {$current_page} de {$total_pages}</div>";
        $output .= '</div>';
    }

    return $output;
}

// Función para construir URLs de paginación
function tmdb_build_pagination_url($page, $filter_name = '', $filter_movie = '') {
    // Obtener la URL actual sin parámetros
    $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    
    // Construir parámetros
    $params = [];
    
    // Usar 'paged' en lugar de 'page' para evitar conflictos con WordPress
    if ($page > 1) {
        $params['paged'] = $page;
    }
    
    if (!empty($filter_name)) {
        $params['filter_name'] = $filter_name;
    }
    if (!empty($filter_movie)) {
        $params['filter_movie'] = $filter_movie;
    }
    
    // Construir URL final
    if (!empty($params)) {
        $current_url .= '?' . http_build_query($params);
    }
    
    return $current_url;
}

add_shortcode('actor_list', 'tmdb_actor_list_shortcode');