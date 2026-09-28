# LAB-09 — Fuerza bruta sin detección, sin bloqueo y sin límite de ritmo

| Campo | Valor |
|---|---|
| **ID** | LAB-09 |
| **Tipo** | Fuerza bruta — ausencia de control de intentos de autenticación |
| **Riesgo** | Medio por sí solo; alto combinado con credenciales débiles (LAB-08) |
| **CWE** | CWE-307 (Improper Restriction of Excessive Authentication Attempts) · CWE-799 (Improper Control of Interaction Frequency) · CWE-312 / CWE-532 (información sensible en registros) |
| **OWASP** | A07:2021 — Identification and Authentication Failures |
| **Endpoint** | `POST /fa/sistemas/login.php` |
| **Acceso** | Sin sesión |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

El formulario de acceso acepta **todos los intentos que le manden**. No hay
ningún control de ningún tipo: no se bloquea la cuenta después de N intentos, no
hay CAPTCHA, no hay retardo incremental, no hay límite de peticiones por IP, no
hay aviso al usuario y no hay alerta al administrador.

Verificado en este entorno: doce intentos fallidos consecutivos con contraseñas
distintas (`incorrecta1` … `incorrecta12`) contra el usuario `admin`, los doce
en el mismo segundo. La cuenta **no se bloqueó**, el formulario siguió
respondiendo con normalidad y, justo después de la racha, un intento con la
contraseña correcta entró al panel sin ninguna resistencia adicional.

Que la cuenta siga abierta después de doce fallos no es un defecto menor. Significa
que el atacante puede calcular su presupuesto: si un intento por segundo desde una
sola IP cubre las combinaciones del caso más fácil, no necesita nada más
—no botnets, no cuentas comprometidas, no explotar ninguna otra vulnerabilidad.
El único límite real es la paciencia y la velocidad de la conexión.

Lo que sí existe, y es la parte que hace esta ficha útil, es el **registro**. Cada
intento, exitoso o no, se escribe en la tabla `login_attempts` con usuario, IP,
resultado y agente. Y existe un visor, `audit/index.php?tab=accesos`, que muestra
esa tabla con filtros.

Ahí está la lección, y es la que hay que decir sin rodeos: **registrar no es
bloquear**. La tabla se llena, la pantalla enseña la racha completa con fecha, IP
y agente, y la cuenta sigue abierta. Un sistema que registra el ataque y no lo
detiene ha construido el informe del ataque sin escribir el freno. Peor todavía:
ha construido un inventario de todo lo que se ha intentado contra él, con las
puertas abiertas.

---

## 2. Dónde está el código

### 2.1 El camino de autenticación

`login.php`, líneas 26 a 47:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = input_post('username');
    $password_input = input_post('password');

    if ($username_input === '' || $password_input === '') {
        $error = 'Debe ingresar usuario y contrasena.';
    } else {
        $user_row = lab_login_vulnerable($username_input, $password_input);

        if ($user_row === null) {
            // Mensaje unico para usuario inexistente y contrasena incorrecta.
            // Distinguirlos permitiria enumerar cuentas validas.
            $error = 'Usuario o contrasena incorrectos.';
            log_login_attempt($username_input, false);
            audit_log('LOGIN', 'login', 'Intento de acceso fallido para el usuario: ' . $username_input, $username_input);
        } else {
            // Usuario inactivo no puede iniciar sesion.
            if ($user_row['estado'] !== 'Activo') {
                $error = 'Usuario o contrasena incorrectos.';
                log_login_attempt($username_input, false);
```

Todo el camino de un intento fallido son cuatro líneas: autenticar, asignar el
mensaje, registrar, seguir. No hay un contador, no hay una consulta a la tabla de
intentos, no hay un `sleep()`, no hay una bandera de "esta cuenta está bloqueada".
`lab_login_vulnerable()` es la función de LAB-01 (inyección SQL); para esta ficha
es irrelevante, porque un intento de fuerza bruta es un login **correcto** en
cuanto a forma: son credenciales válidas en su formato, solo que la contraseña
falla.

### 2.2 El registro que existe y que nadie consulta para denegar

`config/audit.php`, líneas 51 a 67:

```php
function log_login_attempt($username, $success)
{
    try {
        db_exec(
            "INSERT INTO login_attempts (username, ip, success, user_agent)
             VALUES (?, ?, ?, ?)",
            array(
                mb_substr($username, 0, 100),
                client_ip(),
                $success ? 1 : 0,
                client_agent()
            )
        );
    } catch (Exception $ex) {
        error_log('log_login_attempt fallo: ' . $ex->getMessage());
    }
}
```

La tabla, `database/schema.sql` (comentario de la línea 66 y definición
siguiente):

```sql
CREATE TABLE `login_attempts` (
  `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL,
  `ip`         VARCHAR(45) NOT NULL COMMENT 'soporta IPv6',
  `success`    TINYINT(1) NOT NULL DEFAULT 0,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip_time` (`ip`, `created_at`),
  KEY `idx_attempts_user_time` (`username`, `created_at`),
  KEY `idx_attempts_success` (`success`)
);
```

Los tres índices compuestos están puestos como si alguien fuera a consultarla por
IP y por usuario. Se puede afirmar más: en toda la aplicación, la tabla
`login_attempts` **solo recibe `INSERT` y se lee en un sitio**, el visor
`audit/index.php` (línea 86: `$tabla = $pestana === 'actividad' ? 'audit_log' : 'login_attempts';`).
No hay ninguna consulta que la use para **negar** un acceso. La infraestructura
de la detección está construida; la decisión que falta es una línea.

### 2.3 Los controles que faltan, comprobado buscándolos

| Control | Estado | Comprobación |
|---|---|---|
| Bloqueo de cuenta tras N intentos | **No existe** | `login_attempts` no se lee para decidir nada; no hay contador de intentos por usuario |
| CAPTCHA o reto tras fallos | **No existe** | No hay ninguna referencia a captcha, reCAPTCHA, hCaptcha ni reto similar en el código |
| Retardo incremental | **No existe** | No hay ningún `sleep()` ni `usleep()` en la aplicación |
| Límite de peticiones por IP | **No existe** | No hay nada que agrupe por IP con fines de decisión; la columna existe solo para pintar |
| Aviso al usuario | **No existe** | El mensaje es siempre "Usuario o contrasena incorrectos.", sin ningún aviso de "intentos restantes" |
| Alerta al administrador | **No existe** | `audit_log` recibe la fila, no hay correo, ni webhook, ni notificación |
| Uso de la IP de origen para decidir | **No se usa** | `client_ip()` (`config/app.php`, línea 158) solo lee `REMOTE_ADDR`: se registra en la tabla y no participa en ninguna decisión |

La búsqueda de las seis primeras filas es literal: un `grep` de `sleep(`,
`usleep`, `throttle`, `rate limit`, `bloque`, `captcha`, `lockout` e `intentos`
sobre todo el código PHP devuelve únicamente comentarios y textos de la
documentación del laboratorio. No hay una implementación parcial escondida: no hay
nada. La séptima fila es el caso interesante, porque la pieza sí existe
(`client_ip()` lee la dirección y la guarda) y aun así no sirve para nada: es
información sobrante. La sección 6 vuelve sobre esto, porque es lo que rompe en
cuanto se pone un WAF delante.

### 2.4 Lo que sí está bien, y lo que estaña

Dos cosas de este módulo están correctas y conviene decirlas, porque la ficha
no debe exagerar:

**El mensaje es único.** `login.php` devuelve "Usuario o contrasena incorrectos."
tanto para un usuario inexistente como para una contraseña equivocada (líneas 38 y
44). La enumeración por mensaje está cerrada, y el comentario del propio código
lo dice: *"Distinguirlos permitiria enumerar cuentas validas."* Eso es
`login.php` aplicando una mitigación de CWE-204 sin llamarla así.

**Pero la enumeración es posible por otro lado, y es peor.** La columna
`username` de `login_attempts` guarda el nombre tal como lo escribió quien intentó
entrar, y `success` dice si funcionó. Quien tenga acceso de lectura a esa tabla
—un administrador, un técnico, o un atacante que haya entrado por cualquier otra
vía— tiene la lista de usuarios reales del sistema: todo `username` con
`success = 1` es una cuenta válida. Verificado en la base actual:

```sql
SELECT DISTINCT username FROM login_attempts WHERE success = 1 ORDER BY username;
-- admin, consulta, m.quiroga, r.ibarra, s.navarro, tecnico
-- ' OR '1'='1' --      <-- no es una persona
-- ' OR 1=1#            <-- no es una persona
-- admin' --            <-- no es una persona
```

Las seis primeras son cuentas reales del sistema; las tres últimas no existen en
la tabla `users`. Y esa mezcla es exactamente el problema: la misma consulta que
sirve para enumerar usuarios válidos devuelve también las cadenas de inyección
de LAB-01, porque en este laboratorio el bypass de autenticación **sí** devolvió
una fila y `log_login_attempt()` la registró con `success = 1` como si fuera un
acceso correcto.

Esos tres "usuarios" no son personas: son cadenas que la consulta de LAB-01 dio
por buenas. Una regla de enumeración que se apoye solo en `success = 1` produce
falsos positivos, porque el nombre que se registra es el
que **mandó el atacante**, no el que la base de datos resolvió. Ese es
justamente el problema de fondo de la bitácora: guarda la entrada del atacante
como si fuera un dato de negocio, y un registro de seguridad que el atacante
controla deja de ser un dato confiable.

### 2.5 La corrección que falta

La forma correcta de cerrar esto, con lo que ya existe en el proyecto, es una
consulta a `login_attempts` **antes** de autenticar:

```php
$fallidos = db_value(
    "SELECT COUNT(*) FROM login_attempts
      WHERE username = ? AND success = 0
        AND created_at > (NOW() - INTERVAL 15 MINUTE)",
    array($username_input)
);

if ($fallidos >= 5) {
    // Cuenta bloqueada temporalmente. Aqui se decide tambien si la IP
    // supera un limite de peticiones por minuto, y se avisa al usuario
    // y al administrador.
    $error = 'Cuenta bloqueada temporalmente por intentos fallidos.';
}
```

El intervalo y el umbral son política, no código, y por eso tienen que ser
configurables. Lo importante es que la consulta **existe**: la tabla está ahí, con
sus índices por `(username, created_at)`, esperando a que alguien la consulte.

---

## 3. Por qué existe en una app real

Es la ausencia más difícil de encontrar en una revisión de código, y por eso
merece una explicación de por qué aparece tanto.

**Nadie ve lo que no hay.** En una revisión de un formulario de login se leen el
`SELECT`, el `password_verify()` y el mensaje de error. Rastrear la **cadencia** de
los intentos requiere abrir el visor de auditoría y preguntar qué se ve ahí, o
simplemente ejecutar doce intentos y comprobar que la cuenta sigue viva. El
esfuerzo de encontrar la vulnerabilidad es mucho mayor que el de introducirla, y
por eso casi nunca se busca.

**El bloqueo de cuentas se percibe como una molestia, no como una defensa.** Es
la razón real por la que el patrón aparece en sistemas de producción, y no es
hipótesis: la soporte de usuarios odia el bloqueo de cuentas, porque los usuarios
se quejan, y un usuario que se queja tiene más peso que un intento fallido que no
logró entrar. El resultado habitual es una excepción tras otra, y un sistema sin
controles. (Por eso las alternativas válidas —retardo progresivo, CAPTCHA,
segundo factor, bloqueo **temporal** y no permanente— suelen descartar el bloqueo
permanente: no es un error de diseño, es un error de criterio.)

**El registro se confunde con la protección.** Tener una tabla de intentos con
fecha e IP parece, en la revisión, un mecanismo antifuerza bruta. Es la confusión
más común de este laboratorio: `login_attempts` parece un control y es un
**inventario**. Se escribió el registro y se Sintió que el problema estaba
resuelto, porque ahora el ataque es "visible". La visibilidad es el primer paso,
no el control.

**El equipo tiene otra capa creyendo que cubre esto.** En una organización con
firewall de aplicación, MFA en el correo y bloques por IP en la red, el formulario
se despliega sin ningún control, porque "eso lo cubre la red". La red no lo cubre:
un WAF no sabe cuál es la contraseña correcta y una IP bloqueada se cambia. En
este laboratorio se ve exactamente esa falsa confianza: LAB-08
(debilidad de credenciales) y LAB-09 (ausencia de control) son independientes y
ninguno de los dos arregla al otro.

---

## 4. Payloads

Probados contra `http://localhost/fa/sistemas/login.php`. No hay payload: la
herramienta es un bucle, y esa es precisamente la diferencia con las fichas
anteriores del laboratorio.

### El ataque

```bash
# Sesion administrativa solo para consultar el visor despues
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php

# Racha: 12 intentos con contrasenas distintas sobre una cuenta real
for i in $(seq 1 12); do
  curl -s -o /dev/null \
    --data-urlencode "username=admin" \
    --data-urlencode "password=incorrecta$i" \
    http://localhost/fa/sistemas/login.php
done

# La cuenta sigue viva
curl -s -o /dev/null -b cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
curl -s -b cookies.txt http://localhost/fa/sistemas/dashboard.php | grep -c "Bienvenido"
# 1
```

| # | Prueba | Resultado verificado |
|---:|---|---|
| 1 | `admin` / contraseña inventada (control) | `200` con "Usuario o contrasena incorrectos.", sin sesión |
| 2 | `noexiste` / cualquiera (control) | `200` con **el mismo** mensaje, sin sesión: la enumeración por mensaje está cerrada |
| 3 | 12 intentos con `incorrecta1`…`incorrecta12` | **12 de 12 rechazados, sin bloqueo de cuenta** |
| 4 | Tras la racha, `admin` / `Admin_2026!` | **Entra al panel**: la cuenta no quedó penalizada |
| 5 | Un intento aislado (control de registro) | Registrado: `login_attempts` crece en exactamente 1 fila por petición |

La fila 5 importa para las filas 3 y 4: se comprobó que el conteo de
`login_attempts` pasó de 239 a 240 con una sola petición de prueba, y que la fila
escrita conserva el agente declarado por el cliente:

```sql
SELECT username, ip, success, user_agent, created_at FROM login_attempts
 WHERE user_agent = 'curl-lab09-demo';
-- m.quiroga | ::1 | 0 | curl-lab09-demo | 2026-09-26 21:00:31
```

Es decir: el sistema registra cada intento, con todos los datos que el atacante
quiere, y no hace nada con ellos.

### La consulta SQL para ver la racha

```sql
-- Intentos fallidos por usuario en los ultimos 10 minutos
SELECT username, COUNT(*) AS intentos,
       MIN(created_at) AS primero, MAX(created_at) AS ultimo
  FROM login_attempts
 WHERE success = 0
   AND created_at >= NOW() - INTERVAL 10 MINUTE
 GROUP BY username
 ORDER BY intentos DESC;

-- La misma racha, agrupada por usuario e IP (el indice idx_attempts_ip_time
-- existe justo para esta consulta)
SELECT username, ip, COUNT(*) AS racha
  FROM login_attempts
 WHERE success = 0
   AND created_at >= NOW() - INTERVAL 10 MINUTE
 GROUP BY username, ip;
```

Salida real de la segunda consulta después de la racha de 12 intentos:

```
username | ip   | racha
admin    | ::1  |    12
```

Un detalle que conviene ver: los doce intentos tienen **el mismo `created_at`**, con
precisión de segundo. La columna es un `TIMESTAMP` con valor por defecto, así que
no distingue milisegundos y una racha rápida se ve como un único instante. Para
detectar automatización, cualquier consulta que agrupe por minuto y por IP
infravalora la velocidad real: doce peticiones en un segundo son doce peticiones
por minuto en la tabla, no doce por hora.

### La misma evidencia por el visor web

`audit/index.php`, pestaña **Intentos de acceso**, con tres filtros: resultado
(exitosos/fallidos), usuario y dirección IP. La equivalencia con la consulta
anterior es:

| Consulta SQL | Visor web |
|---|---|
| `success = 0` | `?tab=accesos&resultado=fail` |
| `username = 'admin'` | `&usuario_login=admin` |
| `GROUP BY ip` | `&ip=::1` |
| `COUNT(*)` por usuario | El contador de la cabecera: "N registro(s) en intentos de acceso" |

```bash
# Fallidos del usuario admin
curl -s -b cookies.txt \
  "http://localhost/fa/sistemas/audit/index.php?tab=accesos&resultado=fail&usuario_login=admin"

# La misma racha, filtrada por IP de origen
curl -s -b cookies.txt \
  "http://localhost/fa/sistemas/audit/index.php?tab=accesos&resultado=fail&ip=::1"
```

El visor pagina de 25 en 25 (`$por_pagina = 25`, línea 26), así que una racha
larga se ve repartida en varias páginas. En la ejecución de este laboratorio, el
filtro `resultado=fail&usuario_login=admin` devolvió "123 registro(s)" y la
primera página con 25 filas marcadas como **Fallido**: los doce intentos de la
racha, más el historial sembrado y las pruebas anteriores. El listado es
precisamente la evidencia que se busca en vídeo: doce líneas consecutivas con el
mismo usuario, la misma IP, resultado Fallido y la misma hora.

Cuando la tabla está vacía, el visor no muestra una tabla en blanco sino un
mensaje explícito: *"Ningun intento llego a la aplicacion."* (línea 246). Ese
mensaje es, en el Escenario B, la prueba de que el corte ocurrió **antes** de la
aplicación.

### La comparación tiene que ser un delta

El `seed.sql` siembra 20 filas en `login_attempts` (`database/seed.sql`, línea
320 y siguientes) para que la tabla se vea con uso real antes de la demostración.
Por eso ningún conteo absoluto significa nada: hay que medir antes y después.

```sql
SELECT COUNT(*) FROM login_attempts;   -- repetir antes y despues del Escenario B
```

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) — esperado |
|---|---|---|
| 1. Usuario inexistente (control) | Mensaje único, sin sesión | Igual |
| 2. Contraseña inventada (control) | Mensaje único, sin sesión | Igual |
| 3. `admin` / `Admin_2026!` (control) | Entra al panel | Entra al panel |
| 4. 12 intentos fallidos seguidos | **12 peticiones aceptadas, sin bloqueo** | **Racha recortada por límite de peticiones** |
| 5. `login_attempts` después de la racha | **12 filas nuevas** | **0 filas nuevas**, o solo las del control |
| 6. Visor, pestaña Intentos de acceso | Racha completa visible con fecha e IP | Vacío: "Ningun intento llego a la aplicacion" |
| 7. Alerta al administrador | Ninguna | Alertar SafeLine, si se configura |

Las filas 1, 2 y 3 son los controles: deben comportarse igual en ambos
escenarios, para demostrar que el WAF no rompe el uso normal. En una oficina con
varios usuarios, la fila 3 es la que hay que vigilar: un límite de peticiones mal
puesto puede bloquear al personal legítimo.

La comparación de la fila 5 y la 6 sobre la **misma tabla** es la mejor evidencia
de toda esta demostración, y merece una explicación de por qué: no hace falta
interpretar respuestas ni buscar marcadores en el cuerpo. La tabla
`login_attempts` es el propio registro de lo que llegó a la aplicación. Si en el
Escenario A crece con cada intento y en el Escenario B no crece, la única
explicación posible es que las peticiones se cortaron antes de entrar.

**Esta ejecución del Escenario B todavía no se ha hecho.** SafeLine no está
instalado en este entorno; se desplegará después, en el VPS, escuchando en el
puerto `8000` y reenviando a `http://127.0.0.1:80`. Todo lo de la columna
derecha es **esperado**, no medido.

### Escenario A — Apache directo

```bash
# Sesión administrativa, solo para el visor
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php

for i in $(seq 1 12); do
  curl -s -o /dev/null \
    --data-urlencode "username=admin" \
    --data-urlencode "password=incorrecta$i" \
    http://localhost/fa/sistemas/login.php
done

# Evidencia: la racha completa
curl -s -b cookies.txt \
  "http://localhost/fa/sistemas/audit/index.php?tab=accesos&resultado=fail&usuario_login=admin"
```

### Escenario B — detrás de SafeLine

Mismos comandos, mismo orden, solo cambia el host:

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://192.168.100.22:8000/fa/sistemas/login.php

for i in $(seq 1 12); do
  curl -s -o /dev/null \
    --data-urlencode "username=admin" \
    --data-urlencode "password=incorrecta$i" \
  http://192.168.100.22:8000/fa/sistemas/login.php
done

curl -s -b cookies.txt \
  "http://192.168.100.22:8000/fa/sistemas/audit/index.php?tab=accesos&resultado=fail&usuario_login=admin"
```

### Cómo lo verifica la matriz

`documents/lab_matrix.sh` hace la misma prueba de doce intentos y cuenta cuántos
de ellos **no** dejaron entrar:

```bash
ANTES=$(curl -s -b "$JAR_ADM" "$BASE_URL/audit/index.php?tab=accesos&resultado=fail" \
        | grep -c "badge bg-danger")

for i in $(seq 1 12); do
  login "$JAR_CON" "$C_ADMIN" "incorrecta$i"
  if ! curl -s -b "$JAR_CON" "$BASE_URL/dashboard.php" | grep -q "Bienvenido"; then
    BLOQUEADOS=$((BLOQUEADOS + 1))
  fi
done
```

El conteo `ANTES` es la línea base de filas fallidas que ya hay en el visor; la
prueba se decide por el número de rechazos (`BLOQUEADOS`), no por el delta, y esa
diferencia explica por qué en el Escenario B la comprobación que importa es la
columna de delta de la sección 4.

En el Escenario A se espera `BLOQUEADOS = 12` y la línea se reporta como `OK`
con la etiqueta "sin bloqueo de cuenta". En el Escenario B, con `--waf`, la misma
línea se reporta como `BLOQ` con el detalle "`N` de 12 rechazados". La lógica
está invertida a propósito: en el Escenario A, que la racha pase es el
comportamiento **correcto** de la aplicación vulnerable; en el Escenario B, que la
racha se recorte es el comportamiento correcto de la defensa.

La matriz marca además `NO_CUBIERTO` cuando los doce intentos pasan, porque esa
línea mide el defecto de la aplicación, no la capacidad del WAF. Y es honesto
decirlo: de las nueve prácticas, esta es la **única en la que el WAF aporta algo
sin que haya un payload** que delate el ataque —en LAB-01, LAB-02, LAB-05,
LAB-06 y LAB-07 hay una firma que bloquea, y en LAB-03 y LAB-08 no hay nada que
un WAF pueda ver—, y aun así es una mitigación parcial. La sección 6 desarrolla
los tres límites.

---

## 6. Qué debería hacer SafeLine

SafeLine debe aplicar un límite de peticiones sobre `login.php` que corte la racha
**antes** de que la petición llegue a Apache, y avisar cuando lo haga.

| Aspecto | Valor esperado |
|---|---|
| Control | Límite de peticiones por IP y por ruta, sobre `login.php` |
| Ventana típica | 1 a 5 minutos por IP, con umbral de 5 a 20 peticiones |
| Al activarse | Página de bloqueo o respuesta de limitación, no el formulario de la app |
| Origen en la app | Ninguno: la petición no debe llegar a `login.php` |
| Efecto secundario esperado | `login_attempts` **no debe crecer**; el visor queda sin las filas de la racha |
| Complemento | Detección de bots y alerta por volumen de `LOGIN` exitosos desde una IP |

El efecto secundario de las dos filas centrales es el que importa en una
demostración: es la misma comprobación de delta que describe la sección 4, y por
eso el visor de auditoría, que en el Escenario A es la prueba del fallo, en el
Escenario B se convierte en la prueba del bloqueo.

Y hay un segundo control que SafeLine sí puede aportar y que no es un límite de
ritmo: **la detección de la racha como patrón**, no como frecuencia. Una cuenta
que recibe muchos `POST` fallidos en pocos segundos es un ataque, tanto si viene
de una IP o de cien. El límite por IP es el mecanismo; la alerta por patrón es la
que de verdad deja rastro.

### Los tres límites, sin suavizarlos

**1. No sabe cuál es la contraseña correcta.** Es la misma razón por la que un WAF
no detiene LAB-08: aquí la petición es un login bien formado, sin sintaxis
maliciosa, sin carga útil sospechosa. Un WAF inspecciona peticiones; la contraseña
es un dato que se compara en la base de datos. Si un atacante prueba tres
contraseñas al día desde su portátil, no toca ningún umbral y entra con la
cuarta. Contra eso solo sirven la política de contraseñas (LAB-08) y el segundo
factor.

**2. Un atacante distribuido lo evade.** El límite por IP cuenta direcciones. Un
atacante con cien máquinas, o con una botnet con IP rotativa, reparte doce
intentos entre cien clientes y ninguno llega al umbral. El control por IP es
eficaz contra el atacante que va con un solo portátil, que es el caso del
laboratorio, y es casi inútil contra el atacante industrializado, que es el caso
real. Un control por **cuenta de destino** no se evade así, y es exactamente el
que SafeLine no puede hacer por sí solo: solo la aplicación sabe qué cuenta se
está atacando en cada petición.

**3. Bloquear por IP rompe el caso de uso legítimo.** Esta es la razón por la que
muchos despliegues desactivan el rate limiting después de una queja, y conviene
decirla antes de que ocurra en la demostración. En una oficina, varios usuarios
salen por la misma IP pública —la de la NAT del router o del proxy—, y también
toda la red de una biblioteca, de un hospital o de una fábrica. Si el umbral se
pone en 10 peticiones por minuto y por IP, las diez personas que están
introduciendo su contraseña en el mismo minuto bloquean a la oficina entera. Por
eso un límite por IP tiene que ir acompañado de una exención para el tráfico
autenticado con sesión previa, y por eso el primer control debe ser por cuenta.

Y una cuarta limitación, esta de la propia aplicación, verificada en el código:
`client_ip()` (`config/app.php`, línea 158) lee **solo** `REMOTE_ADDR`:

```php
function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}
```

Con el despliegue descrito —SafeLine delante, reenviando a `http://127.0.0.1:80`—
Apache verá como `REMOTE_ADDR` la dirección del proxy, no la del atacante. Dos
consecuencias, ambas verificadas en el código:

- Cualquier límite por IP implementado en la aplicación, leyendo de esa función,
  controla el proxy en lugar de controlar a quien ataca.
- En el visor `audit/index.php?tab=accesos&ip=...`, el filtro por IP del Escenario
  B devolverá la IP del proxy para todas las filas, y la columna IP de la bitácora
  habrá dejado de ser útil como evidencia.

La corrección es leer `X-Forwarded-For` **solo** cuando la petición viene de un
proxy de confianza, nunca sin comprobarlo: aceptar esa cabecera tal cual
permitiría a un cliente falsear su IP y saltarse el límite por IP que se acaba de
configurar. Es un recordatorio útil de que la mitigación del WAF y la de la
aplicación se necesitan mutuamente.

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- La ausencia de control vive en `login.php`, que es el único camino de
  autenticación de la aplicación. `login.php` es también el único archivo que
  escribe `$_SESSION['user_id']`: no hay un segundo formulario de login en otro
  módulo, ni una API de autenticación alternativa.
- `log_login_attempt()` se llama únicamente desde `login.php`, en sus tres ramas
  (líneas 39, 45 y 54). Ningún otro módulo escribe en `login_attempts`.
- La tabla se lee únicamente desde `audit/index.php`. No hay ninguna otra
  consulta, y —esto es lo importante— ninguna que decida.
- **LAB-09 no depende de LAB-01.** La racha de doce intentos **sí** pasa por
  `lab_login_vulnerable()`, porque todo login pasa por ahí, pero las peticiones
  no llevan ninguna sintaxis de inyección: el usuario es `admin` y la contraseña
  es `incorrecta1`. Por eso un WAF configurado solo para detectar inyecciones SQL
  no tiene nada que bloquear en esta racha, y la única defensa que puede entrar
  en juego es el límite de peticiones. Son dos prácticas medidas por separado en
  la matriz, con expectativas opuestas.
- **LAB-09 y LAB-08 se refuerzan, y esa es la parte incómoda.** Los dos defectos
  son independientes por separado —una contraseña débil entra sin ningún intento
  fallido, y una racha de doce intentos no adivina nada—, pero juntos forman un
  camino corto: las credenciales débiles son adivinables y la ausencia de control
  da tiempo ilimitado para adivinarlas. En este laboratorio, con las siete
  contraseñas del `seed.sql` siendo `inicial.apellido` o un año predecible, el
  número de intentos que hace falta está dentro del rango de lo que esta
  aplicación acepta en un segundo. Por eso la ficha de LAB-08 marca `m.quiroga`
  como objetivo de esta práctica, y por eso el `seed.sql` lo dice en un comentario
  junto a la contraseña.
- El visor de auditoría solo lee; no altera el resultado de ningún intento. Es
  un módulo de consulta, y a pesar de ser el que "detecta" el ataque, su
  `require_role(array(1, 2))` impide que un usuario de solo lectura vea la
  bitácora.

---

## 8. La conclusión del laboratorio

De las nueve prácticas, esta es la única en la que el WAF tiene algo que aportar
**sin que exista un payload**: la petición es un login bien formado y el único
indicador es cuántas veces se repite. Con un límite de peticiones por IP, SafeLine
corta la racha y el `login_attempts` del Escenario B se queda sin las doce filas.
Eso es una defensa real, y es la diferencia entre esto y una hoja de cálculo.

Pero conviene ser exacto sobre su tamaño, y no hay forma honesta de decir más que
esto: es una mitigación **parcial**, en tres frentes. No sabe cuál es la contraseña
correcta, de modo que un atacante lento no toca ningún umbral. Se evade
repartiendo los intentos entre muchas direcciones IP. Y bloquear por IP rompe el
caso de uso legítimo de una oficina con salida compartida, que es exactamente lo
que pasa en la red donde se va a grabar la demostración. Un límite mal ajustado
no protege el sistema: lo deja inservible y con la entrada al local bloqueada.

La corrección de fondo es de aplicación, y aquí la buena noticia es que la mayor
parte ya está construida: la tabla existe, tiene índices pensados para esta
consulta, y `log_login_attempt()` se llama en las tres ramas del login. Falta una
consulta antes de autenticar y una decisión. Un bloqueo temporal y progresivo por
**cuenta** —con retardo incremental, CAPTCHA a partir de un umbral, y
desbloqueo administrativo—, más un límite por IP, MFA en las cuentas
privilegiadas, y alertas cuando una cuenta se bloquea.

Y la última parte de la conclusión es la que más se olvida: **la bitácora no debe
convertirse en la guía del atacante**. Este sistema ya hace bien dos cosas —
mensaje único y registro completo— y aun así deja una lista con `success = 1`
que revela los usuarios reales, contaminada además con los payloads que el propio
atacante escribió. Un registro de seguridad que guarda el texto que envió el
atacante, sin normalizar y sin distinguir lo que es entrada de lo que es hecho,
sirve para el atacante tanto como para el defensor. Y para que sirva para el
defensor, tiene que quedar fuera del alcance del rol de Consulta, con una
retención definida, y con la enumeración de cuentas resuelta en la capa de
autenticación y no en la consulta de auditoría.
