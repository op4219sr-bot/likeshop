#!/bin/bash
# All-in-one entrypoint: 用 mariadbd --init-file 做首次用户/库初始化,然后交给 supervisord
set -e

: "${MYSQL_ROOT_PASSWORD:=root}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_NAME:=likeshop}"
: "${DB_USER:=root}"
: "${DB_PASSWORD:=${MYSQL_ROOT_PASSWORD}}"
: "${DB_PREFIX:=ls_}"
: "${ADMIN_USER:=admin}"
: "${ADMIN_PASSWORD:=123456}"
: "${IMPORT_DEMO:=yes}"
: "${APP_DEBUG:=false}"

export DB_HOST DB_PORT DB_USER DB_PASSWORD DB_NAME DB_PREFIX ADMIN_USER ADMIN_PASSWORD IMPORT_DEMO APP_DEBUG

MYSQL_DATADIR=/var/lib/mysql
REDIS_DATADIR=/var/lib/redis
LOCK_FILE=/server/config/install.lock
ENV_FILE=/server/.env
INIT_SQL=/tmp/mariadb-init.sql

mkdir -p /run/mysqld "${REDIS_DATADIR}"
chown -R mysql:mysql /run/mysqld "${MYSQL_DATADIR}"
chown -R redis:redis "${REDIS_DATADIR}"

# === 写 .env(给后续 php 用) ===
cat > "${ENV_FILE}" <<EOF
[app]
app_debug = ${APP_DEBUG}
app_trace = false

[database]
hostname = ${DB_HOST}
database = ${DB_NAME}
username = ${DB_USER}
password = ${DB_PASSWORD}
hostport = ${DB_PORT}

[project]
env_name =
file_domain =
EOF
chown www-data:www-data "${ENV_FILE}"

# === 只在 install.lock 不存在时,才需要拉 mariadb 做 setup + 安装 ===
if [ ! -f "${LOCK_FILE}" ]; then
    # 首次初始化数据目录
    if [ ! -d "${MYSQL_DATADIR}/mysql" ]; then
        echo "[entrypoint-aio] initializing MariaDB datadir..."
        mariadb-install-db --user=mysql --datadir="${MYSQL_DATADIR}" --auth-root-authentication-method=normal >/dev/null
    fi

    # 准备 init-file:mariadbd 启动后会以正常模式跑这段 SQL,再开放给客户端连接
    # 用 ALTER USER IF EXISTS + CREATE USER IF NOT EXISTS 双重保险,允许重复跑
    echo "[entrypoint-aio] preparing init SQL..."
    cat > "${INIT_SQL}" <<EOF
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
GRANT ALL ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
CREATE OR REPLACE USER 'root'@'127.0.0.1' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
GRANT ALL ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4;
CREATE OR REPLACE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
    chmod 644 "${INIT_SQL}"

    echo "[entrypoint-aio] booting MariaDB with --init-file..."
    mariadbd --user=mysql --datadir="${MYSQL_DATADIR}" --bind-address=127.0.0.1 --skip-name-resolve --init-file="${INIT_SQL}" &
    INSTALL_PID=$!

    READY=0
    for i in $(seq 1 90); do
        if mysqladmin -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" ping --silent 2>/dev/null; then
            READY=1
            echo "[entrypoint-aio] MariaDB ready (root@127.0.0.1 authenticates)"
            break
        fi
        sleep 1
    done

    if [ "${READY}" != "1" ]; then
        echo "[entrypoint-aio] MariaDB failed to come up — handing off anyway so users can debug" >&2
    else
        echo "[entrypoint-aio] running auto-install..."
        php /usr/local/bin/auto_install.php || echo "[entrypoint-aio] auto-install failed (see logs above)"
    fi

    # 优雅 shutdown,带超时,卡住就硬杀
    if [ "${READY}" = "1" ]; then
        mysqladmin -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" shutdown 2>/dev/null || true
    fi
    for i in $(seq 1 30); do
        if ! kill -0 "${INSTALL_PID}" 2>/dev/null; then break; fi
        sleep 1
    done
    if kill -0 "${INSTALL_PID}" 2>/dev/null; then
        echo "[entrypoint-aio] forcing MariaDB shutdown"
        kill "${INSTALL_PID}" 2>/dev/null || true
        sleep 2
        kill -9 "${INSTALL_PID}" 2>/dev/null || true
    fi

    rm -f "${INIT_SQL}"

    if [ -f "${LOCK_FILE}" ]; then
        echo "[entrypoint-aio] auto-install OK; admin = ${ADMIN_USER} / ${ADMIN_PASSWORD}"
    fi
else
    echo "[entrypoint-aio] install.lock present, skipping install"
fi

chown -R www-data:www-data /server/runtime /server/public/uploads /server/config 2>/dev/null || true

echo "[entrypoint-aio] handing off to supervisord"
exec "$@"
