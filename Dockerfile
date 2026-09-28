# Build Vite assets first, then serve Laravel with Nginx + PHP-FPM.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# Render's maintained Nginx + PHP-FPM image for Laravel/PHP applications.
FROM richarvey/nginx-php-fpm:3.1.6
COPY . /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

ENV SKIP_COMPOSER=1 \
    WEBROOT=/var/www/html/public \
    PHP_ERRORS_STDERR=1 \
    RUN_SCRIPTS=1 \
    REAL_IP_HEADER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

CMD ["/start.sh"]
