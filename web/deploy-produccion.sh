#!/bin/bash

# Script de despliegue para el hosting
# Este script actualiza el repositorio y copia las carpetas necesarias del frontend y backend

# Colores para mensajes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Directorios
REPO_DIR="/repo/web"
FRONTEND_SOURCE_DIR="/repo/frontend/web"
FRONTEND_DEST_DIR="/public_html/app"
BACKEND_SOURCE_DIR="/repo/backend/web"
BACKEND_DEST_DIR="/public_html/app/admin"

# Carpetas a copiar
FRONTEND_FOLDERS=("css" "custom-template" "images" "js")
BACKEND_FOLDERS=("css" "js" "images")

echo -e "${YELLOW}Iniciando despliegue del frontend y backend...${NC}"

# Paso 1: Git pull
echo -e "${YELLOW}Ejecutando git pull...${NC}"
cd "$REPO_DIR" || exit 1
if ! git pull; then
    echo -e "${RED}Error: git pull falló${NC}"
    exit 1
fi
echo -e "${GREEN}Git pull completado exitosamente${NC}"

# Paso 2: Verificar que los directorios fuente existen
if [ ! -d "$FRONTEND_SOURCE_DIR" ]; then
    echo -e "${RED}Error: El directorio fuente del frontend $FRONTEND_SOURCE_DIR no existe${NC}"
    exit 1
fi

if [ ! -d "$BACKEND_SOURCE_DIR" ]; then
    echo -e "${RED}Error: El directorio fuente del backend $BACKEND_SOURCE_DIR no existe${NC}"
    exit 1
fi

# Paso 3: Verificar que los directorios destino existen
if [ ! -d "$FRONTEND_DEST_DIR" ]; then
    echo -e "${RED}Error: El directorio destino del frontend $FRONTEND_DEST_DIR no existe${NC}"
    exit 1
fi

if [ ! -d "$BACKEND_DEST_DIR" ]; then
    echo -e "${RED}Error: El directorio destino del backend $BACKEND_DEST_DIR no existe${NC}"
    exit 1
fi

# ==========================================
# DESPLIEGUE DEL FRONTEND
# ==========================================
echo -e "${YELLOW}=== Desplegando Frontend ===${NC}"

# Paso 4: Borrar el contenido de la carpeta assets del frontend
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

# Paso 5: Copiar las carpetas especificadas del frontend
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
# DESPLIEGUE DEL BACKEND
# ==========================================
echo -e "${YELLOW}=== Desplegando Backend ===${NC}"

# Paso 6: Borrar el contenido de la carpeta assets del backend
BACKEND_ASSETS_DIR="$BACKEND_DEST_DIR/assets"
if [ -d "$BACKEND_ASSETS_DIR" ]; then
    echo -e "${YELLOW}Borrando contenido de la carpeta assets del backend...${NC}"
    if rm -rf "$BACKEND_ASSETS_DIR"/*; then
        echo -e "${GREEN}Contenido de assets del backend borrado exitosamente${NC}"
    else
        echo -e "${YELLOW}Advertencia: No se pudo borrar el contenido de assets del backend (puede estar vacía)${NC}"
    fi
else
    echo -e "${YELLOW}La carpeta assets del backend no existe, se omite la limpieza${NC}"
fi

# Paso 7: Copiar las carpetas especificadas del backend
echo -e "${YELLOW}Copiando carpetas del backend a $BACKEND_DEST_DIR...${NC}"
for folder in "${BACKEND_FOLDERS[@]}"; do
    SOURCE_PATH="$BACKEND_SOURCE_DIR/$folder"
    
    if [ ! -d "$SOURCE_PATH" ]; then
        echo -e "${YELLOW}Advertencia: La carpeta $folder no existe en $BACKEND_SOURCE_DIR, se omite${NC}"
        continue
    fi
    
    echo -e "  Copiando $folder..."
    if cp -r "$SOURCE_PATH" "$BACKEND_DEST_DIR/"; then
        echo -e "  ${GREEN}✓${NC} $folder copiada exitosamente"
    else
        echo -e "  ${RED}✗${NC} Error al copiar $folder"
        exit 1
    fi
done

echo -e "${GREEN}Despliegue completado exitosamente${NC}"

