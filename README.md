# CIS - CVrČkov Informačný Systém (PHP backend)

This project was implemented using [Slim](http://www.slimframework.com).

## Install

`brew install composer`
`composer require slim/slim`
`composer require slim/psr7`
`composer require tuupola/slim-basic-auth`
`composer install` (probably not necessary)

### Slim-Skeleton (not used)

`composer create-project slim/slim-skeleton cis-be-php`

## Run

Start a server with `php -S localhost:8080 -t src/`.
Use `php -S 127.0.0.1:8080 -t src/` for VSCode's REST Client to work.

## Others

`psql cvrcek -c 'SHOW SERVER_ENCODING'`
