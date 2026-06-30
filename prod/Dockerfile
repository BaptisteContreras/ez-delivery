FROM 'php:8.4-cli-trixie' as base

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
COPY src /app/src

RUN mkdir /app/.castor

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN composer global require humbug/box
ENV PATH="/app/vendor/bin:/root/.composer/vendor/bin:$PATH"

RUN composer install -o --prefer-dist --no-cache

RUN vendor/bin/castor repack --app-name ez-delivery

FROM base as prod
LABEL org.opencontainers.image.source="https://github.com/BaptisteContreras/ez-delivery"

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

