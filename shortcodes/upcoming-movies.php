<?php

/* ---------------------------------------------------
 * 1) SHORTCODE: [upcoming_movies number=10]
 * -------------------------------------------------- */

add_shortcode( 'upcoming_movies', 'tmdb_show_upcoming_movies' );

function tmdb_show_upcoming_movies( $atts ) {
	 $api_key = tmdb_get_api_key(); 

	if ( empty( $api_key ) ) {
		return '<p><strong>TMDb:</strong> API key is not set. Please go to <em>TMDb → Settings</em> to enter it.</p>';
	}

	$atts = shortcode_atts( array(
		'number' => 10,
	), $atts, 'upcoming_movies' );

	$number = max( 1, min( 50, intval( $atts['number'] ) ) );

	$language = 'en-US';
	$url      = "https://api.themoviedb.org/3/movie/upcoming?api_key={$api_key}&language={$language}&page=1";

	$response = wp_remote_get( $url );

	if ( is_wp_error( $response ) ) {
		return '<p>Error connecting to TMDb: ' . esc_html( $response->get_error_message() ) . '</p>';
	}

	if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return '<p>TMDb returned an unexpected HTTP response.</p>';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $data['results'] ) ) {
		return '<p>No upcoming movies found.</p>';
	}

	$movies = array_slice( $data['results'], 0, $number );

	$output = '<div class="tmdb-movie-list">';

	foreach ( $movies as $movie ) {
		if ( empty( $movie['title'] ) ) {
			continue;
		}

		$title       = esc_html( $movie['title'] );
		$slug        = sanitize_title( $title );
		$releaseDate = esc_html( $movie['release_date'] );
		$overview    = esc_html( wp_trim_words( $movie['overview'], 30 ) );
		$poster      = $movie['poster_path'] ? esc_url( 'https://image.tmdb.org/t/p/w300' . $movie['poster_path'] ) : '';
		$movie_id = intval( $movie['id'] );
        $detail_url = esc_url( home_url( '/movie-detail-page?movie_id=' . $movie_id ) );

		$output .= '<div class="tmdb-movie-list__item">';
		if ( $poster ) {
			$output .= "<a href='{$detail_url}'><img src='{$poster}' alt='{$title}'></a>";
		}
		$output .= "<a href='{$detail_url}'><h3>{$title}</h3></a><br>";
		$output .= "<em>{$releaseDate}</em><p>{$overview}</p>";
		
		// WISHLIST 
		$output .= tmdb_get_wishlist_button($movie_id, $title, $poster);
		
		$output .= '</div>';
	}

	$output .= '</div>';

	return $output;
}
?>