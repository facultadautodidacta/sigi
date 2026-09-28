# LAB-03 — IDOR en la ficha de empleado

| Campo | Valor |
|---|---|
| **ID** | LAB-03 |
| **Tipo** | IDOR — Referencia directa a objeto insegura (autorización horizontal) |
| **Riesgo** | Alto |
| **CWE** | CWE-639 (Authorization Bypass Through User-Controlled Key) · CWE-284 (Improper Access Control) |
| **OWASP** | A01:2021 — Broken Access Control |
| **Endpoint** | `GET /fa/sistemas/employees/view.php?id=` |
| **Acceso** | Requiere sesión iniciada (cualquier rol, incluido Consulta) |
| **Implementada** | 26 de septiembre de 2026 |

> **Esta ficha es distinta a las demás.** En LAB-01, LAB-02, LAB-04, LAB-05,
> LAB-06 y LAB-07 el ataque es un **payload**: hay una cadena concreta en la
> petición que una herramienta puede reconocer. Aquí no hay payload. La petición
> es un `GET` a una URL válida con un número válido, y quien la hace está
> autenticado. Por eso el Escenario B de esta práctica **sigue vulnerable** y la
> matriz lo reporta aparte, en la línea `No cubiertos por el WAF`. No es un
> defecto de la demostración: es el límite real de la herramienta, y la sección 6
> está escrita para demostrarlo.

---

## 1. Qué hace

La ficha de un empleado se abre con `?id=N`. La página carga esa fila completa
con un `SELECT *` y la imprime, **sin comprobar el rol de quien está mirando**.
El único control que existe es `require_login()`: que haya sesión. Y como el rol
más bajo de la aplicación es *Consulta* (3), que por definición solo debe leer,
cualquier usuario autenticado puede recorrer `id=1` a `id=22` y leer los datos
personales y las notas internas de RRHH de **todas** las personas de la empresa.

Los tres campos expuestos son:

| Campo | Qué contiene | Dónde se imprime |
|---|---|---|
| `employees.email` | Correo institucional | Tarjeta "Datos personales" |
| `employees.telefono` | Teléfono de la oficina | Tarjeta "Datos personales" |
| `employees.notas_confidenciales` | Notas internas de RRHH | Tarjeta "Notas de RRHH" |

Las dos tarjetas llevan la insignia `Restringido` en rojo, y esa insignia es la
mejor noticia de esta ficha: **la aplicación sabe que esos datos son
restringidos y lo dice en pantalla, pero no lo restringe.** La etiqueta es
documentación, no control de acceso.

El contenido de las notas no es decorativo. En el seed hay quién es titular de la
cuenta de dominio de la empresa, quién es el único con acceso al hipervisor y
dónde está la custodia de las claves, quién concentra los pagos a proveedores,
quién tiene la firma digital habilitada ante el SAT y quién autoriza las compras
del sistema financiero. Son datos que en una empresa real tienen protección
legal y contractual, y que aquí salen completos con veintidós peticiones
seguidas, una por empleado.

No hay carga útil, no hay cadena que decodificar y no hay error que provocar.
Lo único que hace el atacante es escribir un número distinto en la URL. Por eso
esta vulnerabilidad no se "explota" con herramientas: se recorre.

---

## 2. Dónde está el código

### 2.1 El único control que se aplica

`employees/view.php`, líneas 36 a 40:

```php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$id = (int) input_get('id');
```

`require_login()` está en `config/app.php`, línea 253, y hace exactamente una
cosa:

```php
function require_login()
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url('login.php'));
        exit;
    }
}
```

Eso es **autenticación**: que la persona sea quien dice ser. Lo que falta es
**autorización**: que esa persona pueda ver *este* registro. La página nunca
pregunta por el rol, en ningún punto de su flujo.

### 2.2 La carga sin filtro de rol

`employees/view.php`, línea 48:

```php
$emp = db_row("SELECT * FROM employees WHERE id = ?", array($id));
```

Conviene ser preciso aquí, porque es tentador leer otra cosa en esta línea: **no
hay inyección SQL**. El identificador viaja como parámetro vinculado y además
pasa por un `(int)`. La consulta es correcta desde el punto de vista de la
sintaxis.

Lo que hace el `SELECT *` es traer **todas** las columnas de la fila, incluidas
las dos que no deberían salir de la base para este usuario. La vulnerabilidad no
está en cómo se ejecuta la consulta, está en que **no se filtra el resultado**
antes de imprimirlo.

### 2.3 El bloque restringido que se imprime

`employees/view.php`, líneas 204 a 229:

```php
<div class="card mb-3 border-danger">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Datos personales</span>
        <span class="badge bg-danger">Restringido</span>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="detail-label">Correo institucional</div>
            <div class="detail-value small"><?php echo e($emp['email']); ?></div>
        </div>
        <div class="mb-3">
            <div class="detail-label">Telefono</div>
            <div class="detail-value"><?php echo e($emp['telefono']); ?></div>
        </div>
    </div>
</div>

<div class="card border-danger">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Notas de RRHH</span>
        <span class="badge bg-danger">Restringido</span>
    </div>
    <div class="card-body">
        <p class="mb-0"><?php echo e($emp['notas_confidenciales']); ?></p>
    </div>
</div>
```

El comentario del archivo, líneas 195 a 203, lo admite sin rodeos:

```php
// BLOQUE RESTRINGIDO - VULNERABLE (LAB-03)
//
// Este bloque deberia renderizarse solo para Administrador. No
// hay ninguna comprobacion de rol: cualquiera con sesion lo ve.
// Iterando ?id=1..22 un usuario de rol Consulta obtiene el
// directorio completo de datos personales de la empresa.
```

El escapado sí está: los tres valores pasan por `e()`. Esta página no tiene un
XSS, y conviene decirlo porque lo contrario se confunde: la única falla aquí es
la de autorización.

### 2.4 El esquema admite que los datos son restringidos

`database/schema.sql`, líneas 116 y 117:

```sql
`telefono`        VARCHAR(30) DEFAULT NULL COMMENT 'LAB-03: dato personal restringido',
`notas_confidenciales` TEXT DEFAULT NULL COMMENT 'LAB-03: notas internas de RRHH, restringidas a Administrador',
```

Los comentarios de la tabla son la especificación del control que falta: las
notas están *"restringidas a Administrador"*. La restricción está escrita en el
esquema y en la tarjeta de la vista, y no está en ninguna línea de PHP.

### 2.5 La corrección que ya existe y no se llama

`config/app.php`, líneas 265 a 274:

```php
function require_role($roles)
{
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);
        include DOC_ROOT . '/403.php';
        exit;
    }
}
```

`has_role()` (línea 237) compara el rol de la sesión contra una lista, e
`is_admin()` (línea 245) es `has_role(1)`. La función que cierra exactamente
este hueco ya está escrita, con su página de error (`403.php`) y todo.

Hay dos formas correctas de aplicarla, según qué se quiera proteger:

**a) La ficha completa para Administrador.** Una línea, sustituyendo la actual:

```php
require_role(1);   // en lugar de require_login()
```

Es la forma que describe el comentario de cabecera del propio archivo (líneas
18 a 24). Es simple, pero cierra también los equipos asignados, que sí conviene
que vea un Técnico.

**b) Solo el bloque restringido.** Se mantiene la ficha para todos los roles
autenticados y se envuelve el HTML sensible:

```php
<?php if (has_role(1)): ?>
    ...las dos tarjetas con la insignia "Restringido"...
<?php endif; ?>
```

Esta es la que corresponde al caso de negocio real: el directorio y el
inventario son de lectura para todos; el teléfono y las notas de RRHH no.

Y hay una tercera forma, que es la que el nombre IDOR sugiere y que conviene
dejar escrita: validar que **el recurso pertenece al usuario autenticado**. En
esta aplicación no se puede hacer, y es honesto decirlo: la tabla `employees`
(`database/schema.sql`, líneas 108 a 126) no tiene ninguna columna que la ligue
con `users`. No existe relación entre "la cuenta que inició sesión" y "la ficha
que se está abriendo", así que la variante horizontal puro necesitaría una tabla
nueva. Lo que el laboratorio demuestra es la variante vertical —el rol no se
comprueba sobre un identificador que elige el cliente—, que es el mismo
CWE-639 con la misma consecuencia.

### 2.6 La bitácora: la evidencia que sí queda

`employees/view.php`, líneas 78 y 79:

```php
audit_log('VIEW', 'employees',
    ' consulted la ficha del empleado ' . $emp['numero_empleado'] . ' (id ' . $id . ')');
```

Cada ficha abierta deja una fila en `audit_log` con usuario, IP y hora. El
comentario de las líneas 75 a 77 lo dice pensando en la demostración:

```php
// El acceso a la ficha queda registrado. En el Escenario A (sin WAF) esta
// linea se acumula por cada id recorrido; con SafeLine tampoco cambia, y
// eso es precisamente lo que hay que señalar en el video.
```

Ese "tampoco cambia" es el dato importante del Escenario B de esta ficha, y
por eso la auditoría se usa aquí como **control de detección**: no evita la
filtración, pero deja el rastro completo de la enumeración. En la sección 5 se
muestra cómo verlo.

(Detalle menor, verificado: la descripción de la fila empieza con un espacio, un
` consulted` con espacio inicial. Es consecuencia de partir la cadena en dos
líneas en la llamada. No importa, pero si se filtra la bitácora por texto
conviene incluir ese espacio en el patrón.)

---

## 3. Por qué existe en una app real

No es un descuido de teclear. Es la forma en que crece un módulo que funciona.

`employees/index.php` —el directorio— es un listado de solo lectura: número de
empleado, nombre, puesto, departamento, cantidad de equipos y estado. Está bien
pensado, y su propio comentario lo dice: *"Vista publica: cualquier usuario
autenticado puede consultar el directorio. Los datos personales no se muestran
aqui, solo en la ficha."* Es decir: **el autor sabía que los datos personales
existen y decidió que vivían en otro lado.** La separación era correcta.

La ficha (`employees/view.php`) nació para un solo propósito: ver qué equipos
tiene asignados una persona. Para eso el bloque de datos personales sobra, y las
tarjetas de "Datos personales" y "Notas de RRHH" se añadieron después, cuando
alguien pidió ver el teléfono de un empleado o la nota que había dejado RRHH.

Ese es el momento en que entró la vulnerabilidad, y entró como lo que era en ese
momento: **una tarjeta más en una página que ya funcionaba.** Quien la añadió
probablemente se probó la página con su propia cuenta de Administrador, vio que
la tarjeta se veía bien, y la dio por terminada. La comprobación de rol se
sintió innecesaria porque, entrando como administrador, la tarjeta se ve
exactamente igual que sin comprobación.

Es el patrón más repetido del laboratorio, y el más difícil de encontrar por
auditoría: la funcionalidad nueva es legítima, los datos son reales, y la página
funciona para quien la escribió. Lo que falta no es una validación de entrada,
es una decisión sobre **quién puede ver qué**. Y esa decisión nunca se escribió
en ninguna línea.

Un detalle del entorno lo hace más creíble todavía: el menú lateral sí aplica el
control de rol (`includes/header.php`, línea 207, con `has_role($item['roles'])`),
y el ítem de Empleados está declarado para los roles 1, 2 y 3
(`includes/header.php`, líneas 58 a 64). Es decir, **el sistema de roles está
funcionando y es correcto en el resto de la aplicación**: `users/`,
`audit/index.php`, `equipment/create.php`, `assignments/create.php` y
`lab/subidas.php` llaman a `require_role()`. La ausencia es de esta página y de
este bloque. Un menú que esconde un enlace no es un mecanismo de seguridad: si
conoces la URL, el enlace da igual.

---

## 4. Pasos de reproducción

Probado contra `http://localhost/fa/sistemas` con la sesión del rol más bajo.
La tabla `employees` tiene **22 filas**.

### Sesión previa (rol Consulta, rol 3)

```bash
curl -s -o /dev/null -c cookies-consulta.txt \
  --data-urlencode "username=consulta" --data-urlencode "password=Consulta_2026!" \
  http://localhost/fa/sistemas/login.php
```

### Controles

| # | Prueba | Resultado verificado |
|---:|---|---|
| 1 | Ficha **sin sesión**: `curl -s -o /dev/null -w "%{http_code}\n" ".../employees/view.php?id=1"` | `302` — redirige a `login.php` |
| 2 | Ficha como **Consulta**: `curl -s -o /dev/null -w "%{http_code}\n" -b cookies-consulta.txt ".../employees/view.php?id=17"` | `200` — la página abre |
| 3 | La **misma** ficha como **Administrador** | `200` — respuesta equivalente, mismo bloque |
| 4 | Módulo restringido con rol Consulta (`users/index.php`) | `403` — el control por rol **sí funciona en otros módulos** |

El control 4 es el que da peso a la demostración: no es que la aplicación no sepa
autorizar. Es que esta página no pregunta.

### Ataque

| # | `?id=` | Persona | Marcador verificado |
|---:|---:|---|---|
| 1 | `1` | Carlos Mendoza, Sistemas | `Titular de la cuenta de dominio empresa.local` |
| 2 | `11` | Héctor Salas, Recursos Humanos | `Acceso a datos personales completos` |
| 3 | `17` | Ricardo Olmo, Dirección | `Accesos al sistema financiero` |

Los tres son los que usa `lab_matrix.sh`, y los tres exponen algo distinto: la
cuenta de dominio con doble factor, el responsable de los expedientes laborales
de RRHH, y quien autoriza compras y tiene acceso al sistema financiero.

### Variante a mano

```bash
# 1. Ficha restringida leida con rol de solo lectura
curl -s -b cookies-consulta.txt \
  "http://localhost/fa/sistemas/employees/view.php?id=17" \
  | grep -c 'class="badge bg-danger">Restringido'

# 2. El texto de la nota interna, con el rol equivocado
curl -s -b cookies-consulta.txt \
  "http://localhost/fa/sistemas/employees/view.php?id=17" \
  | grep -c "Accesos al sistema financiero"

# 3. Telefono y correo de la ficha de RRHH
curl -s -b cookies-consulta.txt \
  "http://localhost/fa/sistemas/employees/view.php?id=11" \
  | grep -cE "\+52 614 300 3311|hector.salas@empresa.local"

# 4. Recorrido completo del directorio: 22 de 22 fichas filtradas
for id in $(seq 1 22); do
  curl -s -b cookies-consulta.txt \
    "http://localhost/fa/sistemas/employees/view.php?id=$id" \
    | grep -q 'class="badge bg-danger">Restringido' && echo "id=$id filtrado"
done | wc -l
```

Resultado verificado del caso 1: `2` (una insignia por tarjeta). Del caso 2: `1`.
Del caso 3: `2`. Del caso 4: `22`.

Fíjate en que ninguno de estos comandos necesita `--data-urlencode` ni
`--path-as-is`: no hay comillas, ni comas, ni `../`, ni espacios. La petición
que se está haciendo es indistinguible de la que hace un Administrador pulsando
el enlace del directorio. Esa es la definición exacta del problema.

### La evidencia: la bitácora

La vista de auditoría exige rol Administrador o Técnico (`audit/index.php`, línea
19), así que para verla hay que iniciar sesión con una de esas cuentas:

```bash
curl -s -o /dev/null -c cookies-admin.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php

# Vista de auditoría, filtrada por el usuario y el módulo de la enumeración
curl -s -b cookies-admin.txt \
  "http://localhost/fa/sistemas/audit/index.php?tab=actividad&usuario=consulta&modulo=employees" \
  | grep -oE "ficha del empleado EMP-[0-9]+ \(id [0-9]+\)"
```

Filas que aparecen (descripción completa tal como se guarda:
` consulted la ficha del empleado EMP-1017 (id 17)`):

```
consulted la ficha del empleado EMP-1017 (id 17)
consulted la ficha del empleado EMP-1011 (id 11)
consulted la ficha del empleado EMP-1001 (id 1)
```

El mismo conteo, en SQL, para medirlo antes y después:

```sql
SELECT COUNT(*) FROM audit_log WHERE modulo = 'employees' AND usuario = 'consulta';
```

En el entorno de demostración, después de las pruebas de esta ficha, ese
conteo crece de tres en tres por cada vuelta del recorrido. Esa es la
observación que hay que señalar en el video: **con SafeLine el número también
crece**, porque la petición llega a la aplicación y la aplicación la registra.
La auditoría detecta; no previene.

### Cómo lo verifica la matriz

`documents/lab_matrix.sh` no adivina: para LAB-03 abre las tres fichas con la
sesión de **Consulta** y busca un marcador exacto en el cuerpo de la respuesta.

| Ficha | Marcador exacto | Umbral |
|---|---|---|
| `?id=1`, `?id=11`, `?id=17` | `class="badge bg-danger">Restringido` | Los **3 de 3** deben aparecer |

La lógica es la de las líneas 228 a 241 del script:

```bash
for id in 1 11 17; do
    if curl -s -b "$JAR_CON" "$BASE_URL/employees/view.php?id=$id" \
        | grep -q 'class="badge bg-danger">Restringido'; then
        LEAK=$((LEAK + 1))
    fi
done
if [ "$LEAK" -eq 3 ]; then
    result OK "rol consulta lee fichas restringidas" "$LEAK de 3 fichas filtradas"
    NO_CUBIERTO=$((NO_CUBIERTO + 1))
    ...
```

Ese `NO_CUBIERTO=$((NO_CUBIERTO + 1))` es la parte importante: la prueba se
reporta en el contador de **"No cubiertos por el WAF"**, no en el de "Detenidos
por el WAF", y el script imprime debajo una nota que dice que un WAF no detiene
esto. La matriz está diseñada para **no** inflar el resultado de SafeLine, y esa
decisión de diseño es lo que hace honesta a la demostración.

```bash
# Escenario A
./documents/lab_matrix.sh

# Escenario B
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf -v
```

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) — esperado |
|---|---|---|
| 1. Ficha sin sesión (control) | `302` a `login.php` | `302` a `login.php` |
| 2. `?id=17` con rol Consulta | **200, notas de RRHH visibles** | **200, notas de RRHH visibles** |
| 3. `?id=1` con rol Consulta | **200, cuenta de dominio visible** | **200, cuenta de dominio visible** |
| 4. `?id=11` con rol Consulta | **200, expediente laboral visible** | **200, expediente laboral visible** |
| 5. Recorrido `1..22` | **22 de 22 fichas filtradas** | **22 de 22 fichas filtradas** |
| 6. `users/index.php` con rol Consulta | `403` | `403` |
| 7. Filas `VIEW` en `audit_log` | Crecen con cada id | **Crecen igual** |

Las dos filas de control (1 y 6) deben comportarse igual en ambos escenarios, y
eso no es trámite: es lo que demuestra que SafeLine no rompe ni el uso normal ni
el control de rol que **sí** existe en la aplicación.

La fila 7 es la que hay que mirar con atención en la grabación. En el Escenario
B se espera que SafeLine bloquee los payloads de las otras prácticas, y en esta
fila el número de
accesos registrados en la bitácora va a seguir subiendo exactamente igual. No es
un fallo del WAF: es que la petición es legítima, y el WAF solo puede detener
peticiones que no lo son.

### Escenario B todavía no se ha ejecutado

Conviene decirlo sin rodeos, porque es el punto donde estas fichas suelen
mentir: **SafeLine no está instalado en este entorno.** Se desplegará después, en
el VPS, escuchando en el puerto `8000` y reenviando a `http://127.0.0.1:80`. Todo
lo que aparece en la columna derecha de la tabla es **esperado**, no medido.

```bash
# Escenario B - detrás de SafeLine (aún no ejecutado)
curl -s -b cookies-consulta.txt \
  "http://192.168.100.22:8000/fa/sistemas/employees/view.php?id=17" \
  | grep -c 'class="badge bg-danger">Restringido'
```

Si cuando SafeLine esté desplegado ese comando devuelve `0`, hay que averiguar
por qué antes de grabar: significaría que algo en el camino está filtrando la
petición por un motivo que no es la regla de LAB-03.

---

## 6. Qué debería hacer SafeLine

**No debería hacer nada. Y esa es la respuesta correcta.**

SafeLine analiza la **petición**. Para decidir si algo es un ataque necesita ver
una señal: una comilla que cierra un `LIKE`, un `../` que recorre un árbol de
directorios, un `<script>` en un parámetro, un `; id` detrás de un host. La
petición de esta ficha no tiene ninguna de esas señales:

| Lo que SafeLine ve en la petición | Lo que tendría que saber para bloquear |
|---|---|
| `GET /fa/sistemas/employees/view.php?id=17` | Que el usuario de esta sesión tiene rol 3 y que el empleado 17 no le corresponde |
| Una cookie `SIGISESSID` válida | El modelo de roles de la aplicación y a qué recurso apunta cada rol |
| Código 200 y contenido HTML normal | Que dos de las tarjetas de esa página están marcadas como restringidas |
| Un entero entre 1 y 22 | Que esos 22 enteros son 22 expedientes personales |

Ninguna de esas cuatro filas es información de la petición: es información del
**modelo de permisos de la aplicación**, que vive en el código y en la base de
datos. Un WAF no lee el esquema de `users`, no sabe que el rol 3 se llama
*Consulta*, y no puede adivinar que la tarjeta con la insignia `Restringido` no
debía renderizarse. Para bloquear esto tendría que entender la aplicación, y en
el momento en que entiende la aplicación ya no es un WAF: es el control de
acceso, que es donde debe estar.

Lo único que un WAF puede aportar aquí es **velocidad**: un límite de peticiones
por IP ralentiza el recorrido `1..22`. Veintidós peticiones no se ralentizan de
forma apreciable, y un atacante que recorra los identificadores durante diez
minutos no se ve afectado. Para una enumeración de este tamaño, el rate limiting
no es un control: es una demora sin consecuencia.

Lo que sí funciona, y por eso la página registra el acceso, es la capa de
**detección**: la bitácora permite ver que el usuario `consulta` abrió 22 fichas
seguidas en cuatro minutos y disparar una alerta o una revisión. Detectar no es
prevenir, pero es infinitamente mejor que no saber nada.

| Aspecto | Valor esperado en Escenario B |
|---|---|
| Regla que debería activarse | **Ninguna** |
| Modo recomendado | Irrelevante: no hay regla aplicable |
| Respuesta | La ficha del empleado, con las dos tarjetas "Restringido" |
| Origen en la aplicación | `employees/view.php` se ejecuta con normalidad |
| Efecto secundario esperado | `audit_log` **sigue creciendo**: la petición llegó a la app |
| Clasificación en `lab_matrix.sh` | `No cubiertos por el WAF` |

Y para cerrar el círculo con las demás fichas: LAB-01, LAB-02, LAB-04, LAB-05,
LAB-06 y LAB-07 son ataques con firma, y un WAF tiene algo que mirar. LAB-03 y
LAB-08 —esta ficha y la de credenciales— son defectos de la aplicación. La
matriz los separa precisamente por eso, y por eso el resumen del script
termina la ejecución del Escenario B con esta frase:

> Escenario B correcto: los ataques con payload quedaron bloqueados. Los defectos
> de autorización y de credenciales siguen abiertos, como debe ser: ahí es donde
> un WAF no llega.

Un WAF que dijera que también detiene esto estaría mintiendo, y una
demostración que lo afirmara estaría vendiendo más producto del que tiene.

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- `employees/view.php` es el único archivo del sistema que imprime
  `notas_confidenciales` y `telefono`.
- `employees/index.php` es el directorio y **no** los muestra: su plantilla
  imprime número de empleado, nombre, puesto, departamento, equipos y estado. (Su
  consulta sí usa `SELECT e.*`, así que las columnas llegan a memoria; lo que no
  existe es la impresión. La diferencia entre "traer" y "mostrar" es
  exactamente la que hace falta entender aquí.)
- El parámetro `id` no es un vector de inyección: pasa por `(int)` y por un
  parámetro vinculado. En `employees/view.php` no hay SQL Injection, y por eso
  esta ficha no se confunde con LAB-01 ni con LAB-02.
- Los tres campos se imprimen con `e()`: en esta página no hay XSS.
- La única escritura derivada de la lectura es la fila de `audit_log`, que es
  el control de detección del que se habla en la sección 2.6.
- La corrección (`require_role()`, `has_role()`) ya existe y se usa en otros
  diez puntos de la aplicación; aquí simplemente no se invoca. Es el mismo
  patrón que LAB-01 con `secure_login()`: **la función correcta está escrita y
  desconectada.**

Consecuencia para el laboratorio: LAB-03 es independiente de las demás prácticas.
Arreglar la inyección SQL del login no cierra este agujero, y cerrar este
agujero no arregla la inyección. Son dos defectos que se corrigen en dos líneas
distintas.

---

## 8. La conclusión del laboratorio

Esta ficha sirve para una sola cosa: para demostrar, con un caso concreto, que
**un WAF no es una defensa completa**. No hay ninguna otra técnica que
presentar aquí. La petición es válida, la autenticación es válida, el
identificador es válido, la respuesta es un `200` con HTML bien formado. SafeLine
no puede hacer otra cosa que dejarla pasar, y hace bien: bloquear tráfico
legítimo rompería el sistema sin proteger nada.

La corrección real está en el código y son tres líneas, en la forma (b) de la
sección 2.5:

```php
<?php if (has_role(1)): ?>
    <!-- tarjetas "Datos personales" y "Notas de RRHH" -->
<?php endif; ?>
```

La función `has_role()` ya existe en `config/app.php`, con la lista de roles
correcta detrás. No hay que escribir un WAF, ni una regla, ni una lista negra de
identificadores: hay que hacer la pregunta que la página nunca hace.

Y hay una segunda conclusión, más incómoda, sobre la insignia roja. La
aplicación etiqueta sus propios datos sensibles con la palabra `Restringido`. Es
un detalle de diseño, y funciona como documentación: cualquiera que abra esa
ficha sabe que está viendo algo que no debería. Pero mientras el control esté en
la etiqueta y no en el código, la etiqueta es solo pintura. Un control de acceso
que se puede ver pero no se puede exigir no es un control: es un aviso. La
autorización tiene que ejecutarse, no mostrarse.

La última idea es la más útil para una auditoría real: en este módulo la
vulnerabilidad no está en una línea de código sospechosa, está en una decisión que
nunca se tomó. Nadie escribió "esto lo puede ver cualquiera". La página se
compuso, se probó con una cuenta de Administrador, y quedó bien. Detectar eso
revisando el código —buscando concatenaciones, búsquedas de contraseña sin hash,
includes dinámicos— es imposible. Solo se detecta preguntando, para cada
pantalla, quién debería verla. Ese es el trabajo que ningún WAF hace, y por eso
esta ficha es la más importante del laboratorio.
