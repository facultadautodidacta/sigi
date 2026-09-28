# LAB-01 — SQL Injection en autenticación

| Campo | Valor |
|---|---|
| **ID** | LAB-01 |
| **Tipo** | SQL Injection — Login Bypass |
| **Riesgo** | Crítico |
| **CVSS** | 9.8 |
| **CWE** | CWE-89 (Improper Neutralization of Special Elements used in an SQL Command) |
| **OWASP** | A03:2021 — Injection |
| **Endpoint** | `POST /fa/sistemas/login.php` |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

El formulario de autenticación permite iniciar sesión **sin conocer la
contraseña**, y devuelve la sesión de un administrador con privilegios totales.

No es un modo de prueba ni una función condicional: es el único camino de
autenticación que existe. Cualquier intento de login pasa por ahí.

---

## 2. Dónde está el código

### 2.1 La consulta vulnerable

`config/lab_auth.php`, función `lab_login_vulnerable()` (línea 74):

```php
$sql = "SELECT id, nombre, apellido, email, username, role_id, estado
          FROM users
         WHERE username = '$username'
           AND password_legacy = MD5('$password')
         LIMIT 1";

return db_row($sql);
```

Los valores de `$username` y `$password` se interpolan **directamente** en la
sentencia, sin consultas preparadas ni escape.

### 2.2 El consumo

`login.php`, línea 33:

```php
$user_row = lab_login_vulnerable($username_input, $password_input);
```

### 2.3 La corrección que existe pero no se usa

`config/lab_auth.php`, línea 102:

```php
function secure_login($username, $password)
{
    $row = db_row(
        "SELECT id, ... FROM users WHERE username = ?",
        array($username)
    );

    if ($row === null) { return null; }
    if (!password_verify($password, $row['password'])) { return null; }
    if ($row['estado'] !== 'Activo') { return null; }

    return $row;
}
```

`secure_login()` usa consulta preparada y `password_verify()` contra el bcrypt.
**Nunca es llamada.** Queda en el archivo como referencia de la corrección, que
es el punto central de la conclusión del laboratorio.

---

## 3. Por qué existe en una app real

No es un error de principiante. Es la forma en que se escribía el código antes
de que existieran las consultas preparadas, y la razón por la que sobrevive en
sistemas antiguos es la más común de todas:

> Se migró el algoritmo de contraseñas de MD5 a bcrypt. Se agregó la columna
> `password`, se escribió la función correcta, y **nunca se cambió el `require`
> del formulario de login**. El módulo viejo siguió atendiendo el tráfico porque
> funcionaba.

En esta aplicación la huella de esa migración quedó visible en el esquema:
`users` tiene dos columnas de contraseña, `password` (bcrypt, la correcta) y
`password_legacy` (MD5, la que el login realmente compara).

---

## 4. Detalle técnico: por qué la consulta va en una sola línea

Esta sección existe porque durante la implementación el bypass **no funcionaba**,
y la causa fue instructiva.

En MySQL, un comentario SQL con `--` **solo anula el resto de la línea donde
aparece**. La primera versión del código tenía la consulta partida en varias
líneas:

```php
// ESTA VERSION NO FUNCIONA
$sql = "SELECT id, ...
          FROM users
         WHERE username = '$username' -- '
           AND password_legacy = MD5('$password')
         LIMIT 1";
```

Con la inyección `admin' -- `, el comentario anula el resto de la línea del
`WHERE`, pero la línea siguiente seguía ejecutándose:

```sql
WHERE username = 'admin'     -- '     ← el comentario termina en el salto de línea
  AND password_legacy = MD5('loquesea')   ← esta línea SIGUE VIVA
LIMIT 1
```

Como `loquesea` no es la contraseña de admin, la condición era falsa, la consulta
no devolvía filas y el ataque era rechazado. El WAF no tenía nada que bloquear
porque la app se comportaba bien por accidente.

La corrección fue escribir el `WHERE` en una sola línea, que además es la forma
clásica de este tipo de código:

```php
// ESTA VERSION SI FUNCIONA
$sql = "SELECT id, ... FROM users WHERE username = '$username' AND password_legacy = MD5('$password') LIMIT 1";
```

### Regla del payload

MySQL exige **espacio en blanco después de `--`** para tomarlo como comentario.

| Payload | Resultado |
|---|---|
| `admin' -- ` (con espacio) | Funciona |
| `admin'#` (sin espacio, sin barra) | Funciona |
| `admin'--` (sin espacio) | **No funciona** — error de sintaxis SQL |

---

## 5. Payloads

Probados contra `http://localhost/fa/sistemas/login.php`.

| # | Usuario | Contraseña | Resultado | Sesión obtenida |
|---:|---|---|---|---|
| 1 | `admin` | `Admin_2026!` | Entra | Carlos Mendoza |
| 2 | `admin` | incorrecta | Rechazado | — |
| 3 | `noexiste` | cualquiera | Rechazado | — |
| 4 | `admin' -- ` | cualquiera | **Entra** | Carlos Mendoza (admin) |
| 5 | `admin' #` | cualquiera | **Entra** | Carlos Mendoza (admin) |
| 6 | `' OR '1'='1' -- ` | cualquiera | **Entra** | Carlos Mendoza (admin) |
| 7 | `' OR 1=1#` | cualquiera | **Entra** | Carlos Mendoza (admin) |

Todos los payloads usan una contraseña arbitraria. La inyección neutraliza la
comprobación.

### Variante a mano

```bash
curl -s -i -X POST http://localhost/fa/sistemas/login.php \
  --data-urlencode "username=admin' -- " \
  --data-urlencode "password=loquesea"
```

Respuesta: `HTTP/1.1 302 Found` con `Location: .../dashboard.php` y el cookie de
sesión `SIGISESSID`. Seguir ese cookie abre el panel como administrador.

---

## 6. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) |
|---|---|---|
| 1. Login válido | Entra | Entra |
| 2. Login inválido | Rechazado | Rechazado |
| 3. Usuario inexistente | Rechazado | Rechazado |
| 4. `admin' -- ` | **Entra como admin** | **Bloqueado** |
| 5. `admin' #` | **Entra como admin** | **Bloqueado** |
| 6. `' OR '1'='1' -- ` | **Entra como admin** | **Bloqueado** |
| 7. `' OR 1=1#` | **Entra como admin** | **Bloqueado** |

Las tres primeras filas son el control: deben comportarse igual en ambos
escenarios, para demostrar que el WAF no rompe el uso normal.

Ejecución automatizada con [`lab01_sqli.sh`](lab01_sqli.sh):

```bash
# Escenario A
./documents/lab01_sqli.sh

# Escenario B
./documents/lab01_sqli.sh http://192.168.100.22:8000/fa/sistemas --waf
```

---

## 7. Qué debería hacer SafeLine

SafeLine analiza el cuerpo del `POST` y debe clasificar los payloads como
inyección SQL antes de que la solicitud llegue a Apache.

| Aspecto | Valor esperado |
|---|---|
| Regla activada | Inyección SQL |
| Modo recomendado | `Strict` |
| Respuesta | Página de bloqueo de SafeLine, no el formulario de la app |
| Origen en la app | Ninguna: el request no debe llegar a `login.php` |
| Efecto secundario esperado | `login_attempts` no debe crecer; SafeLine corta antes de la app |

El seed siembra historial en `audit_log` (32 filas) y `login_attempts` (20
filas), así que la comprobación es un **delta**, no un valor absoluto:

```sql
SELECT COUNT(*) FROM login_attempts;   -- repetir antes y despues del Escenario B
```

Si el conteo no aumenta, el bloqueo ocurrió **antes** de la aplicación y no en
ella. En el Escenario A el mismo conteo sí aumenta, porque la petición entra a
`login.php` y se registra.

Ten en cuenta que `lab01_sqli.sh --waf` corre los 9 casos: los 5 de control
(deben seguir entrando a la app) más los 4 de inyección (deben quedar
bloqueados). El delta esperado en `login_attempts` corresponde a los 5 casos
de control, no a 9.

---

## 8. Aislamiento de la vulnerabilidad

Regla del proyecto: **una sola función vulnerable en toda la aplicación.**

- `lab_login_vulnerable()` está en `config/lab_auth.php` y solo la llama `login.php`.
- Ningún otro módulo concatena entrada de usuario en una consulta.
- El resto de las consultas usan `db_query()`, `db_row()` y `db_all()` con
  parámetros vinculados.
- `secure_login()` existe pero no se invoca, para que la corrección sea visible.

---

## 9. La conclusión del laboratorio

SafeLine bloquea el ataque, y eso es una defensa real. Pero un WAF es una capa
adicional, no la solución: si alguien desactiva el proxy, escribe otro cliente
o encuentra un camino que el WAF no inspecciona, la vulnerabilidad sigue ahí.

La corrección real es una línea: cambiar el `require` de `login.php` para que
llame a `secure_login()` en lugar de `lab_login_vulnerable()`, y eliminar la
columna `password_legacy`.

Esa función ya está escrita y probada. Nunca se conectó.
