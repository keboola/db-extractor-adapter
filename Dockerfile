FROM php:8.2-cli-trixie

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=3600

WORKDIR /code/

COPY docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh
COPY docker/MariaDB_odbc_driver_template.ini /etc/MariaDB_odbc_driver_template.ini

# Install system dependencies including unixODBC and MariaDB ODBC driver 3.2.6
RUN apt-get update && apt-get install -y --no-install-recommends \
        ssh \
        git \
        locales \
        unzip \
        curl \
        unixodbc \
        unixodbc-dev \
        odbcinst \
        libmariadb3 \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh \
	&& echo "Installing MariaDB ODBC driver 3.2.6 from official MariaDB repository..." \
	&& ARCH=$(dpkg --print-architecture) \
	&& echo "Detected architecture: $ARCH" \
	&& cd /tmp \
	&& curl -L "https://dlm.mariadb.com/4275282/Connectors/odbc/connector-odbc-3.2.6/mariadb-connector-odbc_3.2.6-1+maria~bookworm_${ARCH}.deb" -o mariadb-odbc.deb \
	&& dpkg -i mariadb-odbc.deb \
	&& rm -f mariadb-odbc.deb \
	&& echo "Finding installed ODBC driver location..." \
	&& DRIVER_PATH=$(find /usr -name "libmaodbc.so" -type f 2>/dev/null | head -1) \
	&& if [ -z "$DRIVER_PATH" ]; then \
		echo "ERROR: MariaDB ODBC driver not found!" && exit 1; \
	fi \
	&& echo "Found MariaDB ODBC driver at: $DRIVER_PATH" \
	&& echo "Updating driver template with correct path..." \
	&& sed -i "s|/usr/lib/x86_64-linux-gnu/odbc/libmaodbc.so|$DRIVER_PATH|" /etc/MariaDB_odbc_driver_template.ini \
	&& echo "Registering ODBC driver..." \
	&& odbcinst -i -d -f /etc/MariaDB_odbc_driver_template.ini \
	&& echo "Verifying installation..." \
	&& ls -la "$DRIVER_PATH" \
	&& odbcinst -q -d \
	&& rm -rf /var/lib/apt/lists/*

ENV LANGUAGE="en_US.UTF-8"
ENV LANG="en_US.UTF-8"
ENV LC_ALL="en_US.UTF-8"

# PDO mysql and sockets extension
RUN docker-php-ext-install pdo_mysql sockets

# PHP ODBC
# https://github.com/docker-library/php/issues/103#issuecomment-353674490
RUN set -ex; \
    docker-php-source extract; \
    { \
        echo '# https://github.com/docker-library/php/issues/103#issuecomment-353674490'; \
        echo 'AC_DEFUN([PHP_ALWAYS_SHARED],[])dnl'; \
        echo; \
        cat /usr/src/php/ext/odbc/config.m4; \
    } > temp.m4; \
    mv temp.m4 /usr/src/php/ext/odbc/config.m4; \
    docker-php-ext-configure odbc --with-unixODBC=shared,/usr; \
    docker-php-ext-install odbc; \
    docker-php-source delete

## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY . /code/

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["composer", "ci"]
