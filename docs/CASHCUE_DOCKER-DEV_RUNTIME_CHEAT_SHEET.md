# CashCue Docker Runtime Cheat Sheet (For Cashcue Tests)

*(Practical lifecycle reference)*

This document explains **how CashCue Docker deployment works**, based on the current project structure.

It clarifies:

* what each configuration file does
* when images are built
* when containers exist
* when the application actually starts
* how to **backup and restore the database**

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
* `install_cashcue_db.sh`

Example variables:

```
DB_HOST=cashcue_db
DB_USER=cashcue
DB_PASS=xxxx
DB_NAME=cashcue
APP_PORT=8000
```

Optional variable used for database dumps:

```
DB_DUMP_FILE=adm/seed_ci_dataset.sql
```

This variable defines the **default location of database backups**.

It can be overridden when calling `make`.

Example:

```
sudo make docker-dump-db DB_DUMP_FILE=/tmp/test_dataset.sql
```

---

# 2. Control Layer (Makefile)

The **Makefile orchestrates the entire Docker lifecycle**.

Main Docker commands:

```
make docker-up
make docker-stop
make docker-down
make docker-reset
make init-db
make docker-dump-db
```

Important variable:

```
DOCKER_COMPOSE = docker compose \
    -f docker/docker-compose.yml \
    --env-file conf/cashcue.conf
```

Meaning:

**docker-compose automatically receives the variables from `cashcue.conf`.**

The Makefile also loads the configuration:

```
include conf/cashcue.conf
export
```

This means:

* variables defined in `cashcue.conf`
* are automatically available to **all Makefile targets**

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

Apache becomes **PID 1 of the container**.

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

Default schema:

```
adm/schemaCashCueBD.sql
```

---

### Custom schema or dataset

You can initialize the database using a **custom SQL dataset**.

Example:

```
make init-db SCHEMA=adm/seed_ci_dataset.sql
```

The script verifies that the file exists before importing it.

This mechanism is useful for:

* CI datasets
* demo databases
* integration testing

---

# 10. Database Backup

The database can be dumped using:

```
make docker-dump-db
```

This uses the variable:

```
DB_DUMP_FILE
```

Defined in:

```
conf/cashcue.conf
```

Example:

```
DB_DUMP_FILE=adm/seed_ci_dataset.sql
```

Running the command:

```
make docker-dump-db
```

Executes internally:

```
docker exec cashcue_db mariadb-dump \
  -u$DB_USER \
  -p$DB_PASS \
  $DB_NAME > $DB_DUMP_FILE
```

---

### Override dump location

You can override the dump file:

```
make docker-dump-db DB_DUMP_FILE=/tmp/test_dump.sql
```

---

# 11. Database Restore

To restore a database dump:

```
make init-db SCHEMA=adm/seed_ci_dataset.sql
```

The initialization script will import the dataset **only if the database is empty**.

If tables already exist, the schema import is skipped.

---

# 12. Runtime Layout (Inside Container)

Application container filesystem:

```
/data/cashcue           -> full project (mounted)

/data/cashcue/web       -> PHP frontend

/var/www/html/cashcue   -> Apache document root

/etc/cashcue/cashcue.conf -> configuration
```

---

# 13. Container Lifecycle

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

## Stop containers

```
make docker-stop
```

Runs:

```
docker compose stop
```

Containers stop but remain defined.

---

## Remove containers (keep DB)

```
make docker-down
```

Runs:

```
docker compose down
```

Containers are removed but **volumes remain intact**.

The database is preserved.

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

# 14. Quick Operational Commands

Start stack:

```
make docker-up
```

Initialize database:

```
make init-db
```

Create DB backup:

```
make docker-dump-db
```

Restore DB dataset:

```
make init-db SCHEMA=adm/seed_ci_dataset.sql
```

Stop containers:

```
make docker-stop
```

Remove containers:

```
make docker-down
```

Reset everything:

```
make docker-reset
```

---

# 15. Important Concept Summary

| Layer                 | Role                    |
| --------------------- | ----------------------- |
| Makefile              | orchestration           |
| docker-compose.yml    | container definition    |
| Dockerfile            | image build             |
| entrypoint.sh         | container startup logic |
| Apache                | web server runtime      |
| install_cashcue_db.sh | database initialization |

---

# 16. Mental Model

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

The project code is **mounted directly into the container**:

```
../:/data/cashcue
```

Which means:

* no rebuild required for code changes
* instant development feedback
* simplified development workflow

