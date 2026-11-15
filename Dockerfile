
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install pdo_pgsql intl mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY backend/ ./backend/

EXPOSE 10000

CMD ["sh", "-c", "php backend/bin/migrate.php && php backend/bin/seed.php && exec php -S 0.0.0.0:${PORT:-10000} -t backend/public backend/public/router.php"]
