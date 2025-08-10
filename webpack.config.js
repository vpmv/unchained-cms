var Encore = require('@symfony/webpack-encore');

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    .addEntry('app', './user/assets/user.js')

    // Add favicon from user dir
    .copyFiles([{
        from: './user/assets/images',
        to: 'images/[path][name].[ext]',
        pattern: /favicon\.webp$/,
    }])

    /*
     * For a full list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableSingleRuntimeChunk()
    .enableVersioning(Encore.isProduction())

    .autoProvidejQuery()
    .enableSassLoader()

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()
;

module.exports = Encore.getWebpackConfig();
