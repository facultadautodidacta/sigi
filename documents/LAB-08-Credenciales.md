# LAB-08 — Credenciales débiles sin política de contraseñas

| Campo | Valor |
|---|---|
| **ID** | LAB-08 |
| **Tipo** | Credenciales débiles — ausencia de política de contraseñas |
| **Riesgo** | Alto (acceso administrativo total con una contraseña publicada) |
| **CWE** | CWE-521 (Weak Password Requirements) · CWE-798 (Use of Hard-coded Credentials) · CWE-308 (Use of Single-factor Authentication) |
| **OWASP** | A07:2021 — Identification and Authentication Failures |
| **Endpoint** | `POST /fa/sistemas/login.php` |
| **Acceso** | Sin sesión |
| **Implementada** | 26 de septiembre de 2026 |

> **Esta ficha es la segunda de las dos que un WAF no puede cerrar**, junto con
> LAB-03. Aquí la petición de ataque es un inicio de sesión **correcto**: la
> contraseña que el atacante usa es la verdadera. No hay payload, no hay patrón
> malicioso, no hay nada anómalo en la petición. Por eso el Escenario B de esta
> práctica también termina con la aplicación expuesta, y `lab_matrix.sh` lo
> reporta en la línea `No cubiertos por el WAF`. La sección 6 desarrolla por qué.

---

## 1. Qué hace

La aplicación tiene siete cuentas y las siete tienen una contraseña que se
deduce. No hace falta fuerza bruta, ni diccionario, ni listas de contraseñas
filtradas: el patrón está a la vista en el nombre de usuario.

**Familia 1 — cuentas de rol.** La contraseña es el nombre del rol, el año y un
símbolo:

| Usuario | Contraseña | Patrón |
|---|---|---|
| `admin` | `Admin_2026!` | rol + `2026` + `!` |
| `tecnico` | `Tecnico_2026!` | rol + `2026` + `!` |
| `consulta` | `Consulta_2026!` | rol + `2026` + `!` |

**Familia 2 — cuentas personales.** El usuario es `inicial.apellido` y la
contraseña es el apellido, el año y el mismo símbolo:

| Usuario | Contraseña | Patrón |
|---|---|---|
| `m.quiroga` (Marta Quiroga) | `Quiroga_2026!` | apellido + `2026` + `!` |
| `a.delgado` (Ana Delgado) | `Delgado_2026!` | apellido + `2026` + `!` |
| `r.ibarra` (Raúl Ibarra) | `Ibarra_2026!` | apellido + `2026` + `!` |
| `s.navarro` (Sofía Navarro) | `Navarro_2026!` | apellido + `2026` + `!` |

Dos reglas y siete cuentas. Eso es todo el secreto, y esa es la debilidad: no
son siete contraseñas debiles, es **una plantilla**. Un atacante que descubre
una la aplica a todas, y un atacante que solo pruebe `admin / Admin_2026!` ya
tiene el control total del sistema.

A eso se suman cuatro ausencias, y las cuatro importan más que la plantilla:

- **No hay caducidad.** La contraseña que se creó en 2026 sigue valiendo en
  2027. Nadie está obligado a cambiarla nunca.
- **No hay rotación.** No existe el concepto de "clave temporal" ni de
  credencial que expire al cambiar de puesto.
- **No hay bloqueo de cuenta** tras intentos fallidos. Eso es LAB-09, que la
  matriz comprueba por separado: doce intentos equivocados seguidos y la cuenta
  sigue disponible.
- **No hay segundo factor.** Un usuario y una contraseña. Nada más.

Y hay un agravante que convierte la debilidad en exposición: **las credenciales
están publicadas, y publicadas en el propio servidor web.**
`documents/CREDENCIALES.md` se sirve por HTTP con código 200, y
`database/seed.sql` también. No hay que adivinar nada: basta con un `GET`.

---

## 2. Dónde está el código

### 2.1 Las credenciales, en claro, en un archivo publicado

`documents/CREDENCIALES.md`, sección 2:

```markdown
| Usuario | Contraseña | Nombre | Rol | Uso |
|---|---|---|---|---|
| `admin` | `Admin_2026!` | Carlos Mendoza | Administrador (1) | Acceso total, gestión de usuarios |
| `tecnico` | `Tecnico_2026!` | Lucia Ferrer | Técnico (2) | Equipos, asignaciones y mantenimientos |
| `consulta` | `Consulta_2026!` | Miguel Sanz | Consulta (3) | Solo lectura |
```

y sección 2.2, las cuatro secundarias con el mismo formato. El documento lleva
un aviso de que son de demostración, lo cual es cierto y no cambia nada: el
archivo está en el `DocumentRoot`, no en un gestor de secretos, y cualquier
petición lo descarga.

Verificado en este entorno:

```bash
curl -s -o /dev/null -w "%{http_code}\n" \
  http://localhost/fa/sistemas/documents/CREDENCIALES.md          # 200
curl -s -o /dev/null -w "%{http_code}\n" \
  http://localhost/fa/sistemas/database/seed.sql                  # 200
curl -s http://localhost/fa/sistemas/database/seed.sql | grep -c "Admin_2026!"   # 1
```

Que el `.sql` esté también en el `DocumentRoot` lo apunta el propio README del
proyecto en su sección 8, y `CREDENCIALES.md` lo explica en su sección 5: los
archivos de `database/` se descargan por HTTP y contienen los MD5 de las
contraseñas, que se revierten en segundos. Eso es CWE-798 en su forma más
literal: la credencial está escrita en un archivo de texto que forma parte del
producto.

### 2.2 El login acepta la contraseña tal cual

`login.php`, líneas 33 a 55:

```php
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
        ...
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user_row['id'];
        $_SESSION['username'] = $user_row['username'];
        $_SESSION['role_id']  = (int) $user_row['role_id'];
        $_SESSION['login_time'] = time();
```

Tres cosas que conviene leer con atención porque son las que hacen válida esta
ficha:

- **No hay comparación de complejidad.** La contraseña se manda al módulo de
  autenticación tal cual. Si la fila existe y el estado es `Activo`, entra.
- **No hay nada después de `login_time`.** Se escribe la sesión y se redirige.
  No hay segundo factor, ni aviso al usuario, ni marcado de sesión como
  "dispositivo nuevo", ni nada que delate un acceso ajeno.
- El único filtro real es `estado = 'Activo'`. Sirve, y se ve en el caso de
  `a.delgado` (sección 4), pero es un campo de negocio, no una política de
  seguridad.

### 2.3 Cómo se guardan

`database/schema.sql`, líneas 49 y 50:

```sql
`password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt via password_hash()',
`password_legacy` CHAR(32) DEFAULT NULL COMMENT 'MD5 heredado. Solo lo usa el modulo legacy de login.php',
```

Y `users/user_form.php`, líneas 144 a 152, escribe las dos columnas cada vez que
se crea un usuario:

```php
$hash = password_hash($password, PASSWORD_DEFAULT);
db_exec(
    "INSERT INTO users (nombre, apellido, email, username, password, password_legacy, role_id, estado)
     VALUES (?, ?, ?, ?, ?, MD5(?), ?, ?)",
    array(..., $hash, $password, ...)
);
```

Es decir: **la contraseña débil se guarda dos veces**, una bien (bcrypt) y una
mal (MD5 sin sal, determinístico, reversible con tablas precalculadas). Por
mucho bcrypt que haya, la columna `password_legacy` anula el beneficio: quien
consiga leer `seed.sql` —y se lee por HTTP— recupera las siete contraseñas sin
tocar la aplicación. Detalle de LAB-01, pero es un agravante directo de esta
ficha: la debilidad de la contraseña no está solo en lo elegido por el usuario,
está también en cómo se conserva.

### 2.4 Lo que no existe

Estas cuatro ausencias se comprueban buscando, y no se encuentra nada:

| Control | Estado | Comprobación |
|---|---|---|
| Caducidad de contraseña | **No existe** | La tabla `users` (`schema.sql`, 43 a 62) no tiene columna de último cambio de contraseña, y ninguna consulta compara la antigüedad del hash |
| Rotación | **No existe** | No hay ningún código que obligue a cambiar la clave en el primer inicio de sesión ni tras un periodo |
| Bloqueo por intentos | **No existe** | `login_attempts` solo recibe `INSERT` (`config/audit.php`, línea 55) y se lee únicamente para pintarlo en pantalla (`audit/index.php`, línea 86). Nadie lo consulta para denegar un acceso |
| Segundo factor | **No existe** | `login.php` escribe cuatro claves en la sesión y redirige. No hay TOTP, ni correo de confirmación, ni pregunta de seguridad |

Que `login_attempts` registre los intentos fallidos es lo que hace visible
LAB-09, y es un buen comienzo: pero **registrar no es bloquear**. La tabla
llena, la pantalla muestra, y la cuenta sigue abierta.

### 2.5 La única política que existe

Hay que decirlo con precisión, porque "no hay política de contraseñas" no es
literalmente cierto en el formulario de alta. `users/user_form.php`, líneas 99 a
104:

```php
if ($password !== '' && strlen($password) < 8) {
    $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
}
if ($password !== '' && $password !== $password2) {
    $errores[] = 'La contrasena y su confirmacion no coinciden.';
}
```

Dos reglas: ocho caracteres y confirmar el campo. No hay verificación contra
diccionarios de contraseñas comunes, ni contra el nombre de usuario, ni contra
el nombre de la empresa, ni contra años recientes, ni comprobación de
variedad de caracteres, ni lista de contraseñas filtradas, ni عامل de
aleatoriedad.

Y la contraseña `Admin_2026!` tiene once caracteres, con mayúscula, minúscula,
dígito y símbolo: **cumple las dos reglas**. La política no solo es débil, es
ineficaz contra el patrón que hay en la base, porque la plantilla del
seed ya cumple el único requisito que se exige. Y `a.delgado` (trece
caracteres), `r.ibarra` (doce) y el resto pasan el mismo filtro sin el menor
esfuerzo.

---

## 3. Por qué existe en una app real

La contraseña de un sistema interno casi nunca se elige mal por descuido en el
momento de crearla. Se elige mal en un ritual anual, con prisa, y después nadie
la vuelve a mirar. El resultado es siempre el mismo: un patrón homogéneo, con
el año pegado, que medio mundo usa en sus propias cuentas.

La historia que hay detrás de estas siete cuentas es fácil de reconstruir y
suficientemente real:

> En enero de 2026 alguien tuvo que crear las cuentas del personal nuevo. Para no
> tener que inventar una contraseña distinta por persona, aplicó la misma receta:
> el identificador del correo corporativo y el año, con un símbolo al final para
> que el sistema de bloques del correo corporativo la aceptara. Las cuentas se
> crearon, se mandaron por correo, y nadie volvió a mirarlas. La receta quedó
> escrita en un documento para que el siguiente que llegara pudiera crear
> cuentas sin preguntar.

Ese "símbolo al final" es el detalle que más se parece a un sistema real. Nadie
añade un `!` porque quiera seguridad: lo añade porque la política del correo
corporativo exige un símbolo, y esa es una política de longitud y composición,
no de entropía. Una contraseña como `Admin_2026!` pasa todos los filtros que la
empresa puede tener escritos en un documento y sigue siendo adivinable en
segundos, porque el espacio de posibilidades real no es el de 62^11, sino el de
siete cuentas y dos plantillas conocidas.

Lo segundo que hay que entender es **por qué no hay caducidad ni rotación**. No
es falta de tiempo: es que nadie tiene una razón para pedirla. La contraseña se
cambia cuando alguien se olvida de ella, y no se olvida nunca. Sin una
obligación —una auditoría, una política corporativa, un proceso de altas y
bajas— la contraseña de 2026 sobrevive a 2026, a 2027 y a la persona que la
eligió. Y sin el mismo proceso que la crea, tampoco hay un momento en el que
alguien revise las cuentas que quedan.

La tercera causa es de proceso, y es la más importante para un laboratorio: las
contraseñas están en un archivo de texto porque **alguien necesitaba que fueran
reproducibles**. En una demostración, en una instalación, en un manual de
despliegue, en un curso. Es una necesidad real, con una solución legítima: un
gestor de secretos, un `.env` fuera del `DocumentRoot`, una variable de entorno
inyectada en el despliegue, un documento en la intranet con control de acceso.
Lo que no debería ocurrir es que la respuesta a "necesito las credenciales de
prueba" sea "escríbelas en un Markdown que Apache sirve a cualquiera".

La cuarta, y la que conecta esta ficha con LAB-01: el módulo de autenticación
vulnerable ya acepta cualquier cosa que llegue en el campo de contraseña
(`lab_login_vulnerable()`, `config/lab_auth.php`, línea 84). Aunque la
inyección SQL se arreglara mañana, estas siete contraseñas seguirían entrando.
Son dos defectos independientes que un mismo cambio no arregla.

---

## 4. Pasos de reproducción

Probado contra `http://localhost/fa/sistemas`. Cada prueba es un `POST` a
`login.php` seguido de una petición a `dashboard.php` con la misma cookie: si
aparece el saludo del panel, el acceso se concedió.

```bash
# Referencia: login correcto de administrador
curl -s -o /dev/null -w "%{http_code}\n" -c cookies-admin.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
curl -s -b cookies-admin.txt http://localhost/fa/sistemas/dashboard.php | grep -c "Bienvenido"
```

Resultado verificado: `302` en el login y `1` apariciones de `Bienvenido` en el
panel. El control negativo, con una contraseña inventada, devuelve `200` en el
login y `0` en el panel.

### Las siete cuentas

| # | Usuario | Contraseña | Rol | Login | Dashboard | Acceso concedido |
|---:|---|---|---:|---|---|---|
| 1 | `admin` | `Admin_2026!` | 1 Administrador | `302` | `Bienvenido` | **Sí — control total** |
| 2 | `tecnico` | `Tecnico_2026!` | 2 Técnico | `302` | `Bienvenido` | Sí |
| 3 | `consulta` | `Consulta_2026!` | 3 Consulta | `302` | `Bienvenido` | Sí |
| 4 | `m.quiroga` | `Quiroga_2026!` | 3 Consulta | `302` | `Bienvenido` | Sí |
| 5 | `a.delgado` | `Delgado_2026!` | 2 Técnico | `200` | — | **No: cuenta `Inactivo`** |
| 6 | `r.ibarra` | `Ibarra_2026!` | 1 Administrador | `302` | `Bienvenido` | **Sí — segundo administrador** |
| 7 | `s.navarro` | `Navarro_2026!` | 3 Consulta | `302` | `Bienvenido` | Sí |

Seis de las siete entran. La séptima (`a.delgado`) tiene la contraseña correcta y
aun así la aplicación la rechaza, porque su campo `estado` es `Inactivo`
(`login.php`, línea 43). Conviene decirlo tal cual en la demostración, porque es
un dato a favor de la aplicación: hay una validación de negocio funcionando. La
debilidad de la contraseña sigue ahí, y en cuanto alguien reactive la cuenta
entra con `Delgado_2026!`.

Nota sobre el caso 6: `r.ibarra` es un **segundo administrador**, con la misma
debilidad. Un solo administrador comprometido es un incidente; dos, con
contraseñas que salen de la misma plantilla, es un incidente sin dueño. Por eso la
tabla siguiente no trata a las cuatro cuentas personales como un caso menor.

### Debilidad por cuenta

| Cuenta | Rol | Contraseña | Longitud | Cómo se deduce | Debilidad principal | En Escenario B |
|---|---|---|---:|---|---|---|
| `admin` | 1 Administrador | `Admin_2026!` | 11 | El nombre del rol es una de las tres palabras más obvias del sistema, y las tres están en `CREDENCIALES.md` | **Acceso total** con una palabra de diccionario más un año | Sigue entrando |
| `tecnico` | 2 Técnico | `Tecnico_2026!` | 13 | Igual que `admin`; además el rol está documentado en `CREDENCIALES.md` | Permite operar equipos y asignaciones, y leer la auditoría | Sigue entrando |
| `consulta` | 3 Consulta | `Consulta_2026!` | 14 | Igual que `admin` | **Habilita LAB-03**: con esta cuenta se recorre `employees/view.php?id=1..22` | Sigue entrando |
| `m.quiroga` | 3 Consulta | `Quiroga_2026!` | 13 | El usuario es `inicial.apellido`; la contraseña es el mismo apellido | Objetivo de la fuerza bruta de LAB-09: cuenta real, activa y conocida | Sigue entrando |
| `a.delgado` | 2 Técnico | `Delgado_2026!` | 13 | Igual que la anterior | Debilidad latente: la plantilla es conocida, la cuenta está `Inactivo` y se reactiva sin cambiar la clave | Sigue entrando si se reactiva |
| `r.ibarra` | 1 Administrador | `Ibarra_2026!` | 12 | Igual que `m.quiroga` | **Segundo administrador** con la misma plantilla: una sola receta compromete a dos cuentas privilegiadas | Sigue entrando |
| `s.navarro` | 3 Consulta | `Navarro_2026!` | 13 | Igual que `m.quiroga` | Tercera cuenta de solo lectura con clave derivada del apellido | Sigue entrando |

La última columna es el punto pedagógico de la tabla, y por eso está en todas
las filas y no solo en la primera: **ninguna de estas siete cuentas cambia de
comportamiento en Escenario B**, porque la petición es siempre válida.

### La cadena completa, en cuatro comandos

```bash
# 1. El atacante no adivina: descarga el archivo
curl -s http://localhost/fa/sistemas/documents/CREDENCIALES.md | grep -c "Admin_2026!"

# 2. Entra como administrador
curl -s -o /dev/null -c cookies-admin.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php

# 3. Descarga el seed y confirma el patron de las demas cuentas
curl -s http://localhost/fa/sistemas/database/seed.sql | grep -oE "'(admin|tecnico|consulta)'" | sort -u

# 4. Y con la cuenta de solo lectura ejecuta LAB-03
curl -s -o /dev/null -c cookies-consulta.txt \
  --data-urlencode "username=consulta" --data-urlencode "password=Consulta_2026!" \
  http://localhost/fa/sistemas/login.php
curl -s -b cookies-consulta.txt "http://localhost/fa/sistemas/employees/view.php?id=17" \
  | grep -c 'class="badge bg-danger">Restringido'
```

Un detalle sobre el paso 1 que conviene no adornar: el paso 4 no necesita el
paso 1 en un sistema real, porque `users/index.php` está restringido a
Administrador (un rol Consulta recibe `403`, verificado) y sin acceso a la
gestión de usuarios la enumeración por la aplicación es más difícil. **Pero** en
este laboratorio el archivo que la publica está en el mismo servidor que la
aplicación, así que el acceso a los nombres de usuario no cuesta nada. En una
empresa real, la combinación habitual es peor que la suma de las partes: nombres
en el correo corporativo, credenciales antiguas en un gestor de contraseñas, y un
LDAP corporativo que acepta la contraseña de otra vez.

### Cómo lo verifica la matriz

`documents/lab_matrix.sh` separa esta práctica de los ataques con payload. No
busca un marcador de exploit: hace el login y mira si el panel abre. Las líneas
son las 368 a 373:

```bash
if login "$JAR_CON" "$C_ADMIN" "$C_PASS" && \
   curl -s -b "$JAR_CON" "$BASE_URL/dashboard.php" | grep -q "Bienvenido"; then
    result OK "admin / Admin_2026! entra al panel" "contrasena trivial aceptada"
    NO_CUBIERTO=$((NO_CUBIERTO + 1))
    echo "           NOTA: un WAF no detiene esto. La contrasena es correcta;"
    echo "           el WAF no puede saber cual es la correcta."
```

Dos detalles de esa lógica que la hacen honesta:

- **No hay modo inverso con `--waf`.** Para LAB-01 o LAB-02 la bandera invierte
  la expectativa: en Escenario A la prueba debe pasar, en B debe fallar. Aquí no
  hay inversión posible, porque no existe un estado en el que la contraseña
  correcta sea rechazada por una regla del WAF. El resultado es el mismo en los
  dos escenarios, y el script lo cuenta en `NO_CUBIERTO`.
- **La nota de dos líneas bajo el resultado** es el recordatorio de que un WAF
  no puede saber cuál es la contraseña correcta. Está escrita en el propio
  script, junto al código, para que nadie pueda "arreglar" la matriz cambiando la
  expectativa y haciendo pasar por bloqueado un acceso que fue válido.

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
| 1. Usuario `admin`, contraseña inventada (control) | `200`, mensaje de error, sin sesión | `200`, mensaje de error, sin sesión |
| 2. Usuario inexistente (control) | `200`, mensaje de error, sin sesión | `200`, mensaje de error, sin sesión |
| 3. `admin / Admin_2026!` | **`302`, entra al panel** | **`302`, entra al panel** |
| 4. `r.ibarra / Ibarra_2026!` | **Entra, segundo administrador** | **Entra, segundo administrador** |
| 5. `consulta / Consulta_2026!` y recorrido `id=1..22` | **22 de 22 fichas filtradas (LAB-03)** | **22 de 22 fichas filtradas (LAB-03)** |
| 6. `documents/CREDENCIALES.md` por HTTP | `200`, credenciales servidas | `200`, credenciales servidas |
| 7. Doce intentos fallidos seguidos (LAB-09) | 12 de 12 rechazados, **sin bloqueo** | **Puede** cortar la racha por límite de peticiones, no por credencial débil |
| 8. Fila `LOGIN` en `audit_log` | Se registra el acceso | Se registra el acceso |

Las filas 1, 2 y 6 son los controles. La 6 merece atención: **SafeLine no
protege un archivo estático.** `CREDENCIALES.md` es un `.md` que Apache sirve
desde el `DocumentRoot`, sin PHP, sin consulta a la base y sin regla que pueda
aplicarse a un archivo que existe tal cual en el disco. Publicar credenciales
en un archivo del servidor web no es un problema de WAF: es un problema de
dónde se guarda el secreto.

La fila 7 es la única de la tabla donde se espera un cambio de comportamiento, y
conviene ser preciso sobre por qué: no es que SafeLine sepa que la contraseña es
débil, es que corta por **volumen de peticiones**. Es el caso de LAB-09, y es
una mitigación parcial real, no un bloqueo del problema. Quien prueba tres
contraseñas al día no toca ningún límite.

### Escenario B todavía no se ha ejecutado

Conviene decirlo sin rodeos, porque es el punto donde estas fichas suelen
mentir: **SafeLine no está instalado en este entorno.** Se desplegará después, en
el VPS, escuchando en el puerto `8000` y reenviando a `http://127.0.0.1:80`. Todo
lo que aparece en la columna derecha de la tabla es **esperado**, no medido.

```bash
# Escenario B - detrás de SafeLine (aún no ejecutado)
curl -s -o /dev/null -c cookies-admin.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://192.168.100.22:8000/fa/sistemas/login.php
curl -s -b cookies-admin.txt http://192.168.100.22:8000/fa/sistemas/dashboard.php | grep -c "Bienvenido"
```

Ese `1` es el resultado que hay que esperar, y también el que hay que filmar: el
WAF funcionando, con las seis prácticas de payload bloqueadas en la misma
ejecución, y el panel de administración abierto en la pantalla siguiente.

---

## 6. Qué debería hacer SafeLine

**No debería bloquear el acceso. Y no es un defecto del WAF: es la definición de
lo que un WAF puede y no puede ver.**

La petición que concede el acceso es, byte a byte, la misma que hace el usuario
legítimo:

```http
POST /fa/sistemas/login.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

username=admin&password=Admin_2026%21
```

No hay comillas, ni metacarácteres, ni rutas, ni etiquetas, ni operadores de
sistema, ni una longitud anómala, ni una secuencia repetida. La contraseña es
correcta. Para cualquier análisis de petición, **esta petición es un login
exitoso y normal**, y detenerla sería bloquear el producto.

| Lo que SafeLine ve | Lo que necesitaría para detenerlo |
|---|---|
| Un `POST` a `/login.php` con dos campos | Que esa contraseña está en la lista de contraseñas filtradas de la empresa |
| Código 302 y `Location: dashboard.php` | Que la contraseña es correcta y que la política de la empresa exige otra |
| Un único intento desde una IP | Que el mismo usuario se autentica desde tres países |
| Cero patrones de ataque | Que `Admin_2026!` es predecible |

Ninguna de las cuatro filas es una propiedad de la petición. Todas son
propiedades del **contexto**: de la política de la organización, del historial de
la cuenta, de la lista de contraseñas comprometidas, del comportamiento del
usuario. Un WAF no tiene ninguna de esas cuatro cosas.

Lo que sí puede aportar SafeLine, y conviene decir porque es útil de verdad:

| Capacidad | Qué mitiga | Límite |
|---|---|---|
| **Límite de peticiones** (`rate limiting`) sobre `login.php` | LAB-09: frena la racha de fuerza bruta | No sabe si la contraseña es débil. Quien prueba despacio no lo nota |
| **Detección de bots** | Automatización de intentos y relleno de credenciales masificado (*credential stuffing*) | Un atacante que navega como una persona no lo es |
| **Alerta por volumen de `LOGIN` exitosos** desde una IP o un ASN | Robo de credenciales masificado | Requiere que alguien mire la alerta |
| **Protección de la URL de la consola** (`https://192.168.100.22:9443`) | Que el panel de administración del WAF sea el punto de entrada de un atacante | Es el único elemento de la lista que sí es un punto de entrada real |

La cuarta fila es la que un WAF sí resuelve, y por eso el despliegue de la Fase
14 importa: la consola de SafeLine en el puerto `9443` es una superficie de
ataque nueva, y va a necesitar el mismo cuidado que cualquier otro panel de
administración.

Todo lo demás es de proceso y de código. Y la lista es corta:

1. **Política de contraseñas** real: longitud mínima de 12 o más, verificación
   contra listas de contraseñas filtradas, prohibición de reutilizar el nombre
   de usuario, comprobación de que no contenga el nombre del usuario ni el de
   la empresa.
2. **Caducidad y rotación**: forzar el cambio en el primer inicio de sesión y
   en cada cambio de puesto. Sin ese proceso, la política de la línea 1 se
   cumple el día del alta y se olvida para siempre.
3. **Bloqueo temporal** tras `N` intentos fallidos, con desbloqueo
   administrativo y registro del bloqueo. La tabla `login_attempts` ya existe y
   ya se llena: falta la consulta que la use para denegar.
4. **Segundo factor** (TOTP como mínimo) en las cuentas privilegiadas. Con
   segundo factor, la debilidad de la contraseña deja de ser el problema
   principal.
5. **No documentar credenciales reales** en el repositorio: gestor de secretos
   o variable de entorno, y una cuenta de servicio por entorno con permisos
   mínimos.
6. **Eliminar `password_legacy`.** Mientras la columna MD5 exista, bcrypt no protege
   nada, porque basta con leer `seed.sql`.

La sexta es la más mecánica de las seis, y además arrastra a la primera: mientras
`password_legacy` exista, `login.php` tiene que seguir comparando contra el MD5,
así que borrar la columna obliga a conectar `secure_login()`, que es la corrección
de LAB-01. Es el mismo cambio visto desde dos fichas distintas: la función correcta
ya existe y nunca se conectó.

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- La debilidad no está en un archivo, sino en el **estado de las cuentas**: en
  las siete contraseñas elegidas y en las cuatro políticas que no existen. Por eso
  esta ficha no tiene un `archivo.php` con la línea culpable, y por eso su
  "punto vulnerable" es `login.php`, el único camino de autenticación que hay.
- **LAB-08 no depende de LAB-01.** Las siete contraseñas de la tabla son las
  correctas. Aunque `lab_login_vulnerable()` se reemplazara mañana por
  `secure_login()`, estas credenciales seguirían entrando. Son dos defectos
  independientes, y la matriz los mide por separado: la inyección SQL se
  clasifica como ataque con payload, y esto como defecto de la aplicación.
- **LAB-08 no depende de LAB-09.** No hay bloqueo de cuenta, pero eso es otra
  práctica, con su propia línea en la matriz. Aquí se mide que la contraseña
  débil concede acceso por sí sola, sin ningún intento fallido previo. En la
  tabla de la sección 4 no hay ni un solo intento fallido: siete cuentas, siete
  contraseñas correctas.
- **LAB-08 y LAB-03 se encadenan, pero no se confunden.** `consulta /
  Consulta_2026!` es la credencial débil, y el recorrido `id=1..22` es el
  IDOR. Arreglar cualquiera de los dos deja el otro abierto. Se mide cada uno
  con su propio marcador, y por eso la matriz reporta `OK` en las dos filas
  correspondientes a los dos escenarios.
- El único punto de la aplicación que *escribe* contraseñas es
  `users/user_form.php`, y lo hace con `password_hash()` y las dos columnas. No
  hay ningún otro `INSERT` ni `UPDATE` sobre `users.password` en el sistema, así
  que el estado de las cuentas está determinado por el seed y por ese formulario,
  y en ninguno de los dos hay una comprobación que rechace una contraseña
  predecible.
- El alcance está verificado: `login.php` es el único endpoint que autentica.
  No hay API alternativa, ni tokens de servicio, ni impersonalización, ni ningún
  otro punto del sistema que autentique.

---

## 8. La conclusión del laboratorio

Esta ficha cierra el argumento que empezó en LAB-01, y lo hace por el lado
opuesto. En LAB-01, SafeLine ve un payload y lo detiene: el WAF funciona. Aquí
SafeLine ve un inicio de sesión correcto y lo deja pasar: el WAF también
funciona, porque no tiene nada que objetar. **La diferencia entre las dos
situaciones no es la calidad del WAF, es la naturaleza del defecto.** Uno es un
ataque con firma; el otro es una decisión de diseño tomada hace años en un
formulario de alta.

La conclusión práctica es incómoda y hay que decirla completa: un WAF protege
contra el atacante que mandó un payload. No protege contra el atacante que usa
las credenciales que le dieron, ni contra el desarrollador que no puso una
comprobación de rol. En una auditoría, las dos prácticas que no se van a poder
cerrar con una regla —LAB-03 y LAB-08— son precisamente las que más daño hacen
en un incidente real, porque no requieren ninguna habilidad: una URL con otro
número y una contraseña escrita en un archivo de texto.

Y hay una segunda conclusión, sobre cómo se demonstra esto sin engañar a nadie.
La tentación, al montar una demostración de WAF, es poner en la tabla de
resultados todo lo que se bloquea y dejar fuera lo que no. Sería más favorable y
sería falso. `lab_matrix.sh` hace lo contrario: separa los hallazgos en dos
contadores, `Detenidos por el WAF` y `No cubiertos por el WAF`, y en el resumen
del Escenario B escribe que los defectos de autorización y de credenciales siguen
abiertos "como debe ser". Esa frase es la lección entera del laboratorio, y
está en el script, no solo en la documentación: si mañana alguien cambiara la
expectativa de estas dos pruebas para que el informe saliera limpio, el
comentario de la línea 362 lo delataría.

La corrección real es una lista de seis puntos y ninguna de ellos es una regla de
WAF. La más urgente no es técnica: es mover las credenciales fuera del
`DocumentRoot` y decidir quién puede leerlas. Y la más satisfactoria es
comprobar que, cuando la contraseña se elige al azar y no con una plantilla, el
ataque más simple de todos deja de funcionar — no porque un filtro lo bloqueara,
sino porque no hay nada que adivinar.

`Admin_2026!` seguirá entrando en Escenario B. Esa es la demostración completa.
