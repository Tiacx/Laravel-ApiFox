<?php

namespace Tiacx\ApiFox;

use Illuminate\Support\ServiceProvider;

class ApiFoxProvider extends ServiceProvider
{
    /**
     * 引导包服务
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/apifox.php' => config_path('apifox.php'),
        ], 'config');
    }
}