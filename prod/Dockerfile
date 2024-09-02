FROM 'php:8.3-fpm' as base

RUN apt-get update && apt-get install -y git

RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-install zip

RUN apt-get install vim --assume-yes

RUN curl "https://castor.jolicode.com/install" | bash
RUN mv /root/.local/bin/castor /usr/local/bin/castor

WORKDIR /app

FROM base as builder

COPY composer.json /app/composer.json
COPY composer.lock /app/composer.lock
COPY castor.php /app/castor.php
COPY box.json /app/box.json

RUN mkdir /app/.castor

WORKDIR /app
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer

RUN composer global require humbug/box
ENV PATH="/app/vendor/bin:/root/.composer/vendor/bin:$PATH"

RUN composer install -o --prefer-dist --no-cache

RUN vendor/bin/castor repack --app-name ez-delivery

FROM base as prod

WORKDIR /app

COPY --from=builder /app/ez-delivery.linux.phar /app/ez-delivery.linux.phar

RUN mv /app/ez-delivery.linux.phar /usr/local/bin/ez-delivery && chmod +x  /usr/local/bin/ez-delivery

ENV HOME=/ez/home
ENV EZ_DELIVERY_CONFIG_PATH='/home/ez-delivery/.ez'


RUN useradd -u 3000  -s /bin/bash -m ez-delivery

RUN echo "alias gst='git status'" >> /home/ez-delivery/.bashrc
RUN echo "alias ga='git add .'" >> /home/ez-delivery/.bashrc

COPY prod/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

