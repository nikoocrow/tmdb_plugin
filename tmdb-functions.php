<?php

if ( ! function_exists( 'tmdb_get_api_key' ) ) {
	function tmdb_get_api_key() {
		$api_key = get_option( 'tmdb_api_key', '' );

		// Puedes agregar validaciones o filtros aquí si lo deseas
		return sanitize_text_field( $api_key );
	}
}
