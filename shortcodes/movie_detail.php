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
        $cast_html = '<div class="cast-list">';
        $cast_count = 0;
        foreach ( $movie_data['credits']['cast'] as $actor ) {
            if ( $cast_count >= 10 ) break;
            $actor_name = esc_html( $actor['name'] );
            $character = esc_html( $actor['character'] );
            $actor_id = intval( $actor['id'] );
            
            // Link to actor detail page (you'll need to implement this)
            $cast_html .= '<div class="cast-member" style="display: inline-block; margin: 5px 10px 5px 0; padding: 5px; background: #f5f5f5; border-radius: 4px;">';
            $cast_html .= '<a href="?actor_id=' . $actor_id . '" style="text-decoration: none; color: #333;">';
            $cast_html .= '<strong>' . $actor_name . '</strong><br><small>as ' . $character . '</small>';
            $cast_html .= '</a></div>';
            $cast_count++;
        }
        $cast_html .= '</div>';
    }
    
    // Get reviews (first 3)
    $reviews_html = '';
    if ( !empty( $movie_data['reviews']['results'] ) ) {
        $reviews_html = '<div class="reviews-section">';
        $review_count = 0;
        foreach ( $movie_data['reviews']['results'] as $review ) {
            if ( $review_count >= 3 ) break;
            $author = esc_html( $review['author'] );
            $rating = isset( $review['author_details']['rating'] ) ? esc_html( $review['author_details']['rating'] ) : 'N/A';
            $content = esc_html( wp_trim_words( $review['content'], 50 ) );
            
            $reviews_html .= '<div class="review" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
            $reviews_html .= '<h5 style="margin: 0 0 5px 0;">Review by ' . $author . ' (Rating: ' . $rating . '/10)</h5>';
            $reviews_html .= '<p style="margin: 0; font-size: 14px;">' . $content . '...</p>';
            $reviews_html .= '</div>';
            $review_count++;
        }
        $reviews_html .= '</div>';
    }
    
    // Get similar movies (first 6)
    $similar_html = '';
    if ( !empty( $movie_data['similar']['results'] ) ) {
        $similar_html = '<div class="similar-movies" style="display: flex; flex-wrap: wrap; gap: 10px;">';
        $similar_count = 0;
        foreach ( $movie_data['similar']['results'] as $similar_movie ) {
            if ( $similar_count >= 6 ) break;
            $similar_title = esc_html( $similar_movie['title'] );
            $similar_id = intval( $similar_movie['id'] );
            $similar_poster = $similar_movie['poster_path'] ? esc_url( 'https://image.tmdb.org/t/p/w200' . $similar_movie['poster_path'] ) : '';
            
            $similar_html .= '<div class="similar-movie" style="flex: 0 0 150px; text-align: center;">';
            $similar_html .= '<a href="?movie_id=' . $similar_id . '" style="text-decoration: none; color: #333;">';
            if ( $similar_poster ) {
                $similar_html .= '<img src="' . $similar_poster . '" alt="' . $similar_title . '" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 5px;">';
            }
            $similar_html .= '<small>' . $similar_title . '</small>';
            $similar_html .= '</a></div>';
            $similar_count++;
        }
        $similar_html .= '</div>';
    }
    
    // Build output HTML
    $output = '<div class="movie-detail" style="max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.1); font-family: Arial, sans-serif;">';
    
    // Header section with poster and basic info
    $output .= '<div class="movie-header" style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">';
    
    if ( $poster ) {
        $output .= '<div class="poster-section" style="flex: 0 0 300px;">';
        $output .= '<img src="' . $poster . '" alt="' . $title . '" style="width: 100%; height: auto; border-radius: 6px;">';
        $output .= '</div>';
    }
    
    $output .= '<div class="info-section" style="flex: 1; min-width: 300px;">';
    $output .= '<h1 style="margin: 0 0 15px 0; color: #333;">' . $title . '</h1>';
    
    if ( $genres_text ) {
        $output .= '<p><strong>Genres:</strong> ' . $genres_text . '</p>';
    }
    
    $output .= '<p><strong>Release Date:</strong> ' . $release_date . '</p>';
    $output .= '<p><strong>Original Language:</strong> ' . strtoupper( $original_language ) . '</p>';
    $output .= '<p><strong>Popularity:</strong> ' . $popularity . '</p>';
    
    if ( $companies_text ) {
        $output .= '<p><strong>Production Companies:</strong> ' . $companies_text . '</p>';
    }
    
    if ( $alt_titles_text ) {
        $output .= '<p><strong>Alternative Titles:</strong> ' . $alt_titles_text . '</p>';
    }
    
    $output .= '</div></div>'; // Close movie-header
    
    // Trailer section
    if ( $trailer_url ) {
        $output .= '<div class="trailer-section" style="margin-bottom: 30px;">';
        $output .= '<h3 style="color: #333; margin-bottom: 15px;">Trailer</h3>';
        $output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">';
        $output .= '<iframe src="' . $trailer_url . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allowfullscreen></iframe>';
        $output .= '</div></div>';
    }
    
    // Overview section
    if ( $overview ) {
        $output .= '<div class="overview-section" style="margin-bottom: 30px;">';
        $output .= '<h3 style="color: #333; margin-bottom: 10px;">Overview</h3>';
        $output .= '<p style="line-height: 1.6; color: #555;">' . $overview . '</p>';
        $output .= '</div>';
    }
    
    // Cast section
    if ( $cast_html ) {
        $output .= '<div class="cast-section" style="margin-bottom: 30px;">';
        $output .= '<h3 style="color: #333; margin-bottom: 15px;">Cast</h3>';
        $output .= $cast_html;
        $output .= '</div>';
    }
    
    // Reviews section
    if ( $reviews_html ) {
        $output .= '<div class="reviews-section" style="margin-bottom: 30px;">';
        $output .= '<h3 style="color: #333; margin-bottom: 15px;">Reviews</h3>';
        $output .= $reviews_html;
        $output .= '</div>';
    }
    
    // Similar movies section
    if ( $similar_html ) {
        $output .= '<div class="similar-section" style="margin-bottom: 30px;">';
        $output .= '<h3 style="color: #333; margin-bottom: 15px;">Similar Movies</h3>';
        $output .= $similar_html;
        $output .= '</div>';
    }
    
    $output .= '</div>'; // Close movie-detail
    
    return $output;
}
add_shortcode( 'movie_detail', 'tmdb_show_movie_detail' );
?>