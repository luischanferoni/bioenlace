#!/bin/bash
#
# usage: ./run.sh command [argument ...]
#
# Commands used during development / CI.
# Also, executable documentation for project dev practices.
#
# See https://death.andgravity.com/run-sh
# for an explanation of how it works and why it's useful.


# First, set up the environment.
# (Check the notes at the end when changing this.)

set -o nounset
set -o pipefail
set -o errexit

# Change the current directory to the project root.
PROJECT_ROOT=${0%/*}
if [[ $0 != $PROJECT_ROOT && $PROJECT_ROOT != "" ]]; then
    cd "$PROJECT_ROOT"
fi
readonly PROJECT_ROOT=$( pwd )

# Store the absolute path to this script (useful for recursion).
readonly SCRIPT="$PROJECT_ROOT/$( basename "$0" )"



# Commands follow.  - TAREAS DESDE AQUI -
PHP=$(which php)
LOG_FILE=/tmp/sisse_run_script.log

function migracion_turnos_consultas {    
    $PHP yii sisse/re-migracion;
}

function autofacturacion_mapear_sumar {
    # Ejemplos LINEA EN CRONTAB:
    #
    # Ejecutar todos los dias a las 21 hs
    # 0 21 * * * /var/www/html/run.sh autofacturacion_mapear_sumar > /tmp/sisse_crontab.log
    #
    # Modo pruebas, ejecutar cada 5 minutos:
    # */5 * * * * /var/www/html/run.sh autofacturacion_mapear_sumar > /tmp/sisse_crontab.log
    
    $PHP yii autofacturacion/mapear-sumar;
}


function test_run {
   echo "PROJECT DIR: "$PROJECT_ROOT > $LOG_FILE
   echo "CURRENT DATE: "$(date) >> $LOG_FILE
   echo "PHP_BIN: "$PHP >> $LOG_FILE
   echo "PHP-VERSION: "$($PHP -v |head -n 1) >> $LOG_FILE
   echo "Hola carola, testing." >> $LOG_FILE 
}

# Commands end. Dispatch to command. - NO TOCAR DESDE AQUI HACIA ABAJO -

"$@"


# Some dev notes for this script.
#
# The commands *require*:
#
# * The current working directory is the project root.
# * The shell options and globals are set as they are.
#
# Inspired by http://www.oilshell.org/blog/2020/02/good-parts-sketch.html
#
