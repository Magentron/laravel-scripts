{{--
  --	Laravel specific Makefile
  --
  --	@author 	Jeroen Derks <jeroen@derks.it>
  --	@since		2017/May/23
  --	@license	GPLv3 https://www.gnu.org/licenses/gpl.html
  --	@copyright	Copyright (c) 2017-2018 Jeroen Derks / Derks.IT
  --	@url		https://github.com/Magentron/laravel-scripts/
  --
  --	This file is part of laravel-scripts.
  --
  --	laravel-scripts is free software: you can redistribute it and/or modify
  --	it under the terms of the GNU General Public License as published by 
  --	the Free Software Foundation, either version 3 of the License, or (at
  --	your option) any later version.
  --
  --	laravel-scripts is distributed in the hope that it will be useful, but
  --	WITHOUT ANY WARRANTY; without even the implied warranty of
  --	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  --	GNU General Public License for more details.
  --
  --	You should have received a copy of the GNU General Public License along
  --	with laravel-scripts.  If not, see <http://www.gnu.org/licenses/>.
  --
  --}}
@setup
	$date     = (new DateTime)->format('Ymd_His');
	$branch   = isset($branch) ? $branch : 'master';
	$release  = isset($release) ? $release : $date;
	$dry_run  = isset($dry_run) ? $dry_run : false;
	$runner   = $dry_run ? 'echo "[DRY-RUN]"' : '';
	$hostname = gethostname();

	// get which environment to load
	$env_file = '.env';

	if (isset($env)) {
		$env_file = '.env.' . $env;
		if (!file_exists($env_file)) {
			die('unknown environment');
		}
	} else {
		$env = 'local';
	}

	$is_dev = false;
	if (preg_match('/test|^local$/', $env)) {
		$is_dev = true;
	}

	// get task to run
	$argv = $_SERVER['argv'];
	$argc = count($argv);
	$task = '<UNKNOWN>';

	foreach ($argv as $n => $arg) {
		if ('run' == $arg) {
			while (++$n < $argc) {
				if ('--' != $argv[$n]) {
					$task = $argv[$n];
					break;
				}
			}
		}
	}

	// load environment from .env file
	require __DIR__.'/vendor/autoload.php';
	$dotenv = new Dotenv\Dotenv(__DIR__, $env_file);
	try {
		$dotenv->load();
		$dotenv->required(['APP_NAME', 'APP_URL', 'DEPLOY_SERVER', 'DEPLOY_PATH'])->notEmpty();
	} catch ( Exception $e )  {
		die($e->getMessage());
	}

	// get envrironment variables
	$app_key       = getenv('APP_KEY');
	$app_name      = getenv('APP_NAME');
	$app_url       = getenv('APP_URL');
	$server        = getenv('DEPLOY_SERVER');
	$repository    = getenv('DEPLOY_REPOSITORY');
	$path          = getenv('DEPLOY_PATH');
	$slack         = getenv('DEPLOY_SLACK_WEBHOOK');

	$maxmind_dl    = getenv('DEPLOY_DL_MAXMIND');
	$maxmind_dl    = 'false' === $maxmind_dl ? false : (boolean) $maxmind_dl;
	$opcache_reset = getenv('DEPLOY_OPCACHE_RESET');
	$opcache_reset = 'false' === $opcache_reset ? false : (boolean) $opcache_reset;

	if ( substr($path, 0, 1) !== '/' ) throw new Exception('Careful - your deployment path does not begin with /');

	$path               = rtrim($path, '/');
	$app_dir            = $path . '/private';
	$web_dir            = $path . '/web';
	$current_dir        = $app_dir . '/current';

	$releases_dir       = $app_dir . '/releases';
	$new_release_dir    = $releases_dir .'/'. $release;
	$log_file           = $app_dir . '/envoy.log';

	$prefix = get_prefix($server);
	fprintf(STDERR, "{$prefix}Getting current release directory path in %s ... ", $releases_dir); fflush(STDERR);
	$command = sprintf('ssh %s php -r \'"echo realpath(\\"\'%s\'\\");"\'', escapeshellarg($server), escapeshellarg($current_dir));
	exec($command, $output);
	$current_release_dir = last($output);
	fprintf(STDERR, " => %s\n", $current_release_dir); fflush(STDERR);

	define('CMD_LOG_START', 'exec> >(sh -c "while read line;do echo \`date\` \"\$line\";done|tee -a ' . escapeshellarg($log_file) . '") 2> >(sh -c "while read line;do echo \`date\` stderr: \"\$line\";done|tee -a ' . escapeshellarg($log_file) . '" >&2)');

	if (!$repository) {
		// make temporary checkout & copy to deployment server
		$temp_checkout_dir = sprintf('storage/tmp/checkouts/%s.tmp.%s', $branch, getmypid());
		if (!mkdir($temp_checkout_dir, 0700, true)) {
			die('failed to create directory ' . $temp_checkout_dir . ': ' . $php_errormsg);
		}

		$destination = ($server ? $server . ':' : '') . $new_release_dir;
		$prefix = get_prefix($hostname);
		fprintf(STDERR, "{$prefix}Checking out branch '{$branch}' and copying application to %s ...\n", $destination); fflush(STDERR);

		// create releases directory if necessary
		$command = sprintf('ssh %s mkdir -p "%s"', escapeshellarg($server), escapeshellarg($new_release_dir));
		exec($command, $output);

		// clone repository
		$command_format = 'sh -c "(%1$s umask 002; %1$s git clone --recursive -j8 -b %2$s . %3$s && %1$s rsync -zaSHx %3$s/ %4$s/); %1$s rm -rf %3$s" 2>&1';
		$command        = sprintf($command_format, $runner, escapeshellarg($branch), escapeshellarg($temp_checkout_dir), escapeshellarg($destination));

		$prefix = get_prefix($hostname);
		fprintf(STDERR, "{$prefix}executing command: %s\n", $command); fflush(STDERR);

		$e         = null;
		$proc      = null;
		$has_error = false;
		try {
			$proc = popen($command, 'r');
			while (!feof($proc)) {
				$line = fread($proc, 4096);
				$line = rtrim($line);

				// rudimentary error checking since pclose() does not help us
				$has_error |= preg_match('/(error|fatal|ssh):/', $line);
				
				if ('' !== $line) {
					$prefix = get_prefix($hostname);
					fprintf(STDERR, "{$prefix}%s\n", rtrim($line)); fflush(STDERR);
				}
			}
		} catch (Exception $e) {
			throw $e;
		} finally {
			if (null !== $proc) {
				pclose($proc);
			}
			@rmdir($temp_checkout_dir);
		}
		if ($has_error) exit(1);
	}
	$prefix = get_prefix($server);
	fputs(STDERR, "{$prefix}Setup done, preparing deployment... \n"); fflush(STDERR);

	function get_prefix($server)
	{
		$server_parts = explode('@', $server);
		$prefix = sprintf("\033[33m[%s]\033[0m: %s ", last($server_parts), date('D M d H:i:s T Y'));
		return $prefix;
	}
@endsetup

@servers(['web' => $server])

@story('init')
	deployment_start
	deployment_init
@endstory

@story('deploy')
	deployment_start
	deployment_composer
	deployment_links
	deployment_cache
	deployment_migrate
	deployment_publish
	deployment_option_cleanup
@endstory

@story('deploy_cleanup')
	deployment_start
	deployment_composer
	deployment_links
	deployment_cache
	deployment_migrate
	deployment_publish
	deployment_cleanup
@endstory

@task('deployment_init')
	{{ CMD_LOG_START }}
	echo " - Initializing of '{{ $app_name }}' started on {{ $date }} on environment: {{ $env }}: {{ $server }}"
	if [ ! -d {{ $current_dir }} ]; then
		{{ $runner }} mv {{ $new_release_dir }}/storage {{ $app_dir }}/storage
		{{ $runner }} cp {{ $new_release_dir }}/.env.example {{ $app_dir }}/.env
		echo "  +- deployment path initialised, configure {{ $app_dir }}/.env and run 'envoy run deploy --env={{ $env }}' to deploy."
	else
		echo "Deployment path already initialised (current symlink exists)! Aborted..."
		exit 1
	fi
@endtask

@task('deployment_start')
	{{ CMD_LOG_START }}
	echo "Starting deployment task '{{ $task }}' of '{{ $app_name }}' on {{ $date }} on {{ $env }}: {{ $server }}{{ $dry_run ? ' [DRY-RUN]' : '' }}"
	echo " - Cloning repository ..."
	[ -d {{ $releases_dir }} ] || {{ $runner }} mkdir {{ $releases_dir }}
	@if ($repository)
		{{ $runner }} git clone --recursive -j8 --depth 1 -b {{ $branch }} {{ $repository }} {{ $new_release_dir }}
		echo "   +- repository cloned"
	@endif
@endtask

@task('deployment_links')
	{{ CMD_LOG_START }}
	echo " - Creating links ..."
	{{ $runner }} rm -rf {{ $new_release_dir }}/storage
	{{ $runner }} ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env
	echo "  +- linked environment"
	{{ $runner }} ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage
	echo "  +- linked storage"
	@if ($current_release_dir)
	{{ $runner }} cp -a {{ $app_dir }}/.env {{ $current_release_dir }}/.env.{{ $release }}
	echo "  +- copied environment"
	{{ $runner }} cp -a {{ $app_dir }}/storage {{ $current_release_dir }}/storage.{{ $release }}
	echo "  +- copied storage"
	@endif
@endtask

@task('deployment_composer')
	{{ CMD_LOG_START }}
	echo " - Running composer ..."
	@if ( $current_release_dir )
	{{ $runner }} cp -a {{ $current_release_dir }}/vendor {{ $new_release_dir }}/
	echo "  +- copied vendor"
	@endif
	{{ $runner }} cd {{ $new_release_dir }}
	echo "  +- running composer ..."
	{{ $runner }} composer install --no-interaction {{ $is_dev ? '--no-dev' : '' }} --prefer-dist --no-scripts -q -o
	echo "  +- composer installed" 
@endtask

@task('deployment_cache')
	{{ CMD_LOG_START }}
	echo " - Clearing caches ..."
	{{ $runner }} php {{ $new_release_dir }}/artisan view:clear        # Clear all compiled view files
	{{ $runner }} php {{ $new_release_dir }}/artisan cache:clear       # Flush the application cache
	{{ $runner }} php {{ $new_release_dir }}/artisan route:clear       # Remove the route cache file
	{{ $runner }} php {{ $new_release_dir }}/artisan config:clear      # Remove the configuration cache file
	echo "  +- cache cleared"
	@if ($maxmind_dl)
		echo " - Downloading MaxMind GeoIP2 database ..."
		{{ $runner }} mkdir -p {{ $new_release_dir }}/storage/maxmind && {{ $runner }} {{ $dry_run ? '\'' : '' }}curl http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz | gzcat > {{ $new_release_dir }}/storage/maxmind/GeoLite2-City.mmdb{{ $dry_run ? '\'' : '' }}
		echo "  +- downloaded MaxMind GeoIP2 database ..."
	@endif
@endtask

@task('deployment_migrate')
	{{ CMD_LOG_START }}
	@if (!$app_key)
	echo " - Generating application keys..."
	{{ $runner }} php {{ $new_release_dir }}/artisan key:generate	# Set the application key
	@endif
	echo " - Running migrations ..."
	{{ $runner }} php {{ $new_release_dir }}/artisan migrate --env={{ $env }} --force --no-interaction
	echo "  +- migrations done"
@endtask

@task('deployment_publish')
	{{ CMD_LOG_START }}
	echo " - Publishing ..."
	{{ $runner }} ln -nfs {{ $new_release_dir }} {{ $current_dir }}
	{{ $runner }} ln -nfs {{ $current_dir }}/public/.htaccess {{ $web_dir }}/ || true
	{{ $runner }} [ -L {{ $web_dir }} ] || (ln -nfs {{ $current_dir }}/public/* {{ $web_dir }}/ && {{ $runner }} perl -pi -e "s@__DIR__\.'/\.\./@__DIR__ \. '/\.\./private/current/@g" {{ $web_dir }}/index.php || true)
	@if ($opcache_reset)
		echo -n " - Flusing opcode cache ..."
		{{ $runner }} ln -nfs {{ $current_dir }}/opcache_reset.php {{ $web_dir }}/ && {{ $runner }} curl -ksS {{ $app_url }}/opcache_reset.php && {{ $runner }} rm -f {{ $web_dir }}/opcache_reset.php || echo
	@endif
	@if ($current_release_dir)
		echo " - Copying .env and storage ..."
		{{ $runner }} rm {{ $current_release_dir }}/.env && {{ $runner }} mv {{ $current_release_dir }}/.env.{{ $release }} {{ $current_release_dir }}/.env
		{{ $runner }} rm {{ $current_release_dir }}/storage && {{ $runner }} mv {{ $current_release_dir }}/storage.{{ $release }} {{ $current_release_dir }}/storage
	@endif
	echo " - Generating caches ..."
	{{ $runner }} php {{ $current_dir }}/artisan optimize    	      # Generate class loader and remove the compiled class file
	{{ $runner }} php {{ $current_dir }}/artisan route:cache || true  # Create a route cache file for faster route registration (fails with Closure routes)
	{{ $runner }} php {{ $current_dir }}/artisan config:cache         # Create a cache file for faster configuration loading
	{{ $runner }} php {{ $current_dir }}/artisan config:cache         # 2nd time to fix incorrect database credentials???
	{{ $runner }} php {{ $current_dir }}/artisan storage:link         # link storage/public
	echo "  +- cache generated"
	echo "  +- published '{{ $app_name }}'{{ '@' }}{{ $branch }} to {{ $env }}"
@endtask

@task('deployment_cleanup')
	{{ CMD_LOG_START }}
	echo " - Running cleanup ..."
	find {{ $releases_dir }}  -maxdepth 1 -name "20*" -mmin +2880 | head -n 5 | xargs {{ $runner }} rm -Rf
	echo "  +- old deployments cleaned up"
@endtask

@task('deployment_option_cleanup')
	{{ CMD_LOG_START }}
	echo " - Cleaning up old deployments ..."
	cd {{ $releases_dir }}
	@if ( isset($cleanup) && $cleanup )
	find {{ $releases_dir }}  -maxdepth 1 -name "20*" -mmin +2880 | head -n 5 | xargs {{ $runner }} rm -Rf
	echo "  +- old deployments cleaned up"
	@endif
@endtask

@finished
	@slack($slack, '#deployments', "Finished Envoy task '{$task}' of '{$app_name}' on {$env}:{$server} at {$date}" . ($dry_run ? ' [DRY-RUN]' : ''))
@endfinished
