const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename');

// Rutas
const paths = {
    scss: {
        src: './assets/scss/**/*.scss',
        dest: './assets/css/',
        main: './assets/scss/main.scss'
    }
};

// Compilar SCSS
gulp.task('scss', function() {
    return gulp.src(paths.scss.main)
        .pipe(sass({
            silenceDeprecations: ['legacy-js-api', 'import'] // Silenciar advertencias
        }).on('error', sass.logError))
        .pipe(gulp.dest(paths.scss.dest));
});

// Minificar CSS - SOLO archivos NO minificados
gulp.task('css-min', function() {
    return gulp.src(['./assets/css/*.css', '!./assets/css/*.min.css']) // Excluir .min.css
        .pipe(cleanCSS())
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(paths.scss.dest));
});

// Watch
gulp.task('watch', function() {
    gulp.watch('./assets/scss/**/*.scss', gulp.series('scss', 'css-min'));
});

// Build
gulp.task('build', gulp.series('scss', 'css-min'));

// Default
gulp.task('default', gulp.series('build', 'watch'));