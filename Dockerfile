FROM dunglas/frankenphp:php8.4

RUN install-php-extensions pdo_mysql

WORKDIR /app

COPY . /app

ENV SERVER_NAME=:8080

EXPOSE 8080

CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "/app"]