<?php

function tmdb_all_movies_filtered_shortcode() {
	$api_key = tmdb_get_api_key();
	if ( empty( $api_key ) ) {
		return '<p><strong>TMDb:</strong> API key is not set.</p>';
	}

	// Leer filtros del querystring - CAMBIO: usar 'paged' en lugar de 'page'
	$title  = isset($_GET['title']) ? sanitize_text_field($_GET['title']) : '';
	$year   = isset($_GET['year']) ? intval($_GET['year']) : '';
	$genre  = isset($_GET['genre']) ? intval($_GET['genre']) : '';
	$page   = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

	// Lógica mejorada para manejar búsquedas mixtas
	if ( $title ) {
		// Si hay título, primero buscamos por título
		$search_url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&query=" . urlencode( $title ) . "&page={$page}&language=en-US&include_adult=false";
		if ( $year ) {
			$search_url .= "&year={$year}";
		}
		$response = wp_remote_get( $search_url );
		$search_type = "Búsqueda por título";
		
		// Si la búsqueda por título no devuelve resultados Y hay filtros adicionales,
		// intentamos una búsqueda más amplia
		$initial_response = $response;
	} else {
		// Sin título, usamos discover
		$discover_url = "https://api.themoviedb.org/3/discover/movie?api_key={$api_key}&language=en-US&include_adult=false&sort_by=original_title.asc&page={$page}";
		if ( $year ) {
			$discover_url .= "&primary_release_year={$year}";
		}
		if ( $genre ) {
			$discover_url .= "&with_genres={$genre}";
		}
		$response = wp_remote_get( $discover_url );
		$search_type = "Descubrimiento de películas";
	}

	if ( is_wp_error( $response ) ) {
		return '<div class="tmdb-error" style="background:#ffebee;border-left:4px solid #f44336;padding:15px;margin:20px 0;">
			<h4>Error de conexión</h4>
			<p>No se pudo conectar con la API de TMDb: ' . esc_html( $response->get_error_message() ) . '</p>
		</div>';
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		return '<div class="tmdb-error" style="background:#ffebee;border-left:4px solid #f44336;padding:15px;margin:20px 0;">
			<h4>Error de API</h4>
			<p>La API de TMDb respondió con código: ' . esc_html( $response_code ) . '</p>
			<p>Verifica tu API key y los parámetros de búsqueda.</p>
		</div>';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	
	// Si buscamos por título y no hay resultados, intentamos una búsqueda más flexible
	$alternative_search_used = false;
	if ( $title && ( empty( $data['results'] ) || $data['total_results'] == 0 ) ) {
		// Intentar búsqueda sin año para ver si el problema es el año
		$flexible_search_url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&query=" . urlencode( $title ) . "&page={$page}&language=en-US&include_adult=false";
		$flexible_response = wp_remote_get( $flexible_search_url );
		
		if ( ! is_wp_error( $flexible_response ) && wp_remote_retrieve_response_code( $flexible_response ) === 200 ) {
			$flexible_data = json_decode( wp_remote_retrieve_body( $flexible_response ), true );
			
			// Si la búsqueda flexible tiene resultados, usarla y filtrar manualmente
			if ( ! empty( $flexible_data['results'] ) ) {
				$data = $flexible_data;
				$alternative_search_used = true;
				$search_type = "Búsqueda flexible por título (filtrado manual)";
				
				// Filtrar resultados manualmente si se especificaron filtros
				if ( $year || $genre ) {
					$filtered_results = [];
					foreach ( $data['results'] as $movie ) {
						$match = true;
						
						// Filtro por año
						if ( $year && ! empty( $movie['release_date'] ) ) {
							$movie_year = intval( substr( $movie['release_date'], 0, 4 ) );
							if ( $movie_year !== $year ) {
								$match = false;
							}
						} elseif ( $year && empty( $movie['release_date'] ) ) {
							$match = false;
						}
						
						// Filtro por género
						if ( $genre && $match && ! empty( $movie['genre_ids'] ) ) {
							if ( ! in_array( $genre, $movie['genre_ids'] ) ) {
								$match = false;
							}
						} elseif ( $genre && $match && empty( $movie['genre_ids'] ) ) {
							$match = false;
						}
						
						if ( $match ) {
							$filtered_results[] = $movie;
						}
					}
					$data['results'] = $filtered_results;
					$data['total_results'] = count( $filtered_results );
				}
			}
		}
	}
	
	// Debug info (puedes comentar esta sección en producción)
	$debug_info = '<div class="tmdb-debug" style="background:#e3f2fd;border-left:4px solid #2196f3;padding:15px;margin:20px 0;font-size:12px;">
		<strong>Información de búsqueda:</strong><br>
		Tipo: ' . esc_html( $search_type ) . '<br>
		' . ( $alternative_search_used ? '<span style="color:#ff9800;">⚠️ Se usó búsqueda alternativa más flexible</span><br>' : '' ) . '
		Filtros aplicados: Título="' . esc_html( $title ) . '", Año=' . esc_html( $year ) . ', Género=' . esc_html( $genre ) . '<br>
		Página: ' . esc_html( $page ) . '<br>
		Total de resultados: ' . ( isset( $data['total_results'] ) ? esc_html( $data['total_results'] ) : 'N/A' ) . '<br>
		Total de páginas: ' . ( isset( $data['total_pages'] ) ? esc_html( $data['total_pages'] ) : 'N/A' ) . '
	</div>';

	if ( empty( $data['results'] ) ) {
		$no_results_message = '<div class="tmdb-no-results" style="background:#fff3e0;border-left:4px solid:#ff9800;padding:20px;margin:20px 0;text-align:center;">
			<h3>📽️ No se encontraron películas</h3>
			<p>No hay películas que coincidan con los criterios de búsqueda:</p>
			<ul style="list-style:none;padding:0;">
				' . ( $title ? '<li><strong>Título:</strong> "' . esc_html( $title ) . '"</li>' : '' ) . '
				' . ( $year ? '<li><strong>Año:</strong> ' . esc_html( $year ) . '</li>' : '' ) . '
				' . ( $genre ? '<li><strong>Género:</strong> ' . esc_html( tmdb_get_genre_name( $genre ) ) . '</li>' : '' ) . '
			</ul>
			<p><strong>Sugerencias:</strong></p>
			<ul style="list-style:none;padding:0;color:#666;">
				<li>• <a href="?title=' . urlencode( $title ) . '" style="color:#0073aa;">Buscar solo por título: "' . esc_html( $title ) . '"</a></li>
				' . ( $year ? '<li>• <a href="?year=' . esc_html( $year ) . '" style="color:#0073aa;">Ver todas las películas de ' . esc_html( $year ) . '</a></li>' : '' ) . '
				' . ( $genre ? '<li>• <a href="?genre=' . esc_html( $genre ) . '" style="color:#0073aa;">Ver todas las películas de ' . esc_html( tmdb_get_genre_name( $genre ) ) . '</a></li>' : '' ) . '
				<li>• Revisa la ortografía del título (ej: "Matrix" en lugar de "Matix")</li>
				<li>• Intenta con criterios menos específicos</li>
			</ul>
		</div>';
		
		return $debug_info . $no_results_message . tmdb_render_filter_form( $title, $year, $genre );
	}

	$total_pages = $data['total_pages'] ?? 1;

	// Formulario de filtros
	$output = tmdb_render_filter_form( $title, $year, $genre );
	
	// Mostrar información de resultados
	$output .= '<div class="tmdb-results-info" style="background:#e8f5e8;border-left:4px solid:#4caf50;padding:15px;margin:20px 0;">
		<strong>Resultados encontrados:</strong> ' . esc_html( $data['total_results'] ?? 0 ) . ' películas
		' . ( $total_pages > 1 ? ' (' . esc_html( $total_pages ) . ' páginas)' : '' ) . '
	</div>';

	// Debug info (comentar en producción)
	$output .= $debug_info;

	// Listado de películas
	$output .= '<div class="tmdb-movie-list" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;">';

	foreach ( $data['results'] as $movie ) {
		$movie_title  = esc_html( $movie['title'] ?? 'Untitled' );
	    $poster = ! empty( $movie['poster_path'] )
                  ? esc_url( 'https://image.tmdb.org/t/p/w300' . $movie['poster_path'] )
                  : 'https://placehold.co/300x450?text=No+Image&font=roboto';

		$date   = esc_html( $movie['release_date'] ?? '' );
		$desc   = esc_html( wp_trim_words( $movie['overview'], 25 ) );
		$movie_id = intval( $movie['id'] );
		$detail_url = esc_url( home_url( '/movie-detail-page?movie_id=' . $movie_id ) );

		$output .= '<div style="background:#f9f9f9;padding:15px;border-radius:8px;text-align:center;">';
		if ( $poster ) {
			$output .= "<a href='{$detail_url}'><img src='{$poster}' alt='{$movie_title}' style='max-width:100%;border-radius:6px;margin-bottom:10px'></a>";
		}
		$output .= "<a href='{$detail_url}' style='text-decoration:none;color:inherit;'><h3>{$movie_title}</h3></a>";
		$output .= "<em>{$date}</em>";
		$output .= "<p style='font-size:13px'>{$desc}</p>";
		$output .= '</div>';
	}
	$output .= '</div>';

	// Paginación con límite de 20 páginas
	$max_pages = 20;
	$total_pages = min($total_pages, $max_pages);
	
	if ( $total_pages > 1 ) {
		$output .= tmdb_render_pagination( $page, $total_pages );
	}

	return $output;
}

function tmdb_render_filter_form( $title, $year, $genre ) {
	$output = '<form method="get" class="tmdb-filter-form" style="margin-bottom:20px;background:#f9f9f9;padding:20px;border-radius:8px;">';
	$output .= '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
	$output .= '<input type="text" name="title" placeholder="Título de la película" value="' . esc_attr( $title ) . '" style="padding:10px;border:1px solid #ddd;border-radius:4px;" />';
	$output .= '<input type="number" name="year" placeholder="Año" min="1900" max="2030" value="' . esc_attr( $year ) . '" style="padding:10px;border:1px solid #ddd;border-radius:4px;width:100px;" />';
	$output .= '<select name="genre" style="padding:10px;border:1px solid #ddd;border-radius:4px;">';
	$output .= '<option value="">Todos los géneros</option>';
	foreach ( tmdb_get_genres() as $id => $name ) {
		$selected = selected( $genre, $id, false );
		$output .= "<option value='{$id}' {$selected}>{$name}</option>";
	}
	$output .= '</select>';
	$output .= '<button type="submit" style="padding:10px 20px;background:#0073aa;color:white;border:none;border-radius:4px;cursor:pointer;">Filtrar</button>';
	$output .= '<a href="?" style="padding:10px 20px;background:#666;color:white;text-decoration:none;border-radius:4px;margin-left:10px;">Limpiar</a>';
	$output .= '</div>';
	$output .= '</form>';
	return $output;
}

function tmdb_render_pagination( $page, $total_pages ) {
	$output = '<div class="tmdb-pagination" style="margin-top:30px;text-align:center;padding:20px;background:#f9f9f9;border-radius:5px;">';

	// Generar query actual sin 'paged'
	$current_url = home_url( add_query_arg( null, null ) );
	$current_query_args = $_GET;
	unset($current_query_args['paged']);

	// Función helper para generar URLs
	$build_url = function($page_num) use ($current_query_args, $current_url) {
		if ($page_num == 1) {
			return esc_url( add_query_arg( $current_query_args, $current_url ) );
		}
		return esc_url( add_query_arg( array_merge( $current_query_args, ['paged' => $page_num] ), $current_url ) );
	};

	// Link a primera página
	if ($page > 3) {
		$first_url = $build_url(1);
		$output .= "<a href='{$first_url}' style='margin:0 3px; padding:8px 12px; background:#f1f1f1; color:#333; text-decoration:none; border-radius:3px;'>1</a>";
		$output .= " <span style='margin:0 3px;'>...</span> ";
	}

	// Link a página anterior
	if ($page > 1) {
		$prev_url = $build_url($page - 1);
		$output .= "<a href='{$prev_url}' style='margin:0 5px; padding:10px 15px; background:#0073aa; color:white; text-decoration:none; border-radius:5px;'>‹ Anterior</a> ";
	}

	// Mostrar páginas alrededor de la actual
	$start_page = max(1, $page - 2);
	$end_page = min($total_pages, $page + 2);

	for ($i = $start_page; $i <= $end_page; $i++) {
		if ($i == $page) {
			$output .= "<span style='margin:0 3px; padding:8px 12px; background:#0073aa; color:white; border-radius:3px; font-weight:bold;'>{$i}</span> ";
		} else {
			$page_url = $build_url($i);
			$output .= "<a href='{$page_url}' style='margin:0 3px; padding:8px 12px; background:#f1f1f1; color:#333; text-decoration:none; border-radius:3px;'>{$i}</a> ";
		}
	}

	// Link a página siguiente
	if ($page < $total_pages) {
		$next_url = $build_url($page + 1);
		$output .= " <a href='{$next_url}' style='margin:0 5px; padding:10px 15px; background:#0073aa; color:white; text-decoration:none; border-radius:5px;'>Siguiente ›</a>";
	}

	// Link a última página
	if ($page < $total_pages - 2) {
		$output .= " <span style='margin:0 3px;'>...</span> ";
		$last_url = $build_url($total_pages);
		$output .= "<a href='{$last_url}' style='margin:0 3px; padding:8px 12px; background:#f1f1f1; color:#333; text-decoration:none; border-radius:3px;'>{$total_pages}</a>";
	}

	// Información de página actual
	$output .= "<div style='margin-top:15px; font-size:14px; color:#666;'>Página {$page} de {$total_pages} (máximo 20 páginas)</div>";
	$output .= '</div>';
	
	return $output;
}

function tmdb_get_genre_name( $genre_id ) {
	$genres = tmdb_get_genres();
	return isset( $genres[ $genre_id ] ) ? $genres[ $genre_id ] : 'Género desconocido';
}

add_shortcode( 'tmdb_all_movies', 'tmdb_all_movies_filtered_shortcode' );

function tmdb_get_genres() {
	$cache_key = 'tmdb_genres_cache';
	$genres = get_transient( $cache_key );
	if ( false === $genres ) {
		$api_key = tmdb_get_api_key();
		$url = "https://api.themoviedb.org/3/genre/movie/list?api_key={$api_key}&language=en-US";
		$response = wp_remote_get( $url );
		$genres = [];   

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			foreach ( $data['genres'] as $genre ) {
				$genres[ $genre['id'] ] = $genre['name'];
			}
			set_transient( $cache_key, $genres, 12 * HOUR_IN_SECONDS );
		}
	}
	return $genres;
}