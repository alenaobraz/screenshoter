# Используем официальный образ PHP CLI
FROM php:8.4-cli

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    wget \
    git \
    curl \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    locales \
    && rm -rf /var/lib/apt/lists/*

# Настройка локали
RUN locale-gen en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LANGUAGE=en_US:en
ENV LC_ALL=en_US.UTF-8

# Установка GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Установка Node.js 18.x
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Установка Puppeteer глобально
RUN npm install -g puppeteer --unsafe-perm=true

# 🔧 ВАЖНО: добавляем NODE_PATH
ENV NODE_PATH=/usr/lib/node_modules:/usr/local/lib/node_modules

# Установка Chromium
RUN apt-get update && apt-get install -y \
    chromium \
    chromium-driver \
    && ln -s /usr/bin/chromium /usr/bin/chromium-browser

# Проверка установки
RUN node -v && npm -v
RUN chromium-browser --version
RUN node -e "require('puppeteer')" || echo "Puppeteer require failed"

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json .
RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN mkdir -p /app/public/screenshots && chmod -R 777 /app/public/screenshots

# Гарантируем права на /tmp
RUN chmod 1777 /tmp

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]