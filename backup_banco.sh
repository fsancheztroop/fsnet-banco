#!/bin/bash

# Definir variables
SOURCE_DIR="/var/www/html/banco"  # Carpeta que quieres respaldar
BACKUP_DIR="/var/www/html/banco/backups"  # Carpeta donde almacenar los backups
TIMESTAMP=$(date +"%Y%m%d%H%M%S")  # Timestamp para el nombre del backup
BACKUP_NAME="backup_$TIMESTAMP.tar.gz"

# Crear el backup excluyendo la carpeta de backups
tar --exclude="banco/backups" -czf $BACKUP_DIR/$BACKUP_NAME -C /var/www/html banco

# Eliminar backups antiguos si hay más de 20
cd $BACKUP_DIR
ls -tp | grep -v '/$' | tail -n +20 | xargs -I {} rm -- {}

