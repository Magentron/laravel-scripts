#
#	laravel-scripts - Laravel specific Makefile
#
#	@author 	Jeroen Derks <jeroen@derks.it>
#	@since		2017/May/23
#	@license	GPLv3 https://www.gnu.org/licenses/gpl.html
#	@copyright	Copyright (c) 2017-2018 Jeroen Derks / Derks.IT
#	@url		https://github.com/Magentron/laravel-scripts/
#
#	This file is part of laravel-scripts.
#
#	laravel-scripts is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by 
#	the Free Software Foundation, either version 3 of the License, or (at
#	your option) any later version.
#
#	laravel-scripts is distributed in the hope that it will be useful, but
#	WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License along
#	with laravel-scripts.  If not, see <http://www.gnu.org/licenses/>.
#

ANT := $(shell which ant)
ANT_TARGET=full-build-parallel
ARTISAN=$(PHP) artisan $(ARTISAN_EXTRA)
COMPOSER=composer
CONVERT=convert
CURL=curl
DOTENV := $(shell [ -z '$(ENVIRONMENT)' ] && echo .env || echo .env.$(ENVIRONMENT) )
ENVIRONMENT=
LANG=nl
NPM_ASSETS=public/css/app.css public/js/app.js
NPM_ENV=development
PHP=php -d memory_limit=256M $(PHP_EXTRA)
PHPUNIT=$(PHP) vendor/bin/phpunit -d xdebug.max_nesting_level=250 -d memory_limit=1024M $(PHPUNIT_EXTRA)
PRETEND=--pretend
SCOUT_MODELS=
SRCS= Makefile README.md webpack.mix.js composer.json package-lock.json package.json Envoy.blade.php \
	  opcache_reset.php server.php phpunit.xml app bootstrap/app.php bootstrap/autoload.php config \
	  database model resources routes tests
USER_AGENT:=$(shell $(CURL) --version | head -1 | awk '{printf("%s/%s", $$1, $$2);}')
WGET=wget --no-check-certificate $(WGET_EXTRA)
WRITABLE_DIRS=storage/*/* bootstrap/cache
WWW_GROUP_ID := $(shell if id _www > /dev/null 2>&1; then echo _www; else echo www-data; fi)

DEFAULT_BRANCH=master
TEMP_DEPLOY_DIR := $(shell echo .deploy.tmp.$$$$)

all:	rw

rw:
	sudo chmod -R g+w $(WRITABLE_DIRS)
	sudo chown -R $(USER):$(WWW_GROUP_ID) $(WRITABLE_DIRS)

init:	composer .env laravel-key npms passport-keys reminder

install-envoy:
	$(COMPOSER) global require laravel/envoy

composer:
	$(COMPOSER) install

.env:
	cp .env.example .env

laravel-key:
	$(ARTISAN) key:generate

# Laravel Passport is not always used, so ignore exit status
passport-keys:
	-$(ARTISAN) passport:keys

reminder:
	@echo remember to edit .env file with db name/pass etc

init-elastic:
#	bash -c '. $(DOTENV) && set -x && curl -XDELETE "http://$${SCOUT_ELASTIC_HOST}/$${SCOUT_ELASTIC_INDEX_NAME}?pretty"'
	-$(ARTISAN) elastic:drop-index 'App\ScoutElasticIndexConfigurator'
	$(ARTISAN) migrate:refresh
	$(ARTISAN) elastic:create-index 'App\Scout\ElasticIndexConfigurator'
	time $(ARTISAN) db:seed --class=DummyDataSeeder
	[ -z $(SCOUT_MODELS) ] || for model in $(SCOUT_MODELS); do $(ARTISAN) scout:import "$$model"; done

deploy-checkout-copy-manual:
	@[ ! -z '$(BRANCH)' -a ! -z '$(DST)' ] || (echo "missing BRANCH=... or DST=..." 1>&2; exit 1)
	(rm -rf $(TEMP_DEPLOY_DIR) && git clone --recursive -j8 -b $(BRANCH) . $(TEMP_DEPLOY_DIR) && rsync -zaSHx $(TEMP_DEPLOY_DIR)/ $(DST)/); rm -rf $(TEMP_DEPLOY_DIR)

deploy-init:
	time $(HOME)/.composer/vendor/bin/envoy $(ENVOY_EXTRA) run $(RUN_EXTRA) init $(EXTRA)

dry-deploy-init:
	make deploy-init EXTRA='--dry_run=1 $(EXTRA)'

deploy:
	time $(HOME)/.composer/vendor/bin/envoy $(ENVOY_EXTRA) run $(RUN_EXTRA) deploy $(EXTRA)

dry-deploy:
	make deploy EXTRA='--dry_run=1 $(EXTRA)'

init-demo:
	make deploy-init EXTRA='--env=demo $(EXTRA)'

dry-init-demo:
	make init-demo EXTRA='--dry_run=1 $(EXTRA)'

deploy-demo:
	time $(HOME)/.composer/vendor/bin/envoy $(ENVOY_EXTRA) run $(RUN_EXTRA) deploy --env=demo $(EXTRA)

dry-deploy-demo:
	make deploy-demo EXTRA='--dry_run=1 $(EXTRA)'

init-staging:
	make deploy-init EXTRA='--env=staging $(EXTRA)'

dry-init-staging:
	make init-staging EXTRA='--dry_run=1 $(EXTRA)'

deploy-staging:
	make deploy EXTRA='--env=staging $(EXTRA)'

dry-deploy-staging:
	make deploy-staging EXTRA='--dry_run=1 $(EXTRA)'

ant build: .build.dummy.

.build.dummy.:
	time $(ANT) $(EXTRA) $(ANT_TARGET)

test:	rw clear-cache autodump
	time $(PHPUNIT) $(EXTRA)

test-fast fast-test testfast fasttest:
	time $(PHPUNIT) --no-coverage $(EXTRA)

test-tee testtee:	rw clear-cache autodump optimize
	set -o pipefail; time $(PHPUNIT) $(EXTRA) 2>&1 | tee build/phpunit.out

test-func testfunc:
	@[ ! -z "$(FUNC)" ] || (echo "missing FUNC=..."; exit 1)
	make test EXTRA="--filter '/::$(FUNC)\$$\$$/' $(EXTRA)"

test-fast-func test-fastfunc testfast-func testfastfunc test-func-fast test-funcfast testfuncfast:
	@[ ! -z "$(FUNC)" ] || (echo "missing FUNC=..."; exit 1)
	make testfast EXTRA="--filter '/::$(FUNC)\$$\$$/' $(EXTRA)"

test-profiler testprofiler:
	@cwd=`pwd`; if [ -z "$(FUNC)" ]; then \
		make testfast PHP="$(PHP) -d xdebug.profiler_enable=1 -d xdebug.profiler_output_name=cachegrind.out.%p -d xdebug.profiler_output_dir=$$cwd/storage/tmp/xdebug" EXTRA='$(EXTRA)'; \
	 else \
		make testfastfunc PHP="$(PHP) -d xdebug.profiler_enable=1 -d xdebug.profiler_output_name=cachegrind.out.%p -d xdebug.profiler_output_dir=$$cwd/storage/tmp/xdebug" EXTRA='$(EXTRA)' FUNC='$(FUNC)'; \
	 fi

test-stop teststop:
	make test EXTRA="--stop-on-failure $(EXTRA)"

test-user testuser:
	make test EXTRA='$(EXTRA) ./tests/Unit/UserTest'

test-elastic testelastic:
	@[ ! -z '$(QUERY)' ] || (echo missing QUERY=... 1>&2; exit 1)
	echo '{"query":{"bool":{"must":[{"query_string":{"default_field":"_all","query":".*word.*"}}],"must_not":[],"should":[]}},"from":0,"size":10,"sort":[],"aggs":{}}' | bash -c '. $(DOTENV) && \
		curl --trace-ascii - -H "Content-Type: application/json" --data @- "http://$${SCOUT_ELASTIC_HOST}/$${SCOUT_ELASTIC_INDEX_NAME}/_search?pretty"'

test-migrate:
	time $(ARTISAN) --env=testing migrate $(EXTRA)

$(NPM_ASSETS):	Makefile webpack.mix.js resources/assets/sass/*.scss resources/assets/js/*.js
	time npm run $(NPM_ENV)

npms:
	npm install

npm-clean npm-clear:
	rm -f $(NPM_ASSETS)

npm-dev npm-devel npm-develop npm-development:	$(NPM_ASSETS)

npm-watch:
	time npm run watch

npm-prod npm-production:
	@make npm-dev NPM_ENV=production

get-envoy:
	$(WGET) -N https://raw.githubusercontent.com/papertank/envoy-deploy/master/Envoy.blade.php

docker-image:
	docker build --squash .

lint lint-parallel:	
	@make -j4 jsonlint xmllint phplint bladelint

lint-sequential:	jsonlint xmllint phplint bladelint

bladelint blade-lint lint-blade lintblade:
	@: echo lint - Blade...
	@: nice -20 $(ARTISAN) blade:lint --quiet

jsonlint json-lint lint-json lintjson:
	@echo lint - JSON...
	@find *.json app bootstrap public resources routes tests -name '*.json' | nice -20 parallel 'echo {}:; jsonlint -q {}' > .tmp.jsonlint 2>&1;\
		egrep -B1 '^(Error:|\s|\.\.\. )' .tmp.jsonlint | egrep -v ^--; res=$$?; rm -f .tmp.jsonlint; [ 0 != "$$res" ]

phplint php-lint lint-php lintphp:
	@echo lint - PHP...
	@find *.php app bootstrap public resources routes tests -name '*.php' | nice -20 parallel 'php -l {}' | fgrep -v 'No syntax errors detected' > .tmp.phplint;\
		[ ! -s .tmp.phplint ]; res=$$?; cat .tmp.phplint; rm -f .tmp.phplint; exit $$res

xmllint xml-lint lint-xml lintxml:
	@echo lint - XML...
	@find *.xml app bootstrap public resources routes tests -name '*.xml' | while read file; do nice -20 xmllint --noout "$$file"; done

find-texts:
	@egrep -Er '>[A-Z][a-z]|^\s*[A-Z][a-z][A-Za-z0-9 _.-]*\s*$$' resources/views/ $(CMD_EXTRA)

gentrans:
	@grep -r --exclude=".svn" --exclude=CVS --exclude=.git --include="*."{conf,cfg,inc,ini,php,php3,php4,php5,xml,yml} -hoEe "(__|\@lang)\\((\\$$.*\\?\\s*)?'[^']*'(\s*:\s*'[^']*')?[\\),]" app resources | sort -n | uniq | egrep -oe "'[^']+'" | uniq \
		| cut -c2- | rev | cut -c2- | rev \
		| while read text; do fgrep -qs "\"`echo \"$$text\" | sed -e 's@"@\\\\"@g'`\"" resources/lang/$(LANG).json || echo "	\"$$text\": \"$$text\","; done

gentrans-en:
	@make gentrans LANG=en

loc:
	@echo making fast copy...
	@rm -rf build/src
	@mkdir -p build/src
	@for i in $(SRCS) ; do ln -s ../../"$$i" build/src/; done
	@echo running sloccount...
	#sloccount --datadir .sloccount --follow build/src
	@sloccount --addlang js --addlang makefile --addlang sql --addlangall --follow $(EXTRA) -- build/src/ 2>&1 | fgrep -v 'Warning! Unclosed PHP file'
	@echo running cloc...
	@cloc --follow-links build/src

clean: clear
	rm -f Envoy[0-9a-f]*.php

autodump autoload dumpautoload dump-autoload:
	$(COMPOSER) dumpautoload

migrate:
	$(ARTISAN) migrate $(PRETEND)

route:
	$(ARTISAN) route:list

clear cache-clear clear-cache:
	$(ARTISAN) cache:clear
	$(ARTISAN) route:clear
	$(ARTISAN) view:clear
	$(ARTISAN) clear-compiled
	$(ARTISAN) config:clear

clear-optimize:	cache-clear optimize
	$(ARTISAN) config:cache
	$(ARTISAN) route:cache

optimize:
	$(ARTISAN) optimize

geoipdb:	storage/maxmind/GeoLite2-City.mmdb

storage/maxmind/GeoLite2-City.mmdb:
	mkdir -p storage/maxmind && curl http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz | zcat > $@

