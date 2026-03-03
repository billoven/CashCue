---

````markdown
# CashCue Installation and Update Guide

This guide summarizes how to install and update CashCue in **Docker** or on a **native Ubuntu VM**.

---

## 1️⃣ Docker Installation (From Scratch)

### Prerequisites
- Docker installed
- Docker Compose v2
- Git installed

### Steps

1. **Clone the repository**
```bash
git clone http://github.com/billoven/cashcue.git
cd cashcue
````

2. **Create environment file**

```bash
cp conf/.env.template conf/.env
nano conf/.env
```

Modify the database credentials and other required variables.

3. **Start Docker environment**

```bash
sudo make MODE=container docker-up
```

4. **Initialize database**

```bash
sudo make MODE=container init-db
```

5. **Verify containers**

```bash
docker ps
```

Access the application in a browser at:

```
http://<host>:8080/cashcue
```

6. **Check logs**

```bash
docker logs cashcue_app
docker logs cashcue_db
```

7. **Update / Reset**

```bash
# Update app without touching DB
sudo make MODE=container docker-up

# Full rebuild and restart (DB reset if volumes are removed)
sudo make MODE=container docker-reset
```

---

## 2️⃣ Native Ubuntu VM Installation (From Scratch)

### Prerequisites

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip mariadb-server apache2 rsync
```

### Steps

1. **Clone the repository**

```bash
git clone http://github.com/billoven/cashcue.git
cd cashcue
```

2. **Create environment file**

```bash
cp conf/.env.template conf/.env
nano conf/.env
```

3. **Run full installation**

```bash
sudo make new-release
```

This will:

* Create system group `cashcue`
* Create `/opt/cashcue` and Python virtual environment
* Install backend and frontend
* Generate configuration in `/etc/cashcue`
* Create `/var/log/cashcue`
* Write deployment version in `/opt/cashcue/VERSION`
* Install cron jobs (enabled)
* Install logrotate configuration (enabled)

4. **Initialize database**

```bash
sudo make init-db
```

5. **Verify services**

```bash
systemctl status apache2
systemctl status mariadb
```

6. **Access application**

```
http://<server-ip>/cashcue
```

---

## 3️⃣ Application Update (Without Reinstalling VM/Docker)

### Docker

```bash
# Update app without touching DB
sudo make MODE=container docker-up

# Full rebuild and restart (DB reset if volumes removed)
sudo make MODE=container docker-reset
```

### Native VM

```bash
git pull
sudo make new-release
```

Optional: update backend/frontend only without touching Python venv:

```bash
sudo make install-backend install-frontend write-version
```

---

## 4️⃣ Check Deployed Version

```bash
cat /opt/cashcue/VERSION
```

---

## 5️⃣ Full Uninstall (VM)

```bash
sudo make uninstall
```

> ⚠️ Note: This removes installation directories, logs, cron jobs, and configuration. Backup your database before uninstalling.

---

## 6️⃣ Notes

| Feature         | Docker                | VM                        |
| --------------- | --------------------- | ------------------------- |
| Install scratch | ✅ docker-up + init-db | ✅ new-release + init-db   |
| Update app      | ✅ docker-up           | ✅ new-release             |
| Reset complete  | ✅ docker-reset        | ✅ uninstall + new-release |
| Cron            | ❌ disabled            | ✅ enabled                 |
| Logrotate       | ❌ disabled            | ✅ enabled                 |

**Tip:** Always back up your database before upgrading or resetting the environment.

---

## 7️⃣ Important Notes About URLs

* CashCue PHP frontend resides in `/var/www/html/cashcue`.
* All links and routes are written with `/cashcue/...`.
* Do **not** change `DocumentRoot`. Access the app at:

```
http://<host>:8080/cashcue
```

Changing the DocumentRoot would break existing links unless the code is refactored.

---

## 8️⃣ ENV-Driven Configuration

* Docker containers use `--env-file conf/.env`.
* Native VM generates `/etc/cashcue/cashcue.conf` from the same `.env` file.
* No parsing of files inside the app; all environment variables are read via `getenv()` or `$_ENV`.
* This ensures a single source of truth, compatible with both Docker and VM deployment.

---

```

