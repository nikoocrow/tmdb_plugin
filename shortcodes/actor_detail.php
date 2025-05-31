<?php

function tmdb_actor_detail_view() {
	if ( ! isset( $_GET['actor_id'] ) ) {
		return '<p>No actor specified.</p>';
	}

    $api_key = tmdb_get_api_key(); 

	$actor_id = intval( $_GET['actor_id'] );
	if ( ! $api_key || ! $actor_id ) {
		return '<p>Invalid request.</p>';
	}

	// 1. Get actor details
	$actor_url = "https://api.themoviedb.org/3/person/{$actor_id}?api_key={$api_key}&language=en-US";
	$actor_response = wp_remote_get( $actor_url );

	if ( is_wp_error( $actor_response ) || wp_remote_retrieve_response_code( $actor_response ) !== 200 ) {
		return '<p>Error fetching actor data.</p>';
	}

	$actor = json_decode( wp_remote_retrieve_body( $actor_response ), true );

	// 2. Get images
	$images_url = "https://api.themoviedb.org/3/person/{$actor_id}/images?api_key={$api_key}";
	$images_response = wp_remote_get( $images_url );
	$images_data = json_decode( wp_remote_retrieve_body( $images_response ), true );
	$images = array_slice( $images_data['profiles'] ?? [], 0, 10 );

	// 3. Get movie credits
	$credits_url = "https://api.themoviedb.org/3/person/{$actor_id}/movie_credits?api_key={$api_key}&language=en-US";
	$credits_response = wp_remote_get( $credits_url );
	$credits_data = json_decode( wp_remote_retrieve_body( $credits_response ), true );

	$movies = $credits_data['cast'] ?? [];
	usort($movies, function($a, $b) {
		return strtotime($b['release_date'] ?? '0') - strtotime($a['release_date'] ?? '0');
	});

    $movies = array_slice($movies, 0, 20);


	// Render actor info
	$profile = $actor['profile_path'] ? esc_url( 'https://image.tmdb.org/t/p/w300' . $actor['profile_path'] ) : '';
	$name = esc_html( $actor['name'] );
	$birthday = esc_html( $actor['birthday'] ?? 'N/A' );
	$place_of_birth = esc_html( $actor['place_of_birth'] ?? 'N/A' );
	$deathday = esc_html( $actor['deathday'] ?? '' );
	$website = esc_url( $actor['homepage'] ?? '' );
	$popularity = esc_html( $actor['popularity'] ?? 'N/A' );
	$bio = esc_html( $actor['biography'] ?? 'Biography not available.' );

	$output = "<div style='display:flex;gap:20px;flex-wrap:wrap;'>
		<div><img src='{$profile}' style='border-radius:8px;max-width:300px;'></div>
		<div>
			<h2>{$name}</h2>
			<p><strong>Birthday:</strong> {$birthday}</p>
			<p><strong>Place of Birth:</strong> {$place_of_birth}</p>";
	if ( $deathday ) {
		$output .= "<p><strong>Day of Death:</strong> {$deathday}</p>";
	}
	if ( $website ) {
		$output .= "<p><strong>Website:</strong> <a href='{$website}' target='_blank'>{$website}</a></p>";
	}
	$output .= "<p><strong>Popularity:</strong> {$popularity}</p>
			<p><strong>Biography:</strong><br>{$bio}</p>
		</div>
	</div>";

	// Gallery
	if ( $images ) {
		$output .= "<h3>Gallery</h3><div style='display:flex;gap:10px;flex-wrap:wrap;'>";
		foreach ( $images as $img ) {
			$img_url = esc_url( 'https://image.tmdb.org/t/p/w300' . $img['file_path'] );
			$output .= "<img src='{$img_url}' style='max-width:150px;border-radius:6px;'>";
		}
		$output .= "</div>";
	}

	// Movies
	if ( $movies ) {
		$output .= "<h3>Movies</h3><div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;'>";
		foreach ( $movies as $movie ) {
			$poster = $movie['poster_path'] ? esc_url( 'https://image.tmdb.org/t/p/w300' . $movie['poster_path'] ) : '';
			$title = esc_html( $movie['title'] ?? 'Untitled' );
			$release = esc_html( $movie['release_date'] ?? 'N/A' );
			$character = esc_html( $movie['character'] ?? 'N/A' );

			$output .= "<div style='background:#fafafa;border-radius:8px;padding:10px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.07);'>";
			if ( $poster ) {
				$output .= "<img src='{$poster}' alt='{$title}' style='max-width:100%;border-radius:6px;margin-bottom:10px'>";
			}
			$output .= "<strong>{$title}</strong><br><em>{$release}</em><p style='font-size:13px'>as {$character}</p></div>";
		}
		$output .= "</div>";
	}

	return $output;
}
add_shortcode( 'actor_detail', 'tmdb_actor_detail_view' );






?>