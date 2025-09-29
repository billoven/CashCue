# =========================================================
# CashCue - Makefile
# =========================================================

# ------------------------
# Directories
# ------------------------
INSTALL_DIR = /opt/cashcue
VENV_DIR = $(INSTALL_DIR)/venv
CRON_FILE = /etc/cron.d/cashcue
WEB_DIR = /var/www/html/cashcue

# ------------------------
# Versioning
# ------------------------
VERSION := $(shell git describe --tags --always --dirty)
VERSION_FILE = $(INSTALL_DIR)/VERSION

# ------------------------
# Python
# ------------------------
PYTHON = python3
PIP = $(VENV_DIR)/bin/pip
PYTHON_BIN = $(VENV_DIR)/bin/python
CONFIG_FILE = /etc/cashcue/cashcue-dev.conf

# ------------------------
# Database config from .conf
# ------------------------
DB_USER := $(shell awk -F= '/DB_USER/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_PASS := $(shell awk -F= '/DB_PASS/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_HOST := $(shell awk -F= '/DB_HOST/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_PORT := $(shell awk -F= '/DB_PORT/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_NAME := $(shell awk -F= '/DB_NAME/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')

.PHONY: all install venv init-db pre-install write-version deploy upgrade cron clean uninstall help \
	install-config run-dev run-cron run-cron-realtime run-cron-daily run-cron-snapshot \
	create-db drop-db db-migrate db-upgrade test lint system-group secure-config secure-logs install-logrotate restart-service

# =========================================================
# Default target
# =========================================================
all: deploy

# =========================================================
# Python environment
# =========================================================
venv:
	@echo "Creating virtual environment in $(VENV_DIR)..."
	$(PYTHON) -m venv $(VENV_DIR)
	. $(VENV_DIR)/bin/activate && pip install --upgrade pip wheel
	. $(VENV_DIR)/bin/activate && pip install -r requirements.txt

install: venv
	@echo "Installing CashCue Python backend into $(INSTALL_DIR)..."
	mkdir -p $(INSTALL_DIR)
	cp -r app cashcue_core lib adm requirements.txt $(INSTALL_DIR)

# =========================================================
# Installation (PHP web app)
# =========================================================
install-web:
	@echo "Installing CashCue PHP web frontend into $(WEB_DIR)..."
	mkdir -p $(WEB_DIR)
	rsync -av --exclude='*.log' --exclude='*.tmp' --exclude='__pycache__' web/ $(WEB_DIR)/
	@echo "Web frontend deployed to $(WEB_DIR)"

# =========================================================
# Config file
# =========================================================
install-config:
	@if [ ! -f /etc/cashcue/cashcue.conf ]; then \
		echo "[INFO] Installing default config..."; \
		sudo mkdir -p /etc/cashcue; \
		sudo cp conf/cashcue.conf.template /etc/cashcue/cashcue.conf; \
		echo "[WARNING] Please edit /etc/cashcue/cashcue.conf with correct values!"; \
	else \
		echo "[INFO] Config already exists, skipping."; \
	fi

# =========================================================
# Database
# =========================================================
init-db:
	@echo "Running database initialization..."
	@bash adm/install_cashcue_db.sh

create-db:
	mysql -u$(DB_USER) -p$(DB_PASS) -h$(DB_HOST) -P$(DB_PORT) \
		-e "CREATE DATABASE IF NOT EXISTS $(DB_NAME) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

drop-db:
	mysql -u$(DB_USER) -p$(DB_PASS) -h$(DB_HOST) -P$(DB_PORT) \
		-e "DROP DATABASE IF EXISTS $(DB_NAME);"

db-migrate:
	alembic revision --autogenerate -m "Auto migration"

db-upgrade:
	alembic upgrade head

# =========================================================
# Versioning
# =========================================================
pre-install:
	@if [ -f $(VERSION_FILE) ]; then \
		echo "Currently installed version: $$(cat $(VERSION_FILE))"; \
	else \
		echo "No version currently installed."; \
	fi

write-version:
	@echo "$(VERSION)" > $(VERSION_FILE)
	@echo "Deployed version: $(VERSION)"

# =========================================================
# Cron jobs
# =========================================================
cron:
	@echo "Installing cron jobs..."
	@echo "# CashCue cron jobs" | sudo tee $(CRON_FILE) > /dev/null
	@echo "*/5 * * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_realtime_prices >> /var/log/cashcue/realtime.log 2>&1" | sudo tee -a $(CRON_FILE) > /dev/null
	@echo "0 18 * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_daily_price >> /var/log/cashcue/daily.log 2>&1" | sudo tee -a $(CRON_FILE) > /dev/null
	@echo "5 18 * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_portfolio_snapshot >> /var/log/cashcue/snapshot.log 2>&1" | sudo tee -a $(CRON_FILE) > /dev/null
	@sudo chmod 644 $(CRON_FILE)
	@echo "Cron jobs installed in $(CRON_FILE)"

run-cron: run-cron-realtime run-cron-daily run-cron-snapshot

run-cron-realtime: venv
	CONFIG_FILE=$(CONFIG_FILE) $(PYTHON_BIN) app/update_realtime_prices.py

run-cron-daily: venv
	CONFIG_FILE=$(CONFIG_FILE) $(PYTHON_BIN) app/update_daily_price.py

run-cron-snapshot: venv
	CONFIG_FILE=$(CONFIG_FILE) $(PYTHON_BIN) app/update_portfolio_snapshot.py

# =========================================================
# Logrotate
# =========================================================
install-logrotate:
	@echo "Installing logrotate config for CashCue..."
	@sudo bash -c 'cat > /etc/logrotate.d/cashcue <<EOF
		/var/log/cashcue/*.* {
		daily
		rotate 14
		compress
		delaycompress
		missingok
		notifempty
		copytruncate
		}
	EOF'
	@echo "Logrotate config installed at /etc/logrotate.d/cashcue"

# =========================================================
# System setup
# =========================================================
system-group:
	@echo "Ensuring 'cashcue' system group exists..."
	@if ! getent group cashcue > /dev/null; then \
		sudo groupadd --system cashcue; \
		echo "Group 'cashcue' created."; \
	else \
		echo "Group 'cashcue' already exists."; \
	fi

secure-config: install-config
	@echo "Securing configuration file..."
	sudo chown root:cashcue /etc/cashcue/cashcue.conf
	sudo chmod 640 /etc/cashcue/cashcue.conf
	@echo "Configuration secured (root:cashcue, 640)."

secure-logs:
	@echo "Securing log directory..."
	sudo mkdir -p /var/log/cashcue
	sudo chown root:cashcue /var/log/cashcue
	sudo chmod 750 /var/log/cashcue
	@echo "Log directory secured (root:cashcue, 750)."

# =========================================================
# Deployment
# =========================================================
deploy: pre-install system-group install install-web init-db write-version cron secure-config secure-logs install-logrotate
	@echo "Deployment completed successfully."

upgrade: pre-install
	@git pull --rebase
	$(MAKE) install
	$(MAKE) install-web
	$(MAKE) install-config
	$(MAKE) write-version
	$(MAKE) cron
	$(MAKE) install-logrotate
	@echo "Upgrade completed."

restart-service:
	sudo systemctl restart cashcue.service

# =========================================================
# Development
# =========================================================
run-dev: venv
	@echo "Starting FastAPI dev server..."
	cd $(INSTALL_DIR) && \
	PYTHONPATH=$(INSTALL_DIR) \
	$(VENV_DIR)/bin/uvicorn cashcue_core.main:app \
		--reload \
		--host 0.0.0.0 \
		--port 8000
	
# =========================================================
# Quality
# =========================================================
test: venv
	CONFIG_FILE=$(CONFIG_FILE) pytest -v --disable-warnings

lint: venv
	$(VENV_DIR)/bin/flake8 app
	$(VENV_DIR)/bin/black --check app

# =========================================================
# Cleanup
# =========================================================
clean:
	@echo "Cleaning build artifacts..."
	rm -rf __pycache__ */__pycache__ .pytest_cache .mypy_cache

# =========================================================
# Uninstall (dangerous!)
# =========================================================
uninstall:
	@echo "Removing CashCue installation..."
	rm -rf $(INSTALL_DIR)
	rm -rf $(WEB_DIR)
	rm -f $(CRON_FILE)
	@echo "CashCue fully uninstalled."

# =========================================================
# Help
# =========================================================
help:
	@echo "CashCue Makefile — available targets:"
	@echo ""
	@echo "Environment:"
	@echo "  make venv            -> Create Python virtual environment"
	@echo "  make install         -> Install Python backend"
	@echo ""
	@echo "Application:"
	@echo "  make run-dev         -> Run FastAPI app locally"
	@echo "  make run-cron        -> Run all cron jobs once"
	@echo "  make run-cron-realtime -> Run realtime prices update"
	@echo "  make run-cron-daily  -> Run daily prices update"
	@echo "  make run-cron-snapshot -> Run portfolio snapshot update"
	@echo ""
	@echo "Database:"
	@echo "  make init-db         -> Initialize database (script)"
	@echo "  make create-db       -> Create DB from config"
	@echo "  make drop-db         -> Drop DB from config"
	@echo "  make db-migrate      -> Generate Alembic migration"
	@echo "  make db-upgrade      -> Apply Alembic migrations"
	@echo ""
	@echo "Quality:"
	@echo "  make test            -> Run pytest suite"
	@echo "  make lint            -> Run flake8 + black"
	@echo ""
	@echo "Deployment:"
	@echo "  make deploy          -> Full deployment (backend + web + config + DB + cron + logrotate)"
	@echo "  make upgrade         -> Pull latest code and redeploy"
	@echo "  make restart-service -> Restart systemd service"
	@echo ""
	@echo "System setup:"
	@echo "  make install-config  -> Install default config"
	@echo "  make secure-config   -> Secure /etc/cashcue/cashcue.conf"
	@echo "  make secure-logs     -> Secure /var/log/cashcue"
	@echo "  make install-logrotate -> Install logrotate rules"
	@echo "  make system-group    -> Ensure 'cashcue' system group"
	@echo ""
	@echo "Misc:"
	@echo "  make clean           -> Clean Python caches and temp files"
	@echo "  make uninstall       -> Remove everything"
	@echo "  make help            -> Show this help message"

