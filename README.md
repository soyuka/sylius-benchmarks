# Sylius standard benchmarks 

## Build images

For FrankenPHP: 

```console
docker build . -f Dockerfile.frankenphp -t rg.fr-par.scw.cloud/soyuka/sylius-frankenphp:v1.0.0
docker push rg.fr-par.scw.cloud/soyuka/sylius-frankenphp:v1.0.0
```

For Nginx:

```console
docker build . -f Dockerfile.nginx -t rg.fr-par.scw.cloud/soyuka/sylius-nginx:v1.0.0
docker push rg.fr-par.scw.cloud/soyuka/sylius-nginx:v1.0.0
docker build . -f Dockerfile.php -t rg.fr-par.scw.cloud/soyuka/sylius-phpfpm:v1.0.0
docker push rg.fr-par.scw.cloud/soyuka/sylius-phpfpm:v1.0.0
```

## Run a Mysql Database

Create a `sylius` database.

## Run an instance of an EC2 like

Grab one of these: 

### Nginx/fpm configuration:

```yaml
services:
  php:
    image: rg.fr-par.scw.cloud/soyuka/sylius-phpfpm:v1.0.0
    environment:
      APP_ENV: prod
      DATABASE_URL: mysql://user:pass@host/sylius?serverVersion=8&charset=utf8mb4
  nginx:
    image: rg.fr-par.scw.cloud/soyuka/sylius-normal-nginx:v1.0.1
    ports:
      - "80:8080"
    depends_on:
      - php
```

Adjust the number of php-fpm workers (you can use a shared volume on the repo)! 

```
# /usr/local/etc/php-fpm.d/www.conf

[www]
user = www-data
group = www-data
pm = static
pm.max_children = 15
```

15 was our sweet spot on a DEV1-S 2vcpu 2gb memory on scaleway.

### FrankenPHP (no worker)

```yaml
services:
  php:
    image: rg.fr-par.scw.cloud/soyuka/sylius-frankenphp:v1.0.0
    ports:
      - "80:8080"
    environment:
      APP_RUNTIME: "Symfony\\Component\\Runtime\\GenericRuntime"
      SERVER_NAME: :8080
      FRANKENPHP_CONFIG: |
        num_threads 36
        max_threads 36

      APP_ENV: prod
      DATABASE_URL: mysql://user:pass@host/sylius?serverVersion=8&charset=utf8mb4
```

35 threads was our sweet spot on a DEV1-S 2vcpu 2gb memory on scaleway.

### FrankenPHP

```yaml
services:
  php:
    image: rg.fr-par.scw.cloud/soyuka/sylius-frankenphp:v1.0.0
    ports:
      - "80:8080"
    environment:
      APP_RUNTIME: "Runtime\\FrankenPhpSymfony\\Runtime"
      SERVER_NAME: :8080
      FRANKENPHP_CONFIG: |
         worker {
           file ./public/index.php
           num 35
         }

      APP_ENV: prod
      DATABASE_URL: mysql://user:pass@host/sylius?serverVersion=8&charset=utf8mb4
```

35 threads was our sweet spot on a DEV1-S 2vcpu 2gb memory on scaleway.

## Setup

You need to setup sylius (probably once): 

```console
docker compose exec php bin/console sylius:install
```

## Running the benchmark

```console
docker compose up
docker compose exec php bin/console sylius:install:check-requirements
docker compose exec php mkdir -p var/log
docker compose exec php chown -R www-data:www-data var
```

Do some sane verifications for example check if the `APP_RUNTIME` environment variable did change.

On another machine:

Warmup the API:

```console
root@attacker:~# curl -I http://SERVER_IP/api/v2/shop/products/Summer_Picnic_Charm
root@attacker:~# wrk -t4 -c100 -d10s --latency --timeout 10s http://51.159.157.40/api/v2/shop/products/Summer_Picnic_Charm
```

Grab data:

```console
root@attacker:~# wrk -t4 -c200 -d10s --latency --timeout 20s http://51.159.157.40/api/v2/shop/products/Summer_Picnic_Charm
root@attacker:~# wrk -t4 -c100 -d10s --latency --timeout 20s http://51.159.157.40/api/v2/shop/products/Summer_Picnic_Charm
```
