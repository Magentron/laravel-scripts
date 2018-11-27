<?php
/**
 *	laravel-scripts - Laravel service provider
 *
 *	@author 	Jeroen Derks <jeroen@derks.it>
 *	@since		2018/Nov/26
 *	@license	GPLv3 https://www.gnu.org/licenses/gpl.html
 *	@copyright	Copyright (c) 2018 Jeroen Derks / Derks.IT
 *	@url		https://github.com/Magentron/laravel-scripts/
 *
 *	This file is part of laravel-scripts.
 *
 *	laravel-scripts is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or (at
 *	your option) any later version.
 *
 *	laravel-scripts is distributed in the hope that it will be useful, but
 *	WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License along
 *	with laravel-scripts.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Magentron\LaravelScripts\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class LaravelScriptsServiceProvider
 *
 * @package Magentron\LaravelScripts\Providers
 */
class LaravelScriptsServiceProvider extends ServiceProvider
{
    /**
     * Files to publish.
     */
    const FILES = [
        'Envoy.blade.php',
        'Makefile',
        'opcache_reset.php',
    ];

    public function boot()
    {
        foreach (self::FILES as $file) {
            $this->publishes([
                realpath(__DIR__ . '/../../' . $file) => base_path($file),
            ], 'deployment');
        }
    }
}
