<?php


namespace Malyusha\WebpackAssets;

use Blade;
use Illuminate\Support\ServiceProvider;
use Malyusha\WebpackAssets\Exceptions\AssetException;

class WebpackAssetsServiceProvider extends ServiceProvider
{
    /**
     * Publish the plugin configuration.
     */
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/assets.php' => config_path('assets.php')]);

        $this->app->alias('webpack.assets', Facade::class);

        $this->registerAssetBladeDirective();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('webpack.assets', function () {
            $file = public_path(config('assets.file'));

            return new Asset($file, $this->app['url']);
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/assets.php', config_path('assets.php'));
    }

    /**
     * Registers @asset blade directive, calling push and endpush for given script and style.
     */
    public function registerAssetBladeDirective()
    {
        $this->app['blade.compiler']->directive('assets', function ($expression) {
            $string = '';
            $stacks = [
                config('assets.stacks.scripts'), config('assets.stacks.styles'),
            ];
            $segments = array_map('trim', explode(',', preg_replace("/[()\\\"']/", '', $expression)));

            foreach ($stacks as $key => $stack) {
                if(!array_key_exists($key, $segments) || $segments[$key] === 'null') {
                    continue;
                }

                $string .= '<?php $__env->startPush($stack); webpack()->$methods[$key]($segments[$key]); $__env->stopPush(); ?>';
            }

            return $string;
        });
    }

    public function provides()
    {
        return ['webpack.assets'];
    }
}