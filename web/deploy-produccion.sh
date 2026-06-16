#!/bin/bash

# Script de despliegue para el hosting
# Este script actualiza el repositorio, ejecuta las migraciones de BD y copia las carpetas necesarias del frontend y admin

# Colores para mensajes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Directorio base del hosting
BASE_DIR="/home/u257309594/domains/bioenlace.io"

# Directorios
REPO_DIR="$BASE_DIR/repo/web"
FRONTEND_SOURCE_DIR="$BASE_DIR/repo/web/frontend/web"
FRONTEND_DEST_DIR="$BASE_DIR/public_html/app"
ADMIN_SOURCE_DIR="$BASE_DIR/repo/web/admin/web"
ADMIN_DEST_DIR="$BASE_DIR/public_html/app/admin"

# Carpetas a copiar
FRONTEND_FOLDERS=("css" "custom-template" "images" "js")
ADMIN_FOLDERS=("css" "js" "images")

SKIP_COMPOSER=0
COMPOSER_DEV=0
for arg in "$@"; do
    case "$arg" in
        --skip-composer|--no-composer)
            SKIP_COMPOSER=1
            ;;
        --composer-dev|--with-dev)
            COMPOSER_DEV=1
            ;;
    esac
done

echo -e "${YELLOW}Iniciando despliegue del frontend y admin...${NC}"

# Paso 1: Git pull
echo -e "${YELLOW}Ejecutando git pull...${NC}"
cd "$REPO_DIR" || exit 1
if ! git pull; then
    echo -e "${RED}Error: git pull falló${NC}"
    exit 1
fi
echo -e "${GREEN}Git pull completado exitosamente${NC}"

# Paso 1.5: Composer (dependencias PHP)
if [ "$SKIP_COMPOSER" -eq 1 ]; then
    echo -e "${YELLOW}Omitiendo composer por parámetro (--skip-composer)${NC}"
else
    echo -e "${YELLOW}Instalando dependencias PHP (composer)...${NC}"
    cd "$REPO_DIR" || exit 1
    if [ "$COMPOSER_DEV" -eq 1 ]; then
        echo -e "${YELLOW}Composer: instalando dependencias DEV (incluye yii2-debug/gii)${NC}"
        COMPOSER_NO_DEV_FLAG=""
    else
        echo -e "${YELLOW}Composer: instalando dependencias PROD (--no-dev)${NC}"
        COMPOSER_NO_DEV_FLAG="--no-dev"
    fi
    if [ -f "composer.lock" ]; then
        if composer install --no-interaction --prefer-dist $COMPOSER_NO_DEV_FLAG --optimize-autoloader; then
            echo -e "${GREEN}Composer install completado exitosamente${NC}"
        else
            echo -e "${RED}Error: composer install falló${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}Advertencia: composer.lock no existe; ejecutando composer update...${NC}"
        if composer update --no-interaction --prefer-dist $COMPOSER_NO_DEV_FLAG --optimize-autoloader; then
            echo -e "${GREEN}Composer update completado exitosamente${NC}"
        else
            echo -e "${RED}Error: composer update falló${NC}"
            exit 1
        fi
    fi
fi

# Paso 2: Ejecutar migraciones
echo -e "${YELLOW}Ejecutando migraciones de base de datos...${NC}"
if php yii migrate --migrationPath=@common/migrations --interactive=0; then
    echo -e "${GREEN}Migraciones completadas exitosamente${NC}"
else
    echo -e "${RED}Error: Las migraciones fallaron${NC}"
    exit 1
fi

# Paso 3: Verificar que los directorios fuente existen
if [ ! -d "$FRONTEND_SOURCE_DIR" ]; then
    echo -e "${RED}Error: El directorio fuente del frontend $FRONTEND_SOURCE_DIR no existe${NC}"
    exit 1
fi

if [ ! -d "$ADMIN_SOURCE_DIR" ]; then
    echo -e "${RED}Error: El directorio fuente del admin $ADMIN_SOURCE_DIR no existe${NC}"
    exit 1
fi

# Paso 4: Verificar que los directorios destino existen
if [ ! -d "$FRONTEND_DEST_DIR" ]; then
    echo -e "${RED}Error: El directorio destino del frontend $FRONTEND_DEST_DIR no existe${NC}"
    exit 1
fi

if [ ! -d "$ADMIN_DEST_DIR" ]; then
    echo -e "${RED}Error: El directorio destino del admin $ADMIN_DEST_DIR no existe${NC}"
    exit 1
fi

# ==========================================
# DESPLIEGUE DEL FRONTEND
# ==========================================
echo -e "${YELLOW}=== Desplegando Frontend ===${NC}"

# Paso 5: Borrar el contenido de la carpeta assets del frontend
FRONTEND_ASSETS_DIR="$FRONTEND_DEST_DIR/assets"
if [ -d "$FRONTEND_ASSETS_DIR" ]; then
    echo -e "${YELLOW}Borrando contenido de la carpeta assets del frontend...${NC}"
    if rm -rf "$FRONTEND_ASSETS_DIR"/*; then
        echo -e "${GREEN}Contenido de assets del frontend borrado exitosamente${NC}"
    else
        echo -e "${YELLOW}Advertencia: No se pudo borrar el contenido de assets del frontend (puede estar vacía)${NC}"
    fi
else
    echo -e "${YELLOW}La carpeta assets del frontend no existe, se omite la limpieza${NC}"
fi

# Paso 6: Copiar las carpetas especificadas del frontend
echo -e "${YELLOW}Copiando carpetas del frontend a $FRONTEND_DEST_DIR...${NC}"
for folder in "${FRONTEND_FOLDERS[@]}"; do
    SOURCE_PATH="$FRONTEND_SOURCE_DIR/$folder"
    
    if [ ! -d "$SOURCE_PATH" ]; then
        echo -e "${YELLOW}Advertencia: La carpeta $folder no existe en $FRONTEND_SOURCE_DIR, se omite${NC}"
        continue
    fi
    
    echo -e "  Copiando $folder..."
    if cp -r "$SOURCE_PATH" "$FRONTEND_DEST_DIR/"; then
        echo -e "  ${GREEN}✓${NC} $folder copiada exitosamente"
    else
        echo -e "  ${RED}✗${NC} Error al copiar $folder"
        exit 1
    fi
done

# ==========================================
# DESPLIEGUE DEL ADMIN
# ==========================================
echo -e "${YELLOW}=== Desplegando Admin ===${NC}"

# Paso 7: Borrar el contenido de la carpeta assets del admin
ADMIN_ASSETS_DIR="$ADMIN_DEST_DIR/assets"
if [ -d "$ADMIN_ASSETS_DIR" ]; then
    echo -e "${YELLOW}Borrando contenido de la carpeta assets del admin...${NC}"
    if rm -rf "$ADMIN_ASSETS_DIR"/*; then
        echo -e "${GREEN}Contenido de assets del admin borrado exitosamente${NC}"
    else
        echo -e "${YELLOW}Advertencia: No se pudo borrar el contenido de assets del admin (puede estar vacía)${NC}"
    fi
else
    echo -e "${YELLOW}La carpeta assets del admin no existe, se omite la limpieza${NC}"
fi

# Paso 8: Copiar las carpetas especificadas del admin
echo -e "${YELLOW}Copiando carpetas del admin a $ADMIN_DEST_DIR...${NC}"
for folder in "${ADMIN_FOLDERS[@]}"; do
    SOURCE_PATH="$ADMIN_SOURCE_DIR/$folder"
    
    if [ ! -d "$SOURCE_PATH" ]; then
        echo -e "${YELLOW}Advertencia: La carpeta $folder no existe en $ADMIN_SOURCE_DIR, se omite${NC}"
        continue
    fi
    
    echo -e "  Copiando $folder..."
    if cp -r "$SOURCE_PATH" "$ADMIN_DEST_DIR/"; then
        echo -e "  ${GREEN}✓${NC} $folder copiada exitosamente"
    else
        echo -e "  ${RED}✗${NC} Error al copiar $folder"
        exit 1
    fi
done

echo -e "${GREEN}Despliegue completado exitosamente${NC}"

