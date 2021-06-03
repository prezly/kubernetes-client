test: vendor
	php -d zend.assertions=1 -d assert.exception=1 -d max_execution_time=2 vendor/bin/peridot ./specs 2>/dev/null

vendor: composer.json composer.lock
	composer install

composer.lock: composer.json
	composer install
