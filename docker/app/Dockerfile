# Imagem de PRODUÇÃO para Kubernetes — FrankenPHP (classic mode).
# Isolada do deploy no Railway: não referencia o Dockerfile da raiz,
# o railway.json nem o docker/railway-entrypoint.sh.
#
# syntax=docker/dockerfile:1

########################################
# Stage 1 — dependências (vendor)
########################################
FROM composer:2 AS vendor

WORKDIR /app

# Copia o código e instala as dependências de produção com autoloader otimizado.
COPY . .
RUN composer install \
      --no-dev \
      --optimize-autoloader \
      --classmap-authoritative \
      --no-interaction \
      --no-progress \
      --no-scripts \
    && rm -rf tests

########################################
# Stage 2 — runtime (FrankenPHP)
########################################
FROM dunglas/frankenphp:1-php8.4 AS app

# Extensões PHP exigidas pelo Laravel + Postgres.
RUN install-php-extensions \
      pdo_pgsql \
      pgsql \
      zip \
      mbstring \
      bcmath \
      intl \
      opcache

WORKDIR /app

# Configuração de produção do PHP + opcache.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "expose_php=0"; \
    } > "$PHP_INI_DIR/conf.d/zz-app.ini"

# Código da aplicação + vendor já instalado no stage anterior.
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Permissões de escrita do Laravel (cache/manifesto de pacotes/logs).
RUN chown -R www-data:www-data storage bootstrap/cache

# FrankenPHP serve /app/public via Caddy embutido, em HTTP na porta 8080.
# NÃO executa migrate/seed no start — isso é responsabilidade do Job de migração.
ENV SERVER_NAME=:8080
EXPOSE 8080
