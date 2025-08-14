FROM php:8.2-cli-trixie

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=3600

WORKDIR /code/

COPY docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh
# MySQL ODBC driver configuration
ENV MYSQL_ODBC_VERSION=8.4.0

RUN apt-get update && apt-get install -y --no-install-recommends \
        ssh \
        git \
        locales \
        unzip \
        unixodbc \
        unixodbc-dev \
        odbcinst \
        wget \
        libssl3 \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh \
	&& echo "Installing MySQL ODBC Connector..." \
	&& MYSQL_ODBC_TAR="mysql-connector-odbc-${MYSQL_ODBC_VERSION}-linux-glibc2.28-x86-64bit.tar.gz" \
	&& MYSQL_ODBC_URL="https://dev.mysql.com/get/Downloads/Connector-ODBC/${MYSQL_ODBC_TAR}" \
	&& wget -q "${MYSQL_ODBC_URL}" -O "/tmp/${MYSQL_ODBC_TAR}" \
	&& cd /tmp && tar -xzf "${MYSQL_ODBC_TAR}" \
	&& MYSQL_ODBC_DIR=$(find /tmp -maxdepth 1 -name "mysql-connector-odbc-*" -type d) \
	&& mkdir -p "/usr/lib/x86_64-linux-gnu/odbc" \
	&& cp "${MYSQL_ODBC_DIR}/lib/libmyodbc8w.so" "/usr/lib/x86_64-linux-gnu/odbc/" \
	&& cp "${MYSQL_ODBC_DIR}/lib/libmyodbc8a.so" "/usr/lib/x86_64-linux-gnu/odbc/" \
	&& ldconfig \
	&& rm -rf "/tmp/${MYSQL_ODBC_TAR}" "${MYSQL_ODBC_DIR}" \
	&& echo "Registering MySQL ODBC drivers with odbcinst..." \
	&& echo "[MySQL ODBC 8.4 Unicode Driver]" > /tmp/mysql_unicode.ini \
	&& echo "Driver=/usr/lib/x86_64-linux-gnu/odbc/libmyodbc8w.so" >> /tmp/mysql_unicode.ini \
	&& echo "[MySQL ODBC 8.4 ANSI Driver]" > /tmp/mysql_ansi.ini \
	&& echo "Driver=/usr/lib/x86_64-linux-gnu/odbc/libmyodbc8a.so" >> /tmp/mysql_ansi.ini \
	&& odbcinst -i -d -f /tmp/mysql_unicode.ini \
	&& odbcinst -i -d -f /tmp/mysql_ansi.ini \
	&& rm /tmp/mysql_unicode.ini /tmp/mysql_ansi.ini

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

# PDO mysql and sockets
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
