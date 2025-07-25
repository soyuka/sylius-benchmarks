FROM php:8.3-fpm

# Install necessary extensions
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
     procps vim \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    gd \
    intl \
    exif \
    zip \
    opcache

RUN version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION.(PHP_ZTS ? '-zts' : '');") \
&& architecture=$(uname -m) \
&& curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/linux/$architecture/$version \
&& mkdir -p /tmp/blackfire \
&& tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire \
&& mv /tmp/blackfire/blackfire-*.so $(php -r "echo ini_get ('extension_dir');")/blackfire.so \
&& printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8307\n" > $PHP_INI_DIR/conf.d/blackfire.ini \
&& rm -rf /tmp/blackfire /tmp/blackfire-probe.tar.gz

# Set working directory
WORKDIR /app

# Copy application files
COPY composer.json composer.lock /app/
COPY package.json symfony.lock /app/

COPY node_modules /app/node_modules
COPY vendor/ /app/vendor

# 3. Configuration files - change rarely
COPY config/ /app/config/

COPY assets/ /app/assets/
COPY themes/ /app/themes/
COPY public/ /app/public/

COPY .env /app/
COPY bin/ /app/bin/
COPY src/ /app/src/
COPY templates/ /app/templates/
COPY tests/ /app/tests/
COPY features/ /app/features/
COPY migrations/ /app/migrations/
COPY translations/ /app/translations/

# Copy php.ini tweaks
COPY php.ini /usr/local/etc/php/conf.d/php-tweaks.ini

# composer install --no-dev --optimize-autoloader --no-interaction \
# Install composer dependencies and clear cache
# RUN  php bin/console cache:clear
COPY www.conf /usr/local/etc/php-fpm.d/www.conf
RUN mkdir var && chown -R www-data:www-data var

