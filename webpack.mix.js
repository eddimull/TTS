const mix = require('laravel-mix');
require('laravel-mix-blade-reload');
const path = require('path');
const fs = require('fs');
/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .vue()
    .postCss('resources/css/app.css', 'public/css', [
        require('postcss-import'),
        require('tailwindcss'),
        require('autoprefixer'),
    ])
    .bladeReload()
    .options({
        hmrOptions: {
            host: 'hmr.emuller.dev',
            port: '443'
        }
    })
    mix.webpackConfig({
        module: {
            rules: [
              {
                test: /\.postcss$/,
                use: [
                    'vue-style-loader',
                    {
                      loader: 'css-loader',
                      options: { importLoaders: 1 }
                    },
                    'postcss-loader'
                ]
              }
            ]
          },
        devServer: {
          host: '0.0.0.0',
          port: '9000'
          // https: {
          //   key: fs.readFileSync('./ssl/privkey.pem'),
          //   cert: fs.readFileSync('./ssl/fullchain.pem')
          // }
        },
        resolve: {
            alias: {
                '@': path.resolve('resources/js'),
            },
        }
      })

if (mix.inProduction()) {
    mix.version();
}
