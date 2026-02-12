# Используем официальный образ PHP CLI
FROM php:8.4-cli

# Установка системных зависимостей для ImageMagick
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# Установка расширения Imagick
RUN pecl install imagick && docker-php-ext-enable imagick

WORKDIR /app

# Создаём папку для diff-изображений
RUN mkdir -p /app/public/diffs && chmod -R 777 /app/public/diffs

# Копируем код
COPY . .

EXPOSE 8081

# Запуск встроенного сервера PHP
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public"]