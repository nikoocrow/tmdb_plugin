<?php

function tmdb_all_movies_filtered_shortcode() {
	$api_key = tmdb_get_api_key();
	if ( empty( $api_key ) ) {
		return '<p><strong>TMDb:</strong> API key is not set.</p>';
	}

	// Read filters from querystring - use 'paged' instead of 'page'
	$title = isset($_GET['title']) ? sanitize_text_field($_GET['title']) : 'Predator';
	$year = isset($_GET['year']) && !empty($_GET['year']) ? intval($_GET['year']) : '';
	$genre = isset($_GET['genre']) ? sanitize_text_field($_GET['genre']) : '';
	$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

	// Validate year - must be in a reasonable range
	if ($year && ($year < 1900 || $year > 2030)) {
		$year = '';
	}

	// Improved logic to handle mixed searches
	if ( $title ) {
		// If there's a title, first search by title
		$search_url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&query=" . urlencode( $title ) . "&page={$page}&language=en-US&include_adult=false";
		
		// Only add year parameter if it has a valid value
		if ( $year && $year >= 1900 && $year <= 2030 ) {
			$search_url .= "&year={$year}";
		}
		
		$response = wp_remote_get( $search_url );
		$search_type = "Title search";
		
		// If title search returns no results AND there are additional filters,
		// we try a broader search
		$initial_response = $response;
	} else {
		// Without title, use discover
		$discover_url = "https://api.themoviedb.org/3/discover/movie?api_key={$api_key}&language=en-US&include_adult=false&sort_by=original_title.asc&page={$page}";
		
		// Only add year parameter if it has a valid value
		if ( $year && $year >= 1900 && $year <= 2030 ) {
			$discover_url .= "&primary_release_year={$year}";
		}
		if ( $genre ) {
			$discover_url .= "&with_genres={$genre}";
		}
		$response = wp_remote_get( $discover_url );
		$search_type = "Movie discovery";
	}

	if ( is_wp_error( $response ) ) {
		return '<div class="tmdb-error">
			<h4>Connection Error</h4>
			<p>Could not connect to TMDb API: ' . esc_html( $response->get_error_message() ) . '</p>
		</div>';
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		return '<div class="tmdb-error">
			<h4>API Error</h4>
			<p>TMDb API responded with code: ' . esc_html( $response_code ) . '</p>
			<p>Check your API key and search parameters.</p>
		</div>';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	
	// If we searched by title and got no results, try a more flexible search
	$alternative_search_used = false;
	if ( $title && ( empty( $data['results'] ) || $data['total_results'] == 0 ) ) {
		// Try search without year to see if the year is the problem
		$flexible_search_url = "https://api.themoviedb.org/3/search/movie?api_key={$api_key}&query=" . urlencode( $title ) . "&page={$page}&language=en-US&include_adult=false";
		$flexible_response = wp_remote_get( $flexible_search_url );
		
		if ( ! is_wp_error( $flexible_response ) && wp_remote_retrieve_response_code( $flexible_response ) === 200 ) {
			$flexible_data = json_decode( wp_remote_retrieve_body( $flexible_response ), true );
			
			// If flexible search has results, use it and filter manually
			if ( ! empty( $flexible_data['results'] ) ) {
				$data = $flexible_data;
				$alternative_search_used = true;
				$search_type = "Flexible title search (manual filtering)";
				
				// Filter results manually if filters were specified
				if ( $year || $genre ) {
					$filtered_results = [];
					foreach ( $data['results'] as $movie ) {
						$match = true;
						
						// Year filter - improved validation
						if ( $year && $year >= 1900 && $year <= 2030 && ! empty( $movie['release_date'] ) ) {
							$movie_year = intval( substr( $movie['release_date'], 0, 4 ) );
							if ( $movie_year !== $year ) {
								$match = false;
							}
						} elseif ( $year && $year >= 1900 && $year <= 2030 && empty( $movie['release_date'] ) ) {
							// If year was specified but movie has no release date, exclude
							$match = false;
						}
						
						// Genre filter
						if ( $genre && $match && ! empty( $movie['genre_ids'] ) ) {
							if ( ! in_array( intval($genre), $movie['genre_ids'] ) ) {
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
	
	// Debug info (you can comment this section in production)
	/*$debug_info = '<div class="tmdb-debug">
		<strong>Search Information:</strong><br>
		Type: ' . esc_html( $search_type ) . '<br>
		' . ( $alternative_search_used ? '<span class="alternative-search-warning">⚠️ Alternative flexible search was used</span><br>' : '' ) . '
		Applied filters: Title="' . esc_html( $title ) . '", Year=' . esc_html( $year ? $year : 'Any' ) . ', Genre=' . esc_html( $genre ) . '<br>
		Page: ' . esc_html( $page ) . '<br>
		Total results: ' . ( isset( $data['total_results'] ) ? esc_html( $data['total_results'] ) : 'N/A' ) . '<br>
		Total pages: ' . ( isset( $data['total_pages'] ) ? esc_html( $data['total_pages'] ) : 'N/A' ) . '
	</div>';*/

	// Improved "no results" message with more context
	if ( empty( $data['results'] ) || $data['total_results'] == 0 ) {
		$no_results_message = '<div class="tmdb-no-results" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
			<h3 style="color: #d32f2f; margin-bottom: 15px;">📽️ No movies found</h3>
			<p style="margin-bottom: 15px;"><strong>No movies match the search criteria:</strong></p>
			<ul class="search-criteria" style="background: white; padding: 15px; border-left: 4px solid #2196f3; margin: 15px 0;">
				' . ( $title ? '<li><strong>Title:</strong> "' . esc_html( $title ) . '"</li>' : '' ) . '
				' . ( $year ? '<li><strong>Year:</strong> ' . esc_html( $year ) . '</li>' : '' ) . '
				' . ( $genre ? '<li><strong>Genre:</strong> ' . esc_html( tmdb_get_genre_name( $genre ) ) . '</li>' : '' ) . '
			</ul>';

		// Improved suggestions
		if ($title || $year || $genre) {
			$no_results_message .= '<p style="margin: 15px 0;"><strong>💡 Suggestions to improve your search:</strong></p>
			<ul class="suggestions-list" style="margin-left: 20px;">';
			
			if ($title) {
				$no_results_message .= '<li>• <a href="?title=' . urlencode( $title ) . '" class="suggestion-link" style="color: #2196f3; text-decoration: none;">Search only by title: "' . esc_html( $title ) . '"</a></li>';
			}
			
			if ($year) {
				$no_results_message .= '<li>• <a href="?year=' . esc_html( $year ) . '" class="suggestion-link" style="color: #2196f3; text-decoration: none;">View all movies from ' . esc_html( $year ) . '</a></li>';
			}
			
			if ($genre) {
				$no_results_message .= '<li>• <a href="?genre=' . esc_html( $genre ) . '" class="suggestion-link" style="color: #2196f3; text-decoration: none;">View all ' . esc_html( tmdb_get_genre_name( $genre ) ) . ' movies</a></li>';
			}
			
			$no_results_message .= '<li>• Check the spelling of the title</li>
				<li>• Try with less specific criteria</li>
				<li>• <a href="?" style="color: #ff5722; text-decoration: none;">Clear all filters</a></li>
			</ul>';
		} else {
			$no_results_message .= '<p style="color: #666;">Try adding some filters to find movies.</p>';
		}
		
		$no_results_message .= '</div>';
		
		return $debug_info . $no_results_message . tmdb_render_filter_form( $title, $year, $genre );
	}

	$total_pages = $data['total_pages'] ?? 1;

	// Filter form
	$output = tmdb_render_filter_form( $title, $year, $genre );
	
	// Show results information
	$output .= '<div class="tmdb-results-info">
		<strong>Results found:</strong> ' . esc_html( $data['total_results'] ?? 0 ) . ' movies
		' . ( $total_pages > 1 ? ' (' . esc_html( $total_pages ) . ' pages)' : '' ) . '
	</div>';

	// Debug info (comment in production)
	//$output .= $debug_info;

	// Movie list
	$output .= '<h3 class="tmdb-results-info__title">Movie Results</h3>';
	$output .= '<div class="tmdb-movie-list">';
	

	foreach ( $data['results'] as $movie ) {
		$movie_title  = esc_html( $movie['title'] ?? 'Untitled' );
	    $poster = ! empty( $movie['poster_path'] )
                  ? esc_url( 'https://image.tmdb.org/t/p/w300' . $movie['poster_path'] )
                  : 'https://placehold.co/300x450?text=No+Image&font=roboto';

		$date   = esc_html( $movie['release_date'] ?? '' );
		$desc   = esc_html( wp_trim_words( $movie['overview'], 25 ) );
		$movie_id = intval( $movie['id'] );
		$detail_url = esc_url( home_url( '/movie-detail-page?movie_id=' . $movie_id ) );

		$output .= '<div class="tmdb-movie-list__item">';
		if ( $poster ) {
			$output .= "<a href='{$detail_url}'><img src='{$poster}' alt='{$movie_title}'></a>";
		}
		$output .= "<a href='{$detail_url}'><h3>{$movie_title}</h3></a>";
		$output .= "<em>{$date}</em>";
		$output .= "<p>{$desc}</p>";
		
		// WISHLIST BUTTON ADDED
		$output .= tmdb_get_wishlist_button($movie_id, $movie_title, $poster);
		
		$output .= '</div>';
	}
	$output .= '</div>';

	// Pagination with limit of 20 pages
	$max_pages = 20;
	$total_pages = min($total_pages, $max_pages);
	
	if ( $total_pages > 1 ) {
		$output .= tmdb_render_pagination( $page, $total_pages );
	}

	return $output;
}

function tmdb_render_filter_form( $title, $year, $genre ) {
	$output = '<div id="tmdb-search-container-movies" class="search-container-movies">';
	$output .= '<form method="get" class="tmdb-search-form-movies">';
	$output .= '<div class="tmdb-search-form-movies__movies">';
	$output .= '<input type="text" name="title" placeholder="Movie title" value="' . esc_attr( $title ) . '" class="tmdb-search-input-movies" />';
	$output .= '<div class="tmdb-search-form-movies__options-movies">';
	
	// Improved year validation
	$year_value = ($year && $year >= 1900 && $year <= 2030) ? $year : '';
	$output .= '<input type="number" name="year" placeholder="Year (e.g. 2024)" min="1900" max="2030" value="' . esc_attr( $year_value ) . '" />';

	// Genre select
	$output .= '<div class="tmdb-search-form-movies__movies-filter">';
	$output .= '<select name="genre" class="tmdb-search-input-movies">';
	$output .= '<option value="">All genres</option>';
	foreach ( tmdb_get_genres() as $id => $name ) {
		$selected = selected( $genre, $id, false );
		$output .= "<option value='{$id}' {$selected}>{$name}</option>";
	}
	$output .= '</select>';
	$output .= '</div>'; // close tmdb-search-form-movies__movies-filter

	$output .= '</div>'; // close tmdb-search-form-movies__options-movies

	// Buttons
	$output .= '<button type="submit">Filter</button>';
	$output .= '<a href="?" class="clear-button">Clear</a>';

	$output .= '</div>'; // close tmdb-search-form-movies__movies
	$output .= '</form>';
	$output .= '</div>'; // close search-container-movies
	return $output;
}

// Rest of functions unchanged...
function tmdb_render_pagination( $page, $total_pages ) {
    $output = '<div class="tmdb-pagination">';
    
    // Generate current query without 'paged'
    $current_url = home_url( add_query_arg( null, null ) );
    $current_query_args = $_GET;
    unset($current_query_args['paged']);
    
    // Helper function to generate URLs
    $build_url = function($page_num) use ($current_query_args, $current_url) {
        if ($page_num == 1) {
            return esc_url( add_query_arg( $current_query_args, $current_url ) );
        }
        return esc_url( add_query_arg( array_merge( $current_query_args, ['paged' => $page_num] ), $current_url ) );
    };
    
    // Link to first page
    if ($page > 3) {
        $first_url = $build_url(1);
        $output .= "<a href='{$first_url}' class='tmdb-pagination__link tmdb-pagination__nav'>1</a>";
        $output .= " <span class='tmdb-pagination__dots'>...</span> ";
    }
    
    // Link to previous page
    if ($page > 1) {
        $prev_url = $build_url($page - 1);
        $output .= "<a href='{$prev_url}' class='tmdb-pagination__link tmdb-pagination__nav'>‹ Previous</a> ";
    }
    
    // Show pages around current
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) {
            $output .= "<span class='tmdb-pagination__current'>{$i}</span> ";
        } else {
            $page_url = $build_url($i);
            $output .= "<a href='{$page_url}' class='tmdb-pagination__link'>{$i}</a> ";
        }
    }
    
    // Link to next page
    if ($page < $total_pages) {
        $next_url = $build_url($page + 1);
        $output .= " <a href='{$next_url}' class='tmdb-pagination__link tmdb-pagination__nav'>Next ›</a>";
    }
    
    // Link to last page
    if ($page < $total_pages - 2) {
        $output .= " <span class='tmdb-pagination__dots'>...</span> ";
        $last_url = $build_url($total_pages);
        $output .= "<a href='{$last_url}' class='tmdb-pagination__link tmdb-pagination__nav'>{$total_pages}</a>";
    }
    
    // Current page information
    $output .= "<div class='tmdb-pagination__info'>Page {$page} of {$total_pages} (maximum 20 pages)</div>";
    $output .= '</div>';
    
    return $output;
}

function tmdb_get_genre_name( $genre_id ) {
    $genres = tmdb_get_genres();
    return isset( $genres[ $genre_id ] ) ? $genres[ $genre_id ] : 'Unknown genre';
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