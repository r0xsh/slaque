.PHONY: all help test logs ssh mycli tinker dbreset

PREFIX = docker-compose exec php
ARTISAN = $(PREFIX) php bin/console
COMPOSER = docker run --rm -it -v $(shell pwd):/app -u $(shell id -u):$(shell id -g) composer
PHP = $(PREFIX) php
ifdef FILTER
FILTERCMD = --filter $(FILTER)
endif

all:
	@echo "Mygooder build and management tool."

	@echo "\nContainer"
	@echo "  make start            - Start the docker-compose stack"
	@echo "  make ssh              - SSH to the platform docker"
	@echo "  make mycli            - Open an SQL client"
	@echo "  make stop             - Halt the docker-compose stack"

	@echo "\nComposer"
	@echo "  make install          - Download and install composer deps."

	@echo "\nArtisan & Database"
	@echo "  make dbreset          - Reset, migrate and seed the platform database"
	@echo "  make tinker           - Launches 'artisan tinker'"

	@echo "\nTesting"
	@echo "  make test             - Run all unit tests"

help: all

install: vendor

test: vendor start
	@$(PHP) vendor/bin/phpunit --configuration phpunit.xml -v --testdox $(FILTERCMD)

vendor: composer.json composer.lock
	@$(COMPOSER) install

.run:
	@touch .run
	@docker-compose up -d

start:	.run

stop:
	@docker-compose stop
	@rm -f .run

logs: start
	@docker-compose logs -f

ssh: start
	@${PREFIX} bash

mycli: start
	@docker-compose exec mariadb mysql -uroot -psecret platform

dbreset: start
	@${ARTISAN} migrate:fresh --seed

tinker: start
	@${PREFIX} sh -c "PHP_IDE_CONFIG=serverName=docker php artisan tinx"

prod_deploy:
	@[ -z "$CI" ]
	RELEASE = $(shell date '+%Y%m%d%H%M')
	RELEASES_PATH = /home/production/web/releases
	APP_PATH = /home/production/web/current
	@scp -r $(shell pwd) production@panda.mygooder.com:${RELEASEs_PATH}/${RELEASE}
	@ssh production@panda.mygooder.com "
		cd ${RELEASES_PATH}/${RELEASE}
		composer install --prefer-dist --no-scripts -q -o
		ln -nfs ${RELEASES_PATH}/${RELEASE} ${APP_PATH}
	"

