<?php
	/**
	 *	laravel-scripts - reset PHP opcode cache on server
	 *
	 *	@author 	Jeroen Derks <jeroen@derks.it>
	 *	@since		2017/Nov/02
	 *	@license	GPLv3 https://www.gnu.org/licenses/gpl.html
	 *	@copyright	Copyright (c) 2017-2018 Jeroen Derks / Derks.IT
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

	if (function_exists('opcache_reset')) {
		if (opcache_reset()) {
			echo 'OK';
		} else {
			echo '(not enabled)';
		}
	} else {
		echo '(not loaded)';
	}
