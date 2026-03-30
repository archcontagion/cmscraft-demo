ARG php_version=8.3
ARG node_version=22

### Stage 1: Node Build Environment
FROM node:${node_version}-alpine as nodebuilder

WORKDIR /app

COPY ./

COPY . .


### Stage 2: CraftCMS + PHP
FROM craftcms/nginx:${php_version}

USER root

# Install Composer: Copy the Composer executable from the official Composer image.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install system tools like git, unzip, bash, and vim using apk (Alpine Package Keeper).
# --no-cache avoids caching package lists, reducing image size.
# mariadb-connector and mysql-client are needed to use mysqldump in the web container.
RUN apk add --no-cache git unzip bash vim mariadb-connector-c mysql-client

WORKDIR /app

# Copy the composer.json and composer.lock files to the working directory.
COPY composer.json composer.lock ./

# Install PHP dependencies using Composer, with --no-interaction, --no-dev, and --optimize-autoloader flags.
# --no-interaction: Prevents Composer from prompting for user input.
# --no-dev: Skips installing development dependencies.
# --optimize-autoloader: Improves autoloader performance.
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Copy the compiled frontend assets from the nodebuilder stage to the /app/web/dist directory.
COPY --from=nodebuilder /app/web/dist /app/web/dist

# Copy the rest of the project files (backend code) into the container.
COPY . .

# Set permissions for Craft CMS directories and .env file.
# Create the storage, cpresources, and assets directories if they don't exist.
# Change ownership of config, storage, cpresources, assets, and .env to www-data.
# Set file permissions to 775 (read, write, execute for owner and group, read and execute for others).
RUN mkdir -p /app/storage /app/web/cpresources /app/web/assets \
  && chown -R www-data:www-data /app/config /app/storage /app/web/cpresources /app/web/assets /app/.env \
  && chmod -R 775 /app/config /app/storage /app/web/cpresources /app/web/assets /app/.env

# Switch back to the www-data user to run the Nginx web server.
USER www-data
