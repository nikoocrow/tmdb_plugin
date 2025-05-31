<?php

/* ---------------------------------------------------
 * SHORTCODE: [popular_actors number=10]
 * -------------------------------------------------- */




function tmdb_show_popular_actors( $atts ) {
	 $api_key = tmdb_get_api_key(); 

	if ( empty( $api_key ) ) {
		return '<p><strong>TMDb:</strong> API key is not set.</p>';
	}

	$atts = shortcode_atts( array(
		'number' => 10,
	), $atts, 'popular_actors' );

	$number = min( max( intval( $atts['number'] ), 1 ), 50 );

	$url = "https://api.themoviedb.org/3/person/popular?api_key={$api_key}&language=en-US&page=1";
	$response = wp_remote_get( $url );

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return '<p>Error fetching actors from TMDb.</p>';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['results'] ) ) {
		return '<p>No popular actors found.</p>';
	}

	$actors = array_slice( $data['results'], 0, $number );
	$output = '<div class="tmdb-actor-list" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:20px;">';

	foreach ( $actors as $actor ) {
		$name = esc_html( $actor['name'] );
		$profile = $actor['profile_path'] ? esc_url( 'https://image.tmdb.org/t/p/w300' . $actor['profile_path'] ) : '';
		$actor_id = intval( $actor['id'] );
		$actor_url = site_url( "/actor-detail?actor_id={$actor_id}" );

		$output .= '<div style="text-align:center;">';
		if ( $profile ) {
			$output .= "<a href='{$actor_url}'><img src='{$profile}' alt='{$name}' style='width:100%;border-radius:50%;max-width:150px;margin-bottom:10px;'></a>";
		}
		$output .= "<div><a href='{$actor_url}'><strong>{$name}</strong></a></div>";
		$output .= '</div>';
	}

	$output .= '</div>';
	return $output;
}
add_shortcode( 'popular_actors', 'tmdb_show_popular_actors' );








?>