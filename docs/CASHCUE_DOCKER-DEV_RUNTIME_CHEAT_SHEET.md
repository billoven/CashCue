# CashCue Docker Runtime Cheat Sheet

*(Practical lifecycle reference)*

This document explains **how CashCue Docker deployment actually works**, based on the current project structure.

It clarifies:

* what each configuration file does
* when images are built
* when containers exist
* when the application actually starts

---

# 1. Configuration Source

The **single source of configuration** is:

```
conf/cashcue.conf
```

Loaded by:

* `Makefile`
* `docker-compose.yml`
* `docker-entrypoint.sh`

Example variables:

```
DB_HOST=cashcue_db
DB_USER=cashcue
DB_PASS=xxxx
DB_NAME=cashcue
APP_PORT=8000
```

---

# 2. Control Layer (Makefile)

The **Makefile orchestrates everything**.

Main Docker commands:

```
make docker-up
make docker-down
make docker-reset
make init-db
make deploy-container
```

Important variable:

```
DOCKER_COMPOSE = docker compose \
    -f docker/docker-compose.yml \
    --env-file conf/cashcue.conf
```

Meaning:

**docker-compose automatically receives the variables from `cashcue.conf`.**

---

# 3. Container Architecture

CashCue uses **two containers**.

```
+--------------------+
|   cashcue_app      |
|--------------------|
| Apache + PHP       |
| entrypoint.sh      |
| mounted source     |
+---------+----------+
          |
          | MariaDB client connection
          |
+---------v----------+
|    cashcue_db      |
|--------------------|
| MariaDB 11         |
| persistent volume  |
+--------------------+
```

---

# 4. Docker Compose Role

File:

```
docker/docker-compose.yml
```

Responsibilities:

* define containers
* define networking
* inject environment variables
* define volumes
* define health checks

Important section:

```
cashcue_db
```

Creates MariaDB with:

```
MYSQL_ROOT_PASSWORD
MYSQL_DATABASE
MYSQL_USER
MYSQL_PASSWORD
```

---

For the application container:

```
cashcue_app
```

Key elements:

### Build the image

```
build:
  context: ..
  dockerfile: docker/Dockerfile
```

### Mount the project

```
volumes:
  - ../:/data/cashcue
```

Meaning:

**The host source code is directly visible inside the container.**

---

### Mount configuration

```
- ../conf/cashcue.conf:/etc/cashcue/cashcue.conf:ro
```

---

### DB dependency

```
depends_on:
  cashcue_db:
    condition: service_healthy
```

Docker waits until MariaDB is healthy.

---

# 5. Docker Image Build

Performed by:

```
make docker-up
```

Which runs:

```
docker compose up --build -d
```

Build instructions are defined in:

```
docker/Dockerfile
```

---

# 6. Dockerfile Role

The Dockerfile **builds the application image**.

It installs:

```
Apache
PHP 8.3
MariaDB client
```

It also installs Apache configuration:

```
conf/apache/vhost.conf
conf/apache/security.conf
```

And registers the **entrypoint script**:

```
ENTRYPOINT /usr/local/bin/docker-entrypoint.sh
```

And the **default container command**:

```
CMD apache2ctl -D FOREGROUND
```

---

# 7. Container Startup Sequence

When the container starts:

```
ENTRYPOINT runs first
```

So this executes:

```
docker-entrypoint.sh
```

---

# 8. Entrypoint Responsibilities

File:

```
docker/docker-entrypoint.sh
```

Responsibilities:

### 1 Load configuration

```
source /data/cashcue/conf/cashcue.conf
```

This provides:

```
DB_HOST
DB_USER
DB_PASS
DB_NAME
```

---

### 2 Wait for database

```
mariadb -h $DB_HOST ...
```

Loop until the DB responds.

---

### 3 Prepare Apache web root

Creates symlink:

```
/var/www/html/cashcue -> /data/cashcue/web
```

This exposes the PHP frontend.

---

### 4 Start Apache

Finally:

```
exec "$@"
```

Which executes the Docker CMD:

```
apache2ctl -D FOREGROUND
```

Apache now becomes **PID 1 of the container**.

---

# 9. Database Initialization

Database schema is installed using:

```
make init-db
```

Which executes:

```
docker compose exec cashcue_app \
    bash /data/cashcue/adm/install_cashcue_db.sh
```

This script loads:

```
adm/schemaCashCueBD.sql
```

Into MariaDB.

---

# 10. Runtime Layout (Inside Container)

Application container filesystem:

```
/data/cashcue           -> full project (mounted)

/data/cashcue/web       -> PHP frontend

/var/www/html/cashcue   -> Apache document root

/etc/cashcue/cashcue.conf -> configuration
```

---

# 11. Container Lifecycle

## Start

```
make docker-up
```

Equivalent to:

```
docker compose up --build -d
```

Creates:

* images
* containers
* network
* volumes

---

## Stop

```
make docker-down
```

Runs:

```
docker compose down
```

Containers stop but volumes persist.

---

## Full reset

```
make docker-reset
```

Runs:

```
docker compose down -v
docker compose up --build -d
```

This deletes:

* containers
* volumes
* database data

---

# 12. Quick Operational Commands

Start stack:

```
make docker-up
```

Initialize database:

```
make init-db
```

Stop stack:

```
make docker-down
```

Reset everything:

```
make docker-reset
```

---

# 13. Important Concept Summary

| Layer              | Role                    |
| ------------------ | ----------------------- |
| Makefile           | orchestration           |
| docker-compose.yml | container definition    |
| Dockerfile         | image build             |
| entrypoint.sh      | container startup logic |
| Apache             | web server runtime      |

---

# 14. Mental Model

Think of the system like this:

```
Makefile
   |
   v
docker-compose
   |
   v
Dockerfile -> build image
   |
   v
Container start
   |
   v
Entrypoint
   |
   v
Apache running
```

---

💡 **Important design advantage of CashCue**

The project code is **mounted into the container**:

```
../:/data/cashcue
```

Which means:

* no rebuild required for code changes
* instant development feedback

---

