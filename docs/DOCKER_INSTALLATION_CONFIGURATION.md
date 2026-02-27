
# Docker User Guide on Ubuntu 24.04

This guide explains how to install and configure Docker on **Ubuntu 24.04** for end users or system administrators. It covers installation, basic configuration, running containers, and uninstalling Docker if needed.

---

## Table of Contents

1. [Introduction](#introduction)  
2. [Prerequisites](#prerequisites)  
3. [Installing Docker](#installing-docker)  
   - [Remove Old Versions](#remove-old-versions)  
   - [Install Dependencies](#install-dependencies)  
   - [Add Docker GPG Key](#add-docker-gpg-key)  
   - [Add Docker Repository](#add-docker-repository)  
   - [Install Docker Engine and Compose](#install-docker-engine-and-compose)  
   - [Add User to Docker Group](#add-user-to-docker-group)  
4. [Verifying Installation](#verifying-installation)  
5. [Running Your First Container](#running-your-first-container)  
6. [Basic Docker Commands](#basic-docker-commands)  
7. [Uninstalling Docker](#uninstalling-docker)  
8. [References](#references)  

---

## Introduction

Docker is a containerization platform that allows you to package and run applications in isolated environments. On Ubuntu, Docker simplifies deploying applications and testing software like CashCue without installing dependencies directly on the host system.

Benefits for end users:

- Run applications in isolated containers
- Avoid dependency conflicts
- Easily create reproducible environments

---

## Prerequisites

- Ubuntu 24.04 installed with sudo privileges
- Internet connection to download Docker packages
- Recommended: 2 GB RAM minimum for basic containers

---

## Installing Docker

### Remove Old Versions

Remove any older Docker packages to prevent conflicts:

```bash
sudo apt remove docker docker-engine docker.io containerd runc
````

### Install Dependencies

Install required packages:

```bash
sudo apt update
sudo apt install ca-certificates curl gnupg lsb-release
```

### Add Docker GPG Key

```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```

### Add Docker Repository

```bash
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

### Install Docker Engine and Compose

```bash
sudo apt update
sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### Add User to Docker Group

Add your user to the Docker group to run Docker without `sudo`:

```bash
sudo usermod -aG docker $USER
newgrp docker
```

---

## Verifying Installation

Check that Docker is installed correctly:

```bash
docker version
docker compose version
```

Run the Hello World container:

```bash
docker run hello-world
```

If successful, you will see a confirmation message.

---

## Running Your First Container

Start an interactive Ubuntu container:

```bash
docker run -it ubuntu bash
```

Inside the container, you can run commands like in a normal Ubuntu system. Exit with:

```bash
exit
```

---

## Basic Docker Commands

| Command                            | Description               |
| ---------------------------------- | ------------------------- |
| `docker ps`                        | List running containers   |
| `docker ps -a`                     | List all containers       |
| `docker images`                    | List downloaded images    |
| `docker stop <container>`          | Stop a container          |
| `docker rm <container>`            | Remove a container        |
| `docker rmi <image>`               | Remove an image           |
| `docker logs <container>`          | Show container logs       |
| `docker exec -it <container> bash` | Enter a running container |

---

## Uninstalling Docker

To remove Docker completely:

```bash
sudo systemctl stop docker
sudo apt purge docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo rm -rf /var/lib/docker
sudo rm -rf /var/lib/containerd
sudo rm -rf /etc/docker
sudo rm -rf ~/.docker
```

This will remove Docker, all containers, images, volumes, and user configurations.

---

## References

* [Docker Installation Guide for Ubuntu](https://docs.docker.com/engine/install/ubuntu/)
* [Docker Compose Documentation](https://docs.docker.com/compose/)
* [Ubuntu Server 24.04 LTS](https://ubuntu.com/download/server)

---

*Prepared for Ubuntu 24.04 users installing Docker for development or testing purposes.*
