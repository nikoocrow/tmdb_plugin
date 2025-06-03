<h1 align="center">🎬 TMDB Plugin for WordPress</h1>

<p align="center"><strong>🚧 This plugin is a test project and must NOT be used for commercial purposes. It was created solely for the purpose of a job application. 🚧</strong></p>

<hr>

<h2>📥 Installation</h2>

<ol>
  <li>Download or clone the plugin into your WordPress plugins directory:</li>
</ol>

<pre><code>cd wp-content/plugins
git clone https://github.com/nikoocrow/tmdb_plugin.git</code></pre>

<ol start="2">
  <li>Log into your WordPress admin panel.</li>
  <li>Go to <strong>Plugins > Installed Plugins</strong> and activate <code>TMDB Plugin</code>.</li>
</ol>

<hr>

<h2>🧩 Pages Automatically Created</h2>

<p>When the plugin is activated, the following pages will be created with specific shortcodes:</p>

<ul>
  <li><strong>All Movies</strong></li>
  <li><strong>Movie Detail</strong></li>
  <li><strong>My Wishlist</strong></li>
  <li><strong>Upcoming Movies</strong></li>
</ul>

<p>Each of these pages includes a specific shortcode automatically inserted.</p>

<hr>

<h2>🔑 API Key Configuration</h2>

<ol>
  <li>Go to <strong>TMDb Movies</strong> in the admin sidebar.</li>
  <li>You will see a message: <code>⚠️ API Key not configured - Configure now</code></li>
  <li>Click the message and enter your API key in the field provided.</li>
  <li>Obtain an API key from the official TMDb website: <a href="https://www.themoviedb.org/?language=en" target="_blank">https://www.themoviedb.org</a></li>
</ol>

<hr>

<h2>🏠 Home Page Setup</h2>

<ol>
  <li>Create a new page titled <strong>Home</strong>.</li>
  <li>Go to <strong>Settings > Reading</strong></li>
  <li>Select <strong>"A static page"</strong> and assign the <strong>Home</strong> page.</li>
  <li>Click <strong>Save Changes</strong>.</li>
</ol>

<h3>📌 Recommended Shortcodes for Home Page:</h3>

<pre><code>[search_movies_and_actors]
[upcoming_movies number=10]
[popular_actors number=10]</code></pre>

<p><strong>Note:</strong> The search feature works without a logged-in user. To test it, make sure to log out of WordPress.</p>

<hr>

<h2>🎬 Movies and Actors Pages</h2>

<h3>Movies</h3>
<ul>
  <li>Create or edit a page named <strong>Movies</strong></li>
  <li>Add the following shortcode:</li>
</ul>

<pre><code>[tmdb_all_movies]</code></pre>

<p>If the Movie Detail page wasn't created automatically:</p>
<ul>
  <li>Create a new page called <strong>Movie Detail</strong></li>
  <li>Add the shortcode below:</li>
</ul>

<pre><code>[movie_detail id="{GET}"]</code></pre>

<h3>Actors</h3>
<ul>
  <li>Create or edit a page called <strong>Actors</strong></li>
  <li>Add this shortcode:</li>
</ul>

<pre><code>[actor_list]</code></pre>

<p>For actor detail:</p>
<ul>
  <li>Create a page and name its slug <code>actor-detail</code></li>
  <li>Add the following shortcode:</li>
</ul>

<pre><code>[actor_detail]</code></pre>

<p><strong>Important:</strong> The slug <code>actor-detail</code> must be exactly like this or the detail page will not function.</p>

<hr>

<h2>🔐 Login & Registration Setup</h2>

<p>To enable login and registration functionality:</p>

<ol>
  <li>Create a page named <strong>Login</strong></li>
  <li>Set the slug as <code>login</code></li>
  <li>Create another page named <strong>Registration</strong></li>
  <li>Set the slug as <code>registration</code></li>
</ol>

<p>If configured properly, you will be able to access both login and registration forms from those pages.</p>

<hr>

<h2>💡 Lightbox Integration</h2>

<p>This plugin supports the use of a Lightbox gallery for displaying actor or movie images.</p>

<ul>
  <li>Compatible with libraries like <a href="https://lokeshdhakar.com/projects/lightbox2/" target="_blank">Lightbox2</a></li>
  <li>Include the Lightbox CSS/JS via your theme or enqueue it via the plugin.</li>
  <li>Images inside modals or detail views will open in a clean overlay.</li>
</ul>

<p><strong>Example usage (HTML):</strong></p>

<pre><code>&lt;a href="large-image.jpg" data-lightbox="gallery"&gt;
  &lt;img src="thumbnail.jpg" alt="Movie Poster"&gt;
&lt;/a&gt;</code></pre>

<hr>

<h2>⚙️ Gulp Development Workflow</h2>

<p>If you're working on styling or expanding the plugin with custom assets, you can use a Gulp-based SCSS workflow:</p>

<h3>🛠️ Requirements</h3>

<ul>
  <li><a href="https://nodejs.org/" target="_blank">Node.js</a></li>
  <li><code>npm install --global gulp-cli</code></li>
</ul>

<h3>⚡ Setup</h3>

<pre><code>cd wp-content/plugins/tmdb_plugin
npm install
gulp watch</code></pre>

<p>This setup will automatically:</p>

<ul>
  <li>Compile SCSS to CSS</li>
  <li>Autoprefix CSS for browser compatibility</li>
  <li>Minify output files</li>
  <li>Reload the browser using BrowserSync (optional)</li>
</ul>

<hr>

<h2>🗂️ Folder Structure</h2>

<pre><code>tmdb_plugin/
├── assets/              # Images, styles, scripts
├── includes/            # Plugin functionality
├── shortcodes/          # Shortcode handlers
├── templates/           # Template parts
├── gulpfile.js          # Gulp configuration
├── tmdb_plugin.php      # Main plugin file
└── README.md            # Plugin documentation
</code></pre>

<hr>

<h2>🛠️ Troubleshooting</h2>

<p>If any of the setup steps fail and you're using <a href="https://localwp.com/" target="_blank">LocalWP</a>, you can drag and drop the pre-configured compressed archive of this plugin or site to replicate the working environment.</p>

<hr>

<h2>📌 Notes</h2>

<ul>
  <li>❌ This plugin must <strong>not</strong> be commercialized.</li>
  <li>🧪 It was developed as a <strong>test for a job opportunity</strong>.</li>
  <li>👤 All rights reserved by the original author.</li>
</ul>

<hr>

<h2>📞 Support</h2>

<p>This plugin is not officially supported. However, you may open an issue if you have feedback or questions:</p>

<p>👉 <a href="https://github.com/nikoocrow/tmdb_plugin/issues">Open an issue on GitHub</a></p>
