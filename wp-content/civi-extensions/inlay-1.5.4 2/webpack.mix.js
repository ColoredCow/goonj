let mix = require('laravel-mix');

mix.js('src/inlay.js', 'dist/inlay.js')
  .js('src/polyfills', 'dist/')
;
