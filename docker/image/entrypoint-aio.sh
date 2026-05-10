#!/bin/bash
# All-in-one entrypoint: 启动临时 mariadb 初始化 → 跑安装 → 关掉 → 交给 supervisord 接管
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
SETUP_SOCK=/run/mysqld/setup.sock

mkdir -p /run/mysqld "${REDIS_DATADIR}"
chown -R mysql:mysql /run/mysqld "${MYSQL_DATADIR}"
chown -R redis:redis "${REDIS_DATADIR}"

# === 1. 初始化 mariadb 数据目录(仅首次) ===
if [ ! -d "${MYSQL_DATADIR}/mysql" ]; then
    echo "[entrypoint-aio] initializing MariaDB datadir..."
    mariadb-install-db --user=mysql --datadir="${MYSQL_DATADIR}" --auth-root-authentication-method=normal >/dev/null
fi

# === 2. 启动临时 mariadb (仅 socket,不开网络端口) —— 用来初始化账号 + 跑安装 ===
echo "[entrypoint-aio] booting temporary MariaDB for setup..."
mariadbd --user=mysql --datadir="${MYSQL_DATADIR}" --socket="${SETUP_SOCK}" --skip-networking --skip-grant-tables &
TMP_PID=$!

for i in $(seq 1 60); do
    if mysqladmin -S "${SETUP_SOCK}" -u root ping --silent 2>/dev/null; then
        break
    fi
    if [ "$i" = "60" ]; then
        echo "[entrypoint-aio] temp MariaDB failed to come up" >&2
        exit 1
    fi
    sleep 1
done

echo "[entrypoint-aio] applying users / db (idempotent)..."
mysql -S "${SETUP_SOCK}" -u root <<SQL || true
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
GRANT ALL ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# 关掉临时 mariadb (准备重启为启用网络 + grant tables 模式)
mysqladmin -S "${SETUP_SOCK}" -u root shutdown 2>/dev/null || true
wait "${TMP_PID}" 2>/dev/null || true
rm -f "${SETUP_SOCK}"

# === 3. 启动启用 grant tables + 127.0.0.1 网络的 mariadb,让安装脚本能连 TCP ===
echo "[entrypoint-aio] booting MariaDB with networking for install..."
mariadbd --user=mysql --datadir="${MYSQL_DATADIR}" --bind-address=127.0.0.1 --skip-name-resolve &
TCP_PID=$!

for i in $(seq 1 60); do
    if mysqladmin -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" ping --silent 2>/dev/null; then
        echo "[entrypoint-aio] MariaDB ready on 127.0.0.1:3306"
        break
    fi
    if [ "$i" = "60" ]; then
        echo "[entrypoint-aio] MariaDB (TCP) failed to come up" >&2
        exit 1
    fi
    sleep 1
done

# === 4. 写 .env ===
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

# === 5. 首次启动跑安装 ===
if [ ! -f "${LOCK_FILE}" ]; then
    echo "[entrypoint-aio] running auto-install..."
    php /usr/local/bin/auto_install.php
    if [ -f "${LOCK_FILE}" ]; then
        echo "[entrypoint-aio] auto-install OK; admin = ${ADMIN_USER} / ${ADMIN_PASSWORD}"
    else
        echo "[entrypoint-aio] auto-install failed" >&2
        # 不 exit —— 让 supervisord 照起,用户能进容器查日志
    fi
else
    echo "[entrypoint-aio] install.lock present, skipping install"
fi

# === 6. 关掉临时 mariadb,交给 supervisord ===
mysqladmin -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" shutdown 2>/dev/null || true
wait "${TCP_PID}" 2>/dev/null || true

chown -R www-data:www-data /server/runtime /server/public/uploads /server/config 2>/dev/null || true

echo "[entrypoint-aio] handing off to supervisord"
exec "$@"
