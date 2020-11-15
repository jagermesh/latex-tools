/* global require */
/* global exports */
/* global process */

const gulp = require('gulp');
const phplint = require('gulp-phplint');

const configs = { phplint: { src: [ '**/*.php', '!vendor/**/*.php', '!node_modules/**/*.php' ] } };

gulp.task('phplint', function() {
  return gulp.src(configs.phplint.src)
             .pipe(phplint('', { skipPassedFiles: true }))
             .pipe(phplint.reporter('default'))
             .pipe(phplint.reporter('fail'));
});

gulp.task('build',
  gulp.series( 'phplint' ));
