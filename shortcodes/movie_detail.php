<?php
function tmdb_show_movie_detail( $atts ) {
    $api_key = tmdb_get_api_key();
    if ( empty( $api_key ) ) {
        return '<p><strong>TMDb:</strong> API key is not set.</p>';
    }
    
    $atts = shortcode_atts( array(
        'id' => '',
    ), $atts, 'movie_detail' );
    
    // If ID is {GET}, use $_GET['movie_id']
    if ( $atts['id'] === '{GET}' && isset( $_GET['movie_id'] ) ) {
        $movie_id = intval( $_GET['movie_id'] );
    } else {
        $movie_id = intval( $atts['id'] );
    }
    
    if ( $movie_id <= 0 ) {
        return '<p>Invalid movie ID.</p>';
    }
    
    // Get movie details
    $movie_url = "https://api.themoviedb.org/3/movie/{$movie_id}?api_key={$api_key}&language=en-US&append_to_response=videos,credits,reviews,similar,alternative_titles";
    $movie_response = wp_remote_get( $movie_url );
    
    if ( is_wp_error( $movie_response ) ) {
        return '<p>Error connecting to TMDb: ' . esc_html( $movie_response->get_error_message() ) . '</p>';
    }
    
    if ( wp_remote_retrieve_response_code( $movie_response ) !== 200 ) {
        return '<p>TMDb returned an unexpected response.</p>';
    }
    
    $movie_data = json_decode( wp_remote_retrieve_body( $movie_response ), true );
    
    if ( empty( $movie_data['title'] ) ) {
        return '<p>Movie not found.</p>';
    }
    
    // Extract movie data
    $title = esc_html( $movie_data['title'] );
    $overview = esc_html( $movie_data['overview'] );
    $release_date = esc_html( $movie_data['release_date'] );
    $original_language = esc_html( $movie_data['original_language'] );
    $popularity = esc_html( $movie_data['popularity'] );
    $poster = $movie_data['poster_path'] ? esc_url( 'https://image.tmdb.org/t/p/w500' . $movie_data['poster_path'] ) : '';
    
    // Get genres
    $genres = array();
    if ( !empty( $movie_data['genres'] ) ) {
        foreach ( $movie_data['genres'] as $genre ) {
            $genres[] = esc_html( $genre['name'] );
        }
    }
    $genres_text = implode( ', ', $genres );
    
    // Get production companies
    $production_companies = array();
    if ( !empty( $movie_data['production_companies'] ) ) {
        foreach ( $movie_data['production_companies'] as $company ) {
            $production_companies[] = esc_html( $company['name'] );
        }
    }
    $companies_text = implode( ', ', $production_companies );
    
    // Get alternative titles
    $alternative_titles = array();
    if ( !empty( $movie_data['alternative_titles']['titles'] ) ) {
        foreach ( $movie_data['alternative_titles']['titles'] as $alt_title ) {
            $alternative_titles[] = esc_html( $alt_title['title'] ) . ' (' . esc_html( $alt_title['iso_3166_1'] ) . ')';
        }
    }
    $alt_titles_text = implode( ', ', array_slice( $alternative_titles, 0, 5 ) ); // Limit to 5
    
    // Get trailer
    $trailer_url = '';
    if ( !empty( $movie_data['videos']['results'] ) ) {
        foreach ( $movie_data['videos']['results'] as $video ) {
            if ( $video['type'] === 'Trailer' && $video['site'] === 'YouTube' ) {
                $trailer_url = esc_url( 'https://www.youtube.com/embed/' . $video['key'] );
                break;
            }
        }
    }
    
    // Get cast (first 10)
    $cast_html = '';
    if ( !empty( $movie_data['credits']['cast'] ) ) {
        $cast_html = '<div class="movie-detail__cast-list">';
        $cast_count = 0;
        foreach ( $movie_data['credits']['cast'] as $actor ) {
            if ( $cast_count >= 10 ) break;
            $actor_name = esc_html( $actor['name'] );
            $character = esc_html( $actor['character'] );
            $actor_id = intval( $actor['id'] );
            
            // Link to actor detail page (you'll need to implement this)
            $cast_html .= '<div class="movie-detail__cast-member">';
            $cast_html .= '<a href="?actor_id=' . $actor_id . '" class="movie-detail__cast-link">';
            $cast_html .= '<strong class="movie-detail__actor-name">' . $actor_name . '</strong>';
            $cast_html .= '<br><small class="movie-detail__character">as ' . $character . '</small>';
            $cast_html .= '</a></div>';
            $cast_count++;
        }
        $cast_html .= '</div>';
    }
    
    // Get reviews (first 3)
    $reviews_html = '';
    if ( !empty( $movie_data['reviews']['results'] ) ) {
        $reviews_html = '<div class="movie-detail__reviews">';
        $review_count = 0;
        foreach ( $movie_data['reviews']['results'] as $review ) {
            if ( $review_count >= 3 ) break;
            $author = esc_html( $review['author'] );
            $rating = isset( $review['author_details']['rating'] ) ? esc_html( $review['author_details']['rating'] ) : 'N/A';
            $content = esc_html( wp_trim_words( $review['content'], 50 ) );
            
            $reviews_html .= '<div class="movie-detail__review">';
            $reviews_html .= '<h5 class="movie-detail__review-header">Review by ' . $author . ' (Rating: ' . $rating . '/10)</h5>';
            $reviews_html .= '<p class="movie-detail__review-content">' . $content . '...</p>';
            $reviews_html .= '</div>';
            $review_count++;
        }
        $reviews_html .= '</div>';
    }
    
    // Get similar movies (first 6)
    $similar_html = '';
    if ( !empty( $movie_data['similar']['results'] ) ) {
        $similar_html = '<div class="movie-detail__similar-movies">';
        $similar_count = 0;
        foreach ( $movie_data['similar']['results'] as $similar_movie ) {
            if ( $similar_count >= 6 ) break;
            $similar_title = esc_html( $similar_movie['title'] );
            $similar_id = intval( $similar_movie['id'] );
            $similar_poster = $similar_movie['poster_path'] ? esc_url( 'https://image.tmdb.org/t/p/w200' . $similar_movie['poster_path'] ) : '';
            
            $similar_html .= '<div class="movie-detail__similar-movie">';
            $similar_html .= '<a href="?movie_id=' . $similar_id . '" class="movie-detail__similar-link">';
            if ( $similar_poster ) {
                $similar_html .= '<img src="' . $similar_poster . '" alt="' . $similar_title . '" class="movie-detail__similar-poster">';
            }
            $similar_html .= '<small class="movie-detail__similar-title">' . $similar_title . '</small>';
            $similar_html .= '</a></div>';
            $similar_count++;
        }
        $similar_html .= '</div>';
    }
    
    // Build output HTML
    $output = '<div class="movie-detail">';
    
    // Header section with poster and basic info
    $output .= '<div class="movie-detail__header">';
    
    if ( $poster ) {
        $output .= '<div class="movie-detail__poster-section">';
        $output .= '<img src="' . $poster . '" alt="' . $title . '" class="movie-detail__poster">';
        $output .= '</div>';
    }
    
    $output .= '<div class="movie-detail__info-section">';
    $output .= '<h1 class="movie-detail__title">' . $title . '</h1>';
    
    if ( $genres_text ) {
        $output .= '<p class="movie-detail__info-item"><strong>Genres:</strong> ' . $genres_text . '</p>';
    }
    
    $output .= '<p class="movie-detail__info-item"><strong>Release Date:</strong> ' . $release_date . '</p>';
    $output .= '<p class="movie-detail__info-item"><strong>Original Language:</strong> ' . strtoupper( $original_language ) . '</p>';
    $output .= '<p class="movie-detail__info-item"><strong>Popularity:</strong> ' . $popularity . '</p>';
    $output .= '<p class="movie-detail__overview-item"><strong>Overview:</strong> ' . $overview . '</p>';
    
    if ( $companies_text ) {
        $output .= '<p class="movie-detail__info-item"><strong>Production Companies:</strong> ' . $companies_text . '</p>';
    }
    
    if ( $alt_titles_text ) {
        $output .= '<p class="movie-detail__info-item"><strong>Alternative Titles:</strong> ' . $alt_titles_text . '</p>';
    }
    
    // WISHLIST BUTTON ADDED
    $output .= '<div class="movie-detail__wishlist-container">';
    $output .= tmdb_get_wishlist_button($movie_id, $title, $poster);
    $output .= '</div>';

   
    
    $output .= '</div>';
    
      // Trailer section
    if ( $trailer_url ) {
        $output .= '<div class="movie-detail__trailer-section">';
        $output .= '<h1 class="movie-detail__section-title">Trailer</h1>';
        $output .= '<div class="movie-detail__trailer-container">';
        $output .= '<iframe src="' . $trailer_url . '" class="movie-detail__trailer-iframe" allowfullscreen></iframe>';
        $output .= '</div></div>';
    }
    $output .= '</div>'; // Close header
    
   
        
    // Cast section
    if ( $cast_html ) {
        $output .= '<div class="movie-detail__cast-section">';
        $output .= '<h3 class="movie-detail__section-title">Cast</h3>';
        $output .= $cast_html;
        $output .= '</div>';
    }
    
    // Reviews section
    if ( $reviews_html ) {
        $output .= '<div class="movie-detail__reviews-section">';
        $output .= '<h3 class="movie-detail__section-title">Reviews</h3>';
        $output .= $reviews_html;
        $output .= '</div>';
    }
    
    // Similar movies section
    if ( $similar_html ) {
        $output .= '<div class="movie-detail__similar-section">';
        $output .= '<h3 class="movie-detail__section-title">Similar Movies</h3>';
        $output .= $similar_html;
        $output .= '</div>';
    }
    
    $output .= '</div>'; // Close movie-detail
    
    return $output;
}
add_shortcode( 'movie_detail', 'tmdb_show_movie_detail' );
?>