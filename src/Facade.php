<?php


namespace Moldedjelly\WebpackAssets;


class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'webpack.assets';
    }
}