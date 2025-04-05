FROM 'php:8.3-fpm'


RUN apt-get update && apt-get install -y git
RUN pecl install xdebug && docker-php-ext-enable  xdebug

RUN apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql

RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-install zip

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer


RUN curl "https://castor.jolicode.com/install" | bash
RUN mv /root/.local/bin/castor /usr/local/bin/castor

RUN git config --global --add safe.directory /test
RUN git config --global user.email "38988658+BaptisteContreras@users.noreply.github.com"
RUN git config --global user.name "test"
RUN apt-get install vim --assume-yes

RUN composer global require humbug/box
ENV PATH="/app/vendor/bin:/root/.composer/vendor/bin:$PATH"
ENV EZ_DELIVERY_CONFIG_PATH='/root/.ez'

WORKDIR /app
