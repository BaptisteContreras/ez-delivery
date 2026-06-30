FROM 'php:8.3-fpm'


RUN apt-get update && apt-get install -y git
RUN pecl install xdebug && docker-php-ext-enable  xdebug

RUN apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql

RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-install zip

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer


RUN curl "https://castor.jolicode.com/install" | bash
RUN mv /root/.local/bin/castor /usr/local/bin/castor

RUN git config --global --add safe.directory /test
RUN git config --global --add safe.directory /test2
RUN git config --global user.email "38988658+BaptisteContreras@users.noreply.github.com"
RUN git config --global user.name "test"
RUN apt-get install vim --assume-yes

RUN composer global require humbug/box
ENV PATH="/app/vendor/bin:/root/.composer/vendor/bin:$PATH"
ENV EZ_DELIVERY_CONFIG_PATH='/root/.ez'

WORKDIR /app
