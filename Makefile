# ========================
# CashCue Makefile
# ========================

# Directories
INSTALL_DIR = /opt/cashcue
VENV_DIR = $(INSTALL_DIR)/venv
CRON_FILE = /etc/cron.d/cashcue
WEB_DIR = /var/www/html/cashcue

# Versioning
VERSION := $(shell git describe --tags --always --dirty)
VERSION_FILE = $(INSTALL_DIR)/VERSION

# Python
PYTHON = python3
PIP = $(VENV_DIR)/bin/pip

.PHONY: all install venv init-db pre-install write-version deploy upgrade cron clean uninstall help install-config

# ------------------------
# Default target
# ------------------------
all: deploy

# ------------------------
# Python environment
# ------------------------
venv:
	@echo "Creating virtual environment in $(VENV_DIR)..."
	$(PYTHON) -m venv $(VENV_DIR)
	. $(VENV_DIR)/bin/activate && pip install --upgrade pip
	. $(VENV_DIR)/bin/activate && pip install -r requirements.txt

# ------------------------
# Installation (Python code)
# ------------------------
install: venv
	@echo "Installing CashCue Python backend into $(INSTALL_DIR)..."
	mkdir -p $(INSTALL_DIR)
	cp -r app lib adm requirements.txt $(INSTALL_DIR)

# ------------------------
# Installation (PHP web app)
# ------------------------
install-web:
	@echo "Installing CashCue PHP web frontend into $(WEB_DIR)..."
	mkdir -p $(WEB_DIR)
	rsync -av --exclude='*.log' --exclude='*.tmp' --exclude='__pycache__' web/ $(WEB_DIR)/
	@echo "Web frontend deployed to $(WEB_DIR)"

# ------------------------
# Config file
# ------------------------
install-config:
	@if [ ! -f /etc/cashcue/cashcue.conf ]; then \
		echo "[INFO] Installing default config..."; \
		sudo mkdir -p /etc/cashcue; \
		sudo cp conf/cashcue.conf.template /etc/cashcue/cashcue.conf; \
		echo "[WARNING] Please edit /etc/cashcue/cashcue.conf with correct values!"; \
	else \
		echo "[INFO] Config already exists, skipping."; \
	fi

# ------------------------
# Database
# ------------------------
init-db:
	@echo "Running database initialization..."
	@bash adm/install_cashcue_db.sh

# ------------------------
# Versioning
# ------------------------
pre-install:
	@if [ -f $(VERSION_FILE) ]; then \
		echo "Currently installed version: $$(cat $(VERSION_FILE))"; \
	else \
		echo "No version currently installed."; \
	fi

write-version:
	@echo "$(VERSION)" > $(VERSION_FILE)
	@echo "Deployed version: $(VERSION)"

# ------------------------
# Cron jobs
# ------------------------
cron:
	@echo "Installing cron jobs..."
	@echo "# CashCue cron jobs" > $(CRON_FILE)
	@echo "*/5 * * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_realtime_prices >> /var/log/cashcue/realtime.log 2>&1" >> $(CRON_FILE)
	@echo "0 18 * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_daily_price >> /var/log/cashcue/daily.log 2>&1" >> $(CRON_FILE)
	@echo "5 18 * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_portfolio_snapshot >> /var/log/cashcue/snapshot.log 2>&1" >> $(CRON_FILE)
	@chmod 644 $(CRON_FILE)
	@echo "Cron jobs installed in $(CRON_FILE)"

# ------------------------
# Logrotate
# ------------------------
install-logrotate:
	@echo "Installing logrotate config for CashCue..."
	@sudo tee /etc/logrotate.d/cashcue > /dev/null <<'EOF'
/var/log/cashcue/*.* {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
EOF
	@echo "Logrotate config installed at /etc/logrotate.d/cashcue"


# ------------------------
# System setup
# ------------------------
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

# ------------------------
# Deployment
# ------------------------
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

# ------------------------
# Cleanup
# ------------------------
clean:
	@echo "Cleaning build artifacts..."
	rm -rf __pycache__ */__pycache__ .pytest_cache .mypy_cache

# ------------------------
# Uninstall (dangerous!)
# ------------------------
uninstall:
	@echo "Removing CashCue installation..."
	rm -rf $(INSTALL_DIR)
	rm -rf $(WEB_DIR)
	rm -f $(CRON_FILE)
	@echo "CashCue fully uninstalled."

# ------------------------
# Help
# ------------------------
# ------------------------
# Help
# ------------------------
help:
	@echo "CashCue Makefile — available targets:"
	@echo "  make all             -> Deploy everything (default target)"
	@echo "  make venv            -> Create Python virtual environment"
	@echo "  make install         -> Install Python backend"
	@echo "  make install-web     -> Install PHP web frontend"
	@echo "  make install-config  -> Install default config in /etc/cashcue if missing"
	@echo "  make init-db         -> Initialize database"
	@echo "  make pre-install     -> Show currently installed version"
	@echo "  make write-version   -> Write current Git version to VERSION file"
	@echo "  make cron            -> Install cron jobs"
	@echo "  make deploy          -> Full deployment (backend + frontend + config + DB + cron + logrotate)"
	@echo "  make upgrade         -> Pull latest code and redeploy"
	@echo "  make install-logrotate -> Install logrotate rules for /var/log/cashcue/*.*"
	@echo "  make clean           -> Clean Python caches and temp files"
	@echo "  make uninstall       -> Remove all installed files"
	@echo "  make help            -> Show this help message"
