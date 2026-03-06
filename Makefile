# =========================================================
# CashCue - Professional Dual-Mode Installation Makefile
#
# Supports:
#   MODE=native     -> Full system install (VM / bare metal)
#   MODE=container  -> Docker deployment
#
# Must be executed as root:
#   sudo make <target>
# =========================================================


# =========================================================
# Root Privilege Check
# =========================================================

ifeq ($(shell id -u),0)
    ROOT_OK := true
else
    $(error This Makefile must be run as root. Please use: sudo make <target>)
endif

# Check if CONFIG_FILE exists before proceeding with any target
ifeq ($(wildcard conf/cashcue.conf),)
	$(error Missing configuration file: conf/cashcue.conf. Please create it before running any targets.)
endif

# =========================================================
# Load environment configuration
# =========================================================

include conf/cashcue.conf
export

# =========================================================
# Execution Mode
# =========================================================

MODE ?= native

ifeq ($(MODE),container)
    CRON_ENABLED       = false
    LOGROTATE_ENABLED  = false
    SYSTEM_INSTALL     = false
else
    CRON_ENABLED       = true
    LOGROTATE_ENABLED  = true
    SYSTEM_INSTALL     = true
endif


# =========================================================
# Core Directories
# =========================================================

INSTALL_DIR     = /opt/cashcue
CONF_FILE	    = conf/cashcue.conf
VENV_DIR        = $(INSTALL_DIR)/venv
WEB_DIR         = /var/www/html/cashcue
CONFIG_DIR      = /etc/cashcue
CONFIG_FILE     = $(CONFIG_DIR)/cashcue.conf
CRON_FILE       = /etc/cron.d/cashcue
LOG_DIR         = /var/log/cashcue
VERSION_FILE    = $(INSTALL_DIR)/VERSION
LOGROTATE_FILE  = /etc/logrotate.d/cashcue

DOCKER_COMPOSE  = docker compose -f docker/docker-compose.yml --env-file conf/cashcue.conf
DOCKER_APP      = cashcue_app
DOCKER_DB       = cashcue_db

APACHE_SITE_NAME      = cashcue
APACHE_SITE_FILE      = /etc/apache2/sites-available/$(APACHE_SITE_NAME).conf
APACHE_CONF_NAME      = cashcue-security
APACHE_CONF_FILE      = /etc/apache2/conf-available/$(APACHE_CONF_NAME).conf

# =========================================================
# Environment Validation
# =========================================================

check-env:
ifndef DB_HOST
    $(error DB_HOST not defined in $(CONF_FILE))
endif
ifndef DB_PORT
    $(error DB_PORT not defined in $(CONF_FILE))
endif
ifndef DB_USER
    $(error DB_USER not defined in $(CONF_FILE))
endif
ifndef DB_PASS
    $(error DB_PASS not defined in $(CONF_FILE))
endif
ifndef DB_NAME
    $(error DB_NAME not defined in $(CONF_FILE))
endif


# =========================================================
# Versioning
# =========================================================
# If git is unavailable (production tarball), fallback to timestamp

GIT_VERSION := $(shell git describe --tags --always --dirty 2>/dev/null)
BUILD_DATE  := $(shell date +"%Y%m%d-%H%M%S")

ifeq ($(GIT_VERSION),)
    VERSION := release-$(BUILD_DATE)
else
    VERSION := $(GIT_VERSION)
endif


# =========================================================
# Default Target
# =========================================================

all: new-release

# =========================================================
# Apache Configuration
# =========================================================

install-apache-config:
ifeq ($(MODE),container)
	@echo "Apache config handled inside Docker image."
else
	@echo "Installing Apache configuration (native mode)..."
	cp conf/apache/vhost.conf $(APACHE_SITE_FILE)
	cp conf/apache/security.conf $(APACHE_CONF_FILE)

	a2enmod headers rewrite
	a2dissite 000-default.conf || true
	a2ensite $(APACHE_SITE_NAME)
	a2enconf $(APACHE_CONF_NAME)

	systemctl reload apache2
endif

# =========================================================
# Docker Development Environment
# =========================================================

docker-up:
	@echo "Starting Docker environment..."
	$(DOCKER_COMPOSE) up --build -d

docker-down:
	@echo "Stopping Docker environment..."
	$(DOCKER_COMPOSE) down -v

docker-reset:
	@echo "Resetting Docker environment (including volumes)..."
	$(DOCKER_COMPOSE) down -v
	$(DOCKER_COMPOSE) up --build -d


# =========================================================
# Python Backend Installation
# =========================================================

venv:
	@echo "Creating Python virtual environment..."
	mkdir -p $(INSTALL_DIR)
	python3 -m venv $(VENV_DIR)
	. $(VENV_DIR)/bin/activate && pip install --upgrade pip wheel
	. $(VENV_DIR)/bin/activate && pip install -r requirements.txt

install-backend: venv
	@echo "Installing backend..."
	rsync -a --delete app cashcue_core lib adm requirements.txt $(INSTALL_DIR)/


# =========================================================
# PHP Frontend Installation
# =========================================================

install-frontend:
	@echo "Installing frontend..."
	mkdir -p $(WEB_DIR)
	rsync -av --delete --exclude='*.log' --exclude='*.tmp' web/ $(WEB_DIR)/


# =========================================================
# Configuration check and installation
# =========================================================
check-config:
	@if [ ! -f conf/cashcue.conf ]; then \
		echo "Missing conf/cashcue.conf"; \
		exit 1; \
	fi

install-config:
	mkdir -p $(CONFIG_DIR)
	cp conf/cashcue.conf $(CONFIG_FILE)
	chown root:cashcue $(CONFIG_FILE) 2>/dev/null || true
	chmod 640 $(CONFIG_FILE)

# =========================================================
# Database Initialization
# =========================================================

init-db:
ifeq ($(MODE),container)
	$(DOCKER_COMPOSE) exec $(DOCKER_APP) bash /data/cashcue/adm/install_cashcue_db.sh
else
	bash adm/install_cashcue_db.sh
endif


# =========================================================
# Logging Setup
# =========================================================

secure-logs:
	mkdir -p $(LOG_DIR)
	chown root:cashcue $(LOG_DIR) 2>/dev/null || true
	chmod 750 $(LOG_DIR)


# =========================================================
# Cron Jobs
# =========================================================
cron:
ifeq ($(CRON_ENABLED),true)
	@echo "Installing cron jobs..."
	@printf "%s\n" \
	"# CashCue scheduled jobs" \
	"*/5 * * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_realtime_prices >> $(LOG_DIR)/realtime.log 2>&1" \
	"0 18 * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_daily_price >> $(LOG_DIR)/daily.log 2>&1" \
	"5 18 * * * root . $(VENV_DIR)/bin/activate && python3 -m app.update_portfolio_snapshot >> $(LOG_DIR)/snapshot.log 2>&1" \
	> $(CRON_FILE)
	chmod 644 $(CRON_FILE)
else
	@echo "Cron disabled (container mode)."
endif


# =========================================================
# Logrotate
# =========================================================

install-logrotate:
ifeq ($(LOGROTATE_ENABLED),true)
	@echo "Installing logrotate config..."
	@printf "%s\n" \
	"$(LOG_DIR)/*.log {" \
	"    daily" \
	"    rotate 14" \
	"    compress" \
	"    delaycompress" \
	"    missingok" \
	"    notifempty" \
	"    copytruncate" \
	"}" \
	> $(LOGROTATE_FILE)
	chmod 644 $(LOGROTATE_FILE)
else
	@echo "Logrotate disabled (container mode)."
endif


# =========================================================
# System Group
# =========================================================

system-group:
	@if ! getent group cashcue > /dev/null; then \
		groupadd --system cashcue; \
	fi


# =========================================================
# Version Tracking
# =========================================================

write-version:
	mkdir -p $(INSTALL_DIR)
	echo "$(VERSION)" > $(VERSION_FILE)
	@echo "Installed version: $(VERSION)"


# =========================================================
# Unified Release Pipeline
# =========================================================

new-release: system-group install-backend install-frontend install-config install-apache-config secure-logs write-version
ifeq ($(CRON_ENABLED),true)
	$(MAKE) cron install-logrotate
endif
	@echo "Release completed successfully."


# =========================================================
# Container Deployment
# =========================================================

deploy-container: start-container init-db

start-container:
	@echo "Starting CashCue Docker stack..."
	$(DOCKER_COMPOSE) up --build -d

# =========================================================
# Development Backend Mode
# =========================================================

run-dev: venv
	cd $(INSTALL_DIR) && \
	PYTHONPATH=$(INSTALL_DIR) \
	$(VENV_DIR)/bin/uvicorn cashcue_core.main:app \
	--reload --host 0.0.0.0 --port 8000


# =========================================================
# Uninstall
# =========================================================

uninstall:
	rm -rf $(INSTALL_DIR)
	rm -rf $(WEB_DIR)
	rm -rf $(CONFIG_DIR)
	rm -rf $(LOG_DIR)
	rm -f $(CRON_FILE)
	rm -f $(LOGROTATE_FILE)
	@echo "CashCue fully removed."


# =========================================================
# Help
# =========================================================

help:
	@echo ""
	@echo "CashCue - Professional Makefile"
	@echo ""
	@echo "  make new-release            -> Native full install"
	@echo "  make deploy-container       -> Docker deployment"
	@echo "  make init-db                -> Initialize database"
	@echo "  make docker-up              -> Start docker stack"
	@echo "  make docker-reset           -> Reset docker stack"
	@echo "  make uninstall              -> Remove installation"
	@echo ""