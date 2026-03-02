# ─── Build stage : dépendances Composer ───────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ─── Image finale : FrankenPHP ────────────────────────────────────────────────
FROM dunglas/frankenphp:latest-php8.4

# Extensions PHP nécessaires
RUN install-php-extensions \
    pdo_mysql \
    intl \
    zip \
    opcache \
    sodium

# Répertoire de travail
WORKDIR /app

# Copie du code source
COPY . .

# Copie des dépendances Composer pré-installées
COPY --from=vendor /app/vendor ./vendor

# Assets Symfony (importmap + asset-mapper)
RUN php bin/console importmap:install --no-interaction
RUN php bin/console asset-map:compile --no-interaction

# Optimisation Symfony pour la prod
RUN php bin/console cache:warmup --env=prod

# Droits sur les répertoires var/ et public/
RUN chown -R www-data:www-data var public

EXPOSE 80
