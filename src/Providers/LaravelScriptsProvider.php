<?php

namespace Magentron\LaravelScripts\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelScriptsServiceProvider extends ServiceProvider
{
	public function boot()
	{
		$this->publishes([
			realpath(__DIR__ . '/../../Envoy.php') => base_path(),
			realpath(__DIR__ . '/../../Makefile')  => base_path(),
			realpath(__DIR__ . '/../../opcache_reset.php')  => public_path(),
		], 'deployment');
	}
}
