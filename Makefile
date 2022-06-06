init: init-ci
init-ci: docker-down-clear \
	docker-pull docker-build docker-up \
	app-init

up: docker-up
down: docker-down
restart: down up

#linter and code-style
lint: app-lint
analyze: app-analyze
cs-fix: app-cs-fix
test: app-test


#Docker
docker-up:
	docker-compose up -d

docker-down:
	docker-compose down --remove-orphans

docker-down-clear:
	docker-compose down -v --remove-orphans

docker-pull:
	docker-compose pull

docker-build:
	docker-compose build --pull

#Composer
app-init: app-composer-install

app-composer-install:
	docker-compose run --rm php-cli composer install

app-composer-update:
	docker-compose run --rm php-cli composer update

app-composer-autoload: #refresh autoloader
	docker-compose run --rm php-cli composer dump-autoload

app-composer-outdated: #get not updated
	docker-compose run --rm php-cli composer outdated