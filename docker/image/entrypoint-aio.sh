#!/bin/bash
# All-in-one entrypoint: 用 mariadbd --bootstrap 模式做首次初始化,然后交给 supervisord
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

mkdir -p /run/mysqld "${REDIS_DATADIR}"
chown -R mysql:mysql /run/mysqld "${MYSQL_DATADIR}"
chown -R redis:redis "${REDIS_DATADIR}"

# === 1. 首次初始化数据目录 + 用 bootstrap 模式建用户/库(无 daemon 启停) ===
if [ ! -d "${MYSQL_DATADIR}/mysql" ]; then
    echo "[entrypoint-aio] initializing MariaDB datadir..."
    mariadb-install-db --user=mysql --datadir="${MYSQL_DATADIR}" --auth-root-authentication-method=normal >/dev/null

    echo "[entrypoint-aio] bootstrapping users + database..."
    mariadbd --user=mysql --datadir="${MYSQL_DATADIR}" --bootstrap <<SQL
USE mysql;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
GRANT ALL ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    echo "[entrypoint-aio] bootstrap done"
fi

# === 2. 写 .env ===
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

# === 3. install.lock 不存在则临时拉起 mariadb 跑 likeshop 安装 ===
if [ ! -f "${LOCK_FILE}" ]; then
    echo "[entrypoint-aio] running auto-install (booting MariaDB temporarily)..."
    mariadbd --user=mysql --datadir="${MYSQL_DATADIR}" --bind-address=127.0.0.1 --skip-name-resolve &
    INSTALL_PID=$!

    READY=0
    for i in $(seq 1 60); do
        if mysqladmin -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" ping --silent 2>/dev/null; then
            READY=1
            echo "[entrypoint-aio] MariaDB ready for install"
            break
        fi
        sleep 1
    done
    if [ "${READY}" != "1" ]; then
        echo "[entrypoint-aio] MariaDB failed to come up for install" >&2
        # 不直接 exit,让 supervisord 接手,用户能进容器调试
    else
        php /usr/local/bin/auto_install.php || echo "[entrypoint-aio] auto-install failed (see logs above)"
    fi

    # 优雅 shutdown,带超时,卡住就硬杀
    mysqladmin -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" shutdown 2>/dev/null || true
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

    if [ -f "${LOCK_FILE}" ]; then
        echo "[entrypoint-aio] auto-install OK; admin = ${ADMIN_USER} / ${ADMIN_PASSWORD}"
    fi
else
    echo "[entrypoint-aio] install.lock present, skipping install"
fi

chown -R www-data:www-data /server/runtime /server/public/uploads /server/config 2>/dev/null || true

echo "[entrypoint-aio] handing off to supervisord"
exec "$@"
