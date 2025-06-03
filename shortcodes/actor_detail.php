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
    
    $output = "<div class='actor-detail'>
        <div class='actor-detail__profile'>
            <img src='{$profile}' class='actor-detail__profile-image'>
        </div>
        <div class='actor-detail__info'>
            <h2 class='actor-detail__name'>{$name}</h2>
            <p class='actor-detail__field'><strong>Birthday:</strong> {$birthday}</p>
            <p class='actor-detail__field'><strong>Place of Birth:</strong> {$place_of_birth}</p>";
    
    if ( $deathday ) {
        $output .= "<p class='actor-detail__field'><strong>Day of Death:</strong> {$deathday}</p>";
    }
    
    if ( $website ) {
        $output .= "<p class='actor-detail__field'><strong>Website:</strong> <a href='{$website}' target='_blank' class='actor-detail__link'>{$website}</a></p>";
    }
    
    $output .= "<p class='actor-detail__field'><strong>Popularity:</strong> {$popularity}</p>
        <p class='actor-detail__field actor-detail__field--biography'><strong>Biography:</strong><br>{$bio}</p>
        </div>
    </div>";
    
    // Gallery
    if ( $images ) {
        $output .= "<h3 class='actor-gallery__title'>Photo Gallery</h3>
        <div class='actor-gallery'>";
        
        foreach ( $images as $index => $img ) {
            $img_url_thumb = esc_url( 'https://image.tmdb.org/t/p/w300' . $img['file_path'] );
            $img_url_full = esc_url( 'https://image.tmdb.org/t/p/original' . $img['file_path'] );
            
            // Título más descriptivo
            $image_title = isset($img['caption']) ? esc_attr($img['caption']) : 'Imagen ' . ($index + 1);
            
            $output .= "<a href='{$img_url_full}' 
                        data-lightbox='actor-gallery' 
                        data-title='{$image_title}'>
                        <img src='{$img_url_thumb}' 
                            class='actor-gallery__image' 
                            alt='Actor image {$index}' 
                            loading='lazy'>
                        </a>";
        }
        
        $output .= "</div>";
    }
    
    
    // Movies
    if ( $movies ) {
        $output .= "<h3 class='actor-movies__title'>Movies</h3>
        <div class='tmdb-movie-list'>";
        
        foreach ( $movies as $movie ) {
            $poster = $movie['poster_path'] ? esc_url( 'https://image.tmdb.org/t/p/w300' . $movie['poster_path'] ) : '';
            $title = esc_html( $movie['title'] ?? 'Untitled' );
            $release = esc_html( $movie['release_date'] ?? 'N/A' );
            $character = esc_html( $movie['character'] ?? 'N/A' );
            
            $output .= "<div class='tmdb-movie-list__item'>";
            
            if ( $poster ) {
                $output .= "<img src='{$poster}' alt='{$title}' class='tmdb-movie-list__poster'>";
            }
            
            $output .= "<h3 class='tmdb-movie-list__title'>{$title}</h3><br>
            <em class='tmdb-movie-list__release'>{$release}</em>
            <span class='tmdb-movie-list__character'>as {$character}</span>
            </div>";
        }
        $output .= "</div>";
    }
    
    return $output;
}

add_shortcode( 'actor_detail', 'tmdb_actor_detail_view' );
?>