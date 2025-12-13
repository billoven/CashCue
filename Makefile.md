Below is a clean, comprehensive **Makefile.md** that serves as a complete user manual for the Makefile you use to manage Cashcue deployments.

---

# Makefile Manual

**Cashcue Deployment and Maintenance Guide**

This document describes all available Makefile targets for building, installing, updating, and managing the Cashcue platform (backend & frontend).
It is intended for operators who deploy releases on servers or development environments.

---

## 1. Overview

The Makefile provides a unified interface to perform:

* Installation of dependencies
* Backend build & installation
* Frontend build & installation
* Service restart
* Release versioning
* Deployment of **specific Git releases**
* Deployment of the **latest Git release**
* Comparison between the currently installed release and available code in Git

The system stores the currently deployed version in:

```
/opt/cashcue/VERSION
```

This file is automatically updated after each successful release installation.

---

## 2. Core Targets

### 2.1 `install-latest`

Deploys the most recent version of the code from the current Git branch.

**What it does:**

* Fetches latest Git changes
* Checks out the current branch (usually main or master)
* Builds backend
* Builds frontend
* Updates the VERSION file
* Restarts the system service

**Usage:**

```
make install-latest
```

This is the equivalent of a standard upgrade operation.

---

### 2.2 `install-release`

Deploys a specific version, identified by a Git *tag*, *branch*, or *commit hash*.

**What it does:**

* Fetches tags and branches from the Git repository
* Checks out the user-specified Git reference
* Executes a complete release deployment (backend + frontend)
* Updates VERSION file
* Restarts the service

**Usage:**

```
make install-release RELEASE=v1.0.2
make install-release RELEASE=develop
make install-release RELEASE=4f3a1e2
```

Optional pre-check:

```
make install-release RELEASE=v1.0.2 CHECK=1
```

With `CHECK=1`, the Makefile will display the commit difference between the installed version and the requested release **before deploying**.

---

### 2.3 `new-release`

Internal target used by `install-latest` and `install-release`.

**What it does:**

* Installs backend
* Installs frontend
* Writes the release identifier into `/opt/cashcue/VERSION`
* Restarts the Cashcue service

It should not normally be called manually.

**Usage (not recommended):**

```
make new-release
```

---

## 3. Diagnostic & Information Targets

### 3.1 `check-gap`

Displays the commit difference between the **currently installed release** and the **latest Git version**.

**Outputs:**

* Installed release version
* Latest Git release (`git describe --tags --always`)
* Commit list between the two

**Usage:**

```
make check-gap
```

This is useful before deciding to upgrade.

---

### 3.2 `version`

Prints the currently installed release version.

**Usage:**

```
make version
```

---

## 4. Build Targets

### 4.1 `backend-install`

Installs backend dependencies and copies server-side files into the system path.

**Typical operations:**

* Create virtual environment (if applicable)
* Install Python dependencies
* Copy backend files to `/opt/cashcue/backend`
* Apply permissions

**Usage:**

```
make backend-install
```

---

### 4.2 `frontend-install`

Builds and installs the frontend application.

**Typical operations:**

* Install npm packages
* Build the production bundle
* Copy static files to `/opt/cashcue/frontend`

**Usage:**

```
make frontend-install
```

---

## 5. Service Control Targets

### 5.1 `restart`

Restarts the Cashcue systemd service.

**Usage:**

```
make restart
```

---

### 5.2 `start`

Starts the service.

```
make start
```

### 5.3 `stop`

Stops the service.

```
make stop
```

---

## 6. Versioning

The Makefile manages release versioning using a Git-based descriptor:

```
git describe --tags --always
```

This string is stored in:

```
/opt/cashcue/VERSION
```

Examples:

* `v1.0.0`
* `v1.1.2-3-g4a2c11f`
* `4f3a1e2`

Every successful release installation automatically updates this file.

---

## 7. Typical Usage Scenarios

### Scenario 1 — Deploy the latest official release

```
make install-latest
```

### Scenario 2 — Deploy a specific version (tag)

```
make install-release RELEASE=v1.0.4
```

### Scenario 3 — Deploy a test branch

```
make install-release RELEASE=feature/refactor-brokers
```

### Scenario 4 — Preview the commit gap before deploying

```
make install-release RELEASE=v1.0.4 CHECK=1
```

### Scenario 5 — Compare installed vs latest version

```
make check-gap
```

---

## 8. Notes and Best Practices

* Always run `check-gap` before performing production upgrades.
* Prefer deploying tags (`vX.Y.Z`) rather than branches for production systems.
* Keep a backup of `/opt/cashcue` before installing major releases.
* Never manually edit `/opt/cashcue/VERSION`.
* When deploying via `install-release`, ensure you have no uncommitted changes in your working tree.

---

