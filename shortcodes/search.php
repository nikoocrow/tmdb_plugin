<?php
function tmdb_search_form_shortcode() {
    ob_start();
    $nonce = wp_create_nonce('tmdb_search_nonce');
?>
<div class="search-container">
    <div id="tmdb-search-container">
        <form id="tmdb-search-form" class="tmdb-search-form">
            <div class="tmdb-search-form__form">
                <input type="text" 
                    id="tmdb-search-input" 
                    placeholder="Search movies or actors..." 
                    required>
                <button type="submit">Find</button>
            </div>
            <div class="tmdb-search-form__options">
                <label>
                    <input type="radio" name="search-type" checked> All 
                </label>
                <label>
                    <input type="radio" name="search-type" value="movie"> Movies only
                </label>
                <label>
                    <input type="radio" name="search-type" value="person"> Actors only
                </label>
            </div>
        </form>
        
        <div id="tmdb-loading" style="display: none; text-align: center; padding: 20px;">
            <div></div>
            <p style="margin-top: 10px; color: #666;">Searching...</p>
        </div>
      </div>
        <div id="tmdb-search-results"></div>
  
</div>

<script>
let currentPage = 1;
let currentQuery = '';
let searchType = 'all';
let isLoading = false;

function showLoading() {
    document.getElementById("tmdb-loading").style.display = "block";
    document.getElementById("tmdb-search-results").innerHTML = "";
    isLoading = true;
}

function hideLoading() {
    document.getElementById("tmdb-loading").style.display = "none";
    isLoading = false;
}

function loadResults(page = 1) {
    if (isLoading) return;
    
    showLoading();
    
    const url = new URL('<?php echo admin_url('admin-ajax.php'); ?>');
    url.searchParams.set('action', 'search_tmdb');
    url.searchParams.set('q', currentQuery);
    url.searchParams.set('page', page);
    url.searchParams.set('type', searchType);
    url.searchParams.set('nonce', '<?php echo $nonce; ?>');
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            hideLoading();
            document.getElementById("tmdb-search-results").innerHTML = html;
            
            // Smooth scroll to results
            document.getElementById("tmdb-search-results").scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        })
        .catch(error => {
            hideLoading();
            console.error('Search error:', error);
            document.getElementById("tmdb-search-results").innerHTML = 
                '<div style="text-align:center; padding:20px; color:#d63384; background:#f8d7da; border:1px solid #f5c6cb; border-radius:6px;">' +
                '<h4>❌ Search Error</h4>' +
                '<p>There was an error performing the search. Please try again.</p>' +
                '</div>';
        });
}

function changePage(page) {
    if (page < 1 || isLoading) return;
    currentPage = page;
    loadResults(page);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById("tmdb-search-form");
    const searchInput = document.getElementById("tmdb-search-input");
    const radioButtons = document.querySelectorAll('input[name="search-type"]');
    
    // Form submission
    form.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const query = searchInput.value.trim();
        if (!query) {
            searchInput.focus();
            return;
        }
        
        currentQuery = query;
        currentPage = 1;
        loadResults(currentPage);
    });
    
    // Search type change
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            searchType = this.value;
            if (currentQuery) {
                currentPage = 1;
                loadResults(currentPage);
            }
        });
    });
    
    // Enter key in search input
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
    
    // Auto-focus search input
    searchInput.focus();
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById("tmdb-search-input").focus();
    }
});
</script>

<?php
    return ob_get_clean();
}

add_shortcode('search_movies_and_actors', 'tmdb_search_form_shortcode');

// Función para limpiar cache si es necesario
function tmdb_clear_search_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tmdb_search_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tmdb_search_%'");
}

// Hook para limpiar cache cada hora
add_action('wp_scheduled_delete', 'tmdb_clear_search_cache');


