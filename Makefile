# =========================================================
# CashCue - Makefile (Enhanced Release Management)
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
CONFIG_FILE = /etc/cashcue/cashcue.conf

# ------------------------
# Database config from .conf
# ------------------------
DB_USER := $(shell awk -F= '/DB_USER/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_PASS := $(shell awk -F= '/DB_PASS/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_HOST := $(shell awk -F= '/DB_HOST/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_PORT := $(shell awk -F= '/DB_PORT/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')
DB_NAME := $(shell awk -F= '/DB_NAME/ {print $$2}' $(CONFIG_FILE) | tr -d ' ')

.PHONY: all install venv init-db pre-install write-version deploy new-release \
	check-gap install-release install-latest \
	install-web install-config cron install-logrotate \
	system-group secure-config secure-logs restart-service \
	run-dev run-cron run-cron-realtime run-cron-daily run-cron-snapshot \
	test lint clean uninstall help

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
# Web frontend (PHP)
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
		echo "[INFO] Please edit /etc/cashcue/cashcue.conf with new values if needed!"; \
	fi

# =========================================================
# Database (not touched by releases)
# =========================================================
init-db:
	@echo "Running database initialization..."
	@bash adm/install_cashcue_db.sh

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
# Release comparison (gap)
# =========================================================
check-gap:
	@if [ -z "$(RELEASE)" ]; then \
		echo "Usage: make check-gap RELEASE=vX.Y.Z"; exit 1; \
	fi
	@if [ -f $(VERSION_FILE) ]; then \
		INSTALLED=$$(cat $(VERSION_FILE)); \
		echo "Installed release: $$INSTALLED"; \
		echo "Target release:    $(RELEASE)"; \
		echo ""; \
		echo "Commit differences:"; \
		git fetch --all --tags >/dev/null 2>&1; \
		git log --oneline $$INSTALLED..$(RELEASE); \
	else \
		echo "[WARN] No installed version file found."; \
	fi

# =========================================================
# Unified release installation pipeline
# =========================================================
new-release: system-group install install-web write-version cron secure-config secure-logs install-logrotate
	@echo "Release installation completed (backend + frontend)."

# =========================================================
# Install a specific release (tag, branch, commit)
# =========================================================
install-release:
	@if [ -z "$(RELEASE)" ]; then \
		echo "ERROR: You must specify a release: make install-release RELEASE=v1.0.1"; \
		exit 1; \
	fi
	@echo "Fetching latest tags..."
	git fetch --all --tags
	@echo "Checking out release $(RELEASE)..."
	git checkout $(RELEASE)
	$(MAKE) new-release
	@echo "Installed release: $(RELEASE)"

# =========================================================
# Install latest version (replaces upgrade)
# =========================================================
install-latest:
	@echo "Fetching latest changes..."
	git pull --rebase
	$(MAKE) new-release
	@echo "System upgraded to latest version."

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

# =========================================================
# Logrotate
# =========================================================
install-logrotate:
	@echo "Installing logrotate config for CashCue..."
	@printf "%s\n" \
	"/var/log/cashcue/*.* {" \
	"	daily" \
	"	rotate 14" \
	"	compress" \
	"	delaycompress" \
	"	missingok" \
	"	notifempty" \
	"	copytruncate" \
	"}" \
	| sudo tee /etc/logrotate.d/cashcue > /dev/null
	@sudo chmod 644 /etc/logrotate.d/cashcue
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
# Dev mode
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
# Uninstall
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
	@echo "  make install-latest               -> Install newest version from Git"
	@echo "  make install-release RELEASE=x    -> Install specific tag/branch/commit"
	@echo "  make check-gap RELEASE=x          -> Compare installed vs. target release"
	@echo ""
	@echo "  make deploy                       -> Full install (initial deployment)"
	@echo "  make run-dev                      -> Run backend in dev mode"
	@echo "  make uninstall                    -> Remove everything"
