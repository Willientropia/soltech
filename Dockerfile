# Usar a imagem oficial do PHP 8.1 com Apache
FROM php:8.1-apache

# Habilitar o mod_rewrite do Apache para usar o .htaccess
RUN a2enmod rewrite

# Habilitar mod_headers para permitir uso de 'Header' no .htaccess
RUN a2enmod headers

# --- INÍCIO DA CORREÇÃO ---
# 1. Atualiza a lista de pacotes do sistema operacional (Debian)
# 2. Instala as dependências de desenvolvimento do PostgreSQL (libpq-dev) e outras úteis (zip)
# 3. Limpa o cache para manter a imagem menor
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zlib1g-dev \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*
# --- FIM DA CORREÇÃO ---

# Instalar as extensões PHP necessárias para o PostgreSQL (agora vai funcionar)
RUN docker-php-ext-install pdo pdo_pgsql zip

# Copiar o código da aplicação para o diretório do Apache
COPY . /var/www/html/

# Garante que o diretório de upload exista e tenha permissão
RUN mkdir -p /var/www/html/upload/images \
    && chown -R www-data:www-data /var/www/html/upload \
    && chmod -R 755 /var/www/html/upload