.PHONY: test

COMPOSER_ARGS ?= 

test: vendor
	php -d zend.assertions=1 -d assert.exception=1 -d max_execution_time=5 vendor/bin/peridot ./specs 2>/dev/null

vendor: composer.json composer.lock
	composer install $(COMPOSER_ARGS)
	touch vendor

composer.lock: composer.json
	composer install $(COMPOSER_ARGS)
