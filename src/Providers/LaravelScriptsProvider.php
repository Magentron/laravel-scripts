<?php

namespace Develpr\AlexaApp\Provider;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Route;

class LaravelServiceProvider extends ServiceProvider
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
