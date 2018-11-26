<?php
	/**
	 *	laravel-scripts - helper functions
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

	use Illuminate\Support\Debug\Dumper;
	use Symfony\Component\VarDumper\Cloner\VarCloner;
	use Symfony\Component\VarDumper\Dumper\CliDumper;

	if (! function_exists('dumpVariables')) {
		/**
		 * Dump the passed variables.
		 *
		 * @param  mixed
		 * @return void
		 */
		function dumpVariables()
		{
			foreach (func_get_args() as $x) {
				(new Dumper)->dump($x);
			}
		}

		/**
		 * Dump the passed variables for CLI output.
		 *
		 * @param  mixed
		 * @return void
		 */
		function dumpVariablesCli()
		{
			$dumper = new CliDumper;
			foreach (func_get_args() as $x) {
				$dumper->dump((new VarCloner)->cloneVar($x));
			}
		}
	}
