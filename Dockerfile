FROM leantime/leantime:3.8.0 AS builder

USER root
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache git nodejs npm

WORKDIR /build
COPY . .

RUN rm -f config/.env \
    && npm ci \
    && composer install --no-dev --optimize-autoloader --no-interaction \
    && rm -rf bootstrap/cache/*.php storage/framework/composerPaths.php storage/framework/viewPaths.php \
    && npx mix --production \
    && node generateBlocklist.mjs

FROM leantime/leantime:3.8.0

USER root
WORKDIR /var/www/html

RUN rm -rf app bin bootstrap config public vendor

COPY --from=builder --chown=www-data:www-data /build/app ./app
COPY --from=builder --chown=www-data:www-data /build/bin ./bin
COPY --from=builder --chown=www-data:www-data /build/bootstrap ./bootstrap
COPY --from=builder --chown=www-data:www-data /build/config ./config
COPY --from=builder --chown=www-data:www-data /build/public ./public
COPY --from=builder --chown=www-data:www-data /build/vendor ./vendor

RUN rm -f config/.env \
    && mkdir -p userfiles public/userfiles app/Plugins storage/logs \
        storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data userfiles public/userfiles app/Plugins storage bootstrap/cache \
    && chmod -R 775 userfiles public/userfiles app/Plugins storage bootstrap/cache

USER www-data
