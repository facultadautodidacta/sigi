# SIGI — Sistema de Gestión de Inventario Informático

Aplicación web de inventario de activos tecnológicos (equipos, empleados, marcas,
categorías, asignaciones y mantenimientos), construida como **laboratorio de seguridad**
para demostrar cómo un WAF como [Chaitin SafeLine](https://docs.waf.chaitin.com/)
detiene ataques reales contra una aplicación tradicional.

> **Aviso.** Esta aplicación contiene vulnerabilidades deliberadas y se ejecuta
> sin aislamiento de red. No desplegarla en una red con exposición pública.

---

## 1. Documentación

### Fichas de vulnerabilidad

| Ficha | Vulnerabilidad | Riesgo |
|---|---|---|
| [`LAB-01-SQLi.md`](LAB-01-SQLi.md) | SQL Injection en autenticación | Crítico |
| [`LAB-02-SQLi-Busqueda.md`](LAB-02-SQLi-Busqueda.md) | SQL Injection en la búsqueda de equipos | Alto |
| [`LAB-03-IDOR.md`](LAB-03-IDOR.md) | IDOR en datos personales de empleados | Alto |
| [`LAB-04-XSS.md`](LAB-04-XSS.md) | XSS almacenado de segunda orden | Medio |
| [`LAB-05-Path-Traversal.md`](LAB-05-Path-Traversal.md) | Recorrido de rutas (LFI) | Alto |
| [`LAB-06-Unrestricted-Upload.md`](LAB-06-Unrestricted-Upload.md) | Subida de archivos sin restricción → RCE | Crítico |
| [`LAB-07-Command-Injection.md`](LAB-07-Command-Injection.md) | Inyección de comandos | Crítico |
| [`LAB-08-Credenciales.md`](LAB-08-Credenciales.md) | Credenciales débiles y sin caducidad | Alto |
| [`LAB-09-Fuerza-Bruta.md`](LAB-09-Fuerza-Bruta.md) | Fuerza bruta sin bloqueo | Medio |

### Scripts y otros documentos

| Archivo | Contenido |
|---|---|
| [`lab_matrix.sh`](lab_matrix.sh) | **Matriz completa**: ejecuta las 9 prácticas y sirve para los dos escenarios |
| [`lab01_sqli.sh`](lab01_sqli.sh) | Matriz ampliada de LAB-01, útil para la primera grabación |
| [`CREDENCIALES.md`](CREDENCIALES.md) | Los 7 usuarios de prueba y las credenciales de MariaDB |

### Cómo se ejecuta la matriz

El mismo script corre en los dos escenarios; solo cambia la bandera `--waf`,
que invierte la expectativa de cada prueba.

```bash
# Escenario A — Apache directo, todo debe funcionar
./documents/lab_matrix.sh

# Escenario B — detrás de SafeLine, los ataques con payload deben bloquearse
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf
```

---

## 2. Pila tecnológica

| Componente | Versión | Notas |
|---|---|---|
| PHP | 8.3.6 | `mod_php` sobre Apache. Sin frameworks ni ORM |
| Apache | 2.4.58 | `DocumentRoot` en `/var/www/html` |
| MariaDB | 10.11.14 | Base de datos `sigi` |
| Docker | 29.8.1 | Se usará para desplegar SafeLine |

Bootstrap 5.3.3, jQuery 3.7.1 y Font Awesome 6.5.2 se sirven **locales** desde
`assets/`. No se usa ningún CDN: durante las pruebas con SafeLine, el tráfico
hacia terceros contaminaría los resultados del WAF.

---

## 3. Estructura

```
fa/sistemas/
├── config/          Configuración, conexión, auditoría y autenticación
│   ├── app.php        Constantes, sesión, roles, helpers
│   ├── db.php         Conexión mysqli y funciones de consulta
│   ├── audit.php      Registro de auditoría e intentos de acceso
│   ├── lab_auth.php   LAB-01: autenticación vulnerable
│   ├── lab_search.php LAB-02: búsqueda vulnerable + versión segura de referencia
│   └── lab_store.php  LAB-04: salida sin escapar de contenido almacenado
├── database/        schema.sql y seed.sql
├── includes/        header.php y footer.php comunes
├── assets/          CSS, JS y webfonts locales
├── users/           CRUD de usuarios
├── documents/       Esta documentación
│
├── equipment/       Equipos: listado, ficha, búsqueda, alta y edición
├── employees/       Directorio y ficha de empleados
├── assignments/     Asignaciones y devoluciones
├── audit/           Visor de auditoría e intentos de acceso
├── lab/             Consola del laboratorio y páginas de práctica
│   └── contratos/     Documentos de ejemplo para el visor
├── uploads/         Archivos subidos (LAB-06)
│
├── brands/          (Fase 4)  pendiente
├── categories/      (Fase 5)  pendiente
├── maintenance/     (Fase 8)  pendiente
└── reports/         (Fase 9)  pendiente
```

Las carpetas vacías corresponden a módulos de fases futuras. El menú lateral
solo muestra los módulos cuyo archivo existe, así que los enlaces rotos no
aparecen hasta que el módulo esté implementado.

---

## 4. Base de datos

11 tablas. Los datos son ficticios y están diseñados para que la aplicación
parezca un sistema real en uso.

| Tabla | Registros | Contenido |
|---|---:|---|
| `roles` | 3 | Administrador, Técnico, Consulta |
| `users` | 7 | Usuarios de la aplicación (uno `Inactivo`) |
| `employees` | 22 | Personal en 6 departamentos, con datos personales restringidos |
| `brands` | 7 | Dell, HP, Lenovo, ASUS, Acer, Apple, Microsoft |
| `categories` | 8 | Desktop, Laptop, Servidor, Monitor, Impresora, Switch, Router, UPS |
| `equipment` | 55 | 40 Asignado · 7 Disponible · 4 Mantenimiento · 4 Baja |
| `assignments` | 44 | 40 activas + 4 devoluciones históricas |
| `maintenance` | 18 | Registros de mantenimiento preventivo y correctivo |
| `audit_log` | 32 | Bitácora de acciones, con historial de ~2,5 años |
| `login_attempts` | 20 | Registro de intentos de acceso de los últimos 7 días |
| `lab_documentos` | 2 | Catálogo del visor de contratos (LAB-05) |

### Fechas del seed

El seed no usa fechas fijas: todas las marcas de tiempo se calculan con
`CURDATE()` e `INTERVAL`, de forma que el conjunto representa siempre un
sistema en uso activo, sin importar el día en que se importe.

| Serie | Antigüedad |
|---|---|
| `users.created_at` | de ~3 años a hoy |
| `users.updated_at` | de ~3 años a hace 5 semanas |
| `assignments.fecha_asignacion` | de ~5 años a hace 4 días |
| `assignments.fecha_devolucion` | de ~3 años a hace 14 meses |
| `maintenance.maintenance_date` | de ~10 meses a hoy, incluidos 2 programados a futuro |
| `equipment.updated_at` | de ~14 meses a hace 1 día |
| `audit_log.created_at` | de ~2,5 años a ayer |
| `login_attempts.created_at` | de los últimos 7 días a hoy |
| `equipment.purchase_date` | 2015–2023, fechas absolutas (antigüedad de los activos) |

Los equipos con intervención reciente (ids 24, 34, 44, 53 y 55) tienen
`updated_at` anclada a los últimos días, para que el bloque *Equipos
actualizados recientemente* del dashboard no aparezca plano.

### Nota sobre la zona horaria

Ni `schema.sql` ni `seed.sql` fijan `time_zone`, a propósito: se ejecutan en
la zona del servidor (CST, UTC−6), que es la misma que usa PHP
(`date_default_timezone_set('America/Mexico_City')` en `config/app.php`) y en la
que MariaDB interpreta las columnas `TIMESTAMP` al operar la aplicación.
Fijar `+00:00` corría el seed un día por delante de lo que mostraba el sistema.

### Reglas de negocio verificadas

Cinco consultas de control deben devolver siempre `0`:

1. Ningún equipo en estado `Asignado` sin asignación activa.
2. Ningún equipo con asignación activa en estado distinto de `Asignado`.
3. Ningún equipo con más de una asignación activa.
4. Ningún equipo en `Mantenimiento` o `Baja` con asignación activa.
5. Ningún empleado `Inactivo` con equipo asignado.

### Nota sobre `password_legacy`

La columna `password_legacy CHAR(32)` guarda el **MD5** de la contraseña y es la que
compara el módulo de autenticación vulnerable (`LAB-01`). La columna `password`
guarda el **bcrypt** correcto y es la que verifica `secure_login()`.

Esa duplicación es intencional: representa una migración de MD5 a bcrypt a medio
terminar, donde el código viejo nunca se reemplazó.

---

## 5. Instalación desde cero

```bash
# 1. Crear la base de datos
mysql -uadmin -p -e "CREATE DATABASE sigi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Crear el usuario acotado de la aplicación
mysql -uadmin -p -e "
  CREATE USER 'sigi'@'localhost' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_SEGURA';
  GRANT SELECT, INSERT, UPDATE, DELETE ON sigi.* TO 'sigi'@'localhost';
  FLUSH PRIVILEGES;"

# 3. Configurar las credenciales locales de la aplicacion
cp config/db.example.php config/db.php
# Editar config/db.php y sustituir CHANGE_ME por la clave configurada arriba.

# 4. Importar esquema y datos
mysql -uadmin -p < database/schema.sql
mysql -uadmin -p < database/seed.sql
```

La aplicación se sirve en:

- `http://localhost/fa/sistemas/`
- `http://192.168.100.22/fa/sistemas/` (red local)

---

## 6. Escenarios de demostración

El objetivo es comparar **el mismo código y el mismo tráfico** antes y después
de poner SafeLine. El código PHP no debe modificarse entre ambos.

| Escenario | Origen | Resultado esperado |
|---|---|---|
| **A** — sin WAF | `http://192.168.100.22/fa/sistemas/` | Las 9 prácticas se explotan |
| **B** — con SafeLine | `http://192.168.100.22:8000/fa/sistemas/` | Las de payload se bloquean; las de autorización siguen abiertas |

SafeLine escuchará en el puerto `8000` y reenviará a `http://127.0.0.1:80`.
No es necesario mover Apache ni crear un vhost.

### Qué protege SafeLine y qué no

Esta es la parte honesta de la demostración, y conviene no ocultarla:

| Práctica | La detiene SafeLine | Por qué |
|---|---|---|
| LAB-01 SQLi en login | Sí | Payload reconocible en el POST |
| LAB-02 SQLi en búsqueda | Sí | Payload con `SLEEP`, `OR`, comentarios |
| LAB-04 XSS almacenado | Sí | Etiqueta `<script>` en el POST |
| LAB-05 Path Traversal | Sí | Secuencia `../` en el parámetro |
| LAB-06 Subida sin control | Sí | Adjunto con extensión ejecutable |
| LAB-07 Command Injection | Sí | Metacaracteres de shell (`;`, `|`, `$()`) |
| LAB-09 Fuerza bruta | Parcial | El rate limiting por IP corta la racha, pero se evade con IP distribuidas |
| **LAB-03 IDOR** | **No** | La petición es un GET legítimo con otro id; el WAF no conoce el modelo de permisos |
| **LAB-08 Credenciales débiles** | **No** | La contraseña que se usa es la correcta; no hay payload ni anomalía que detectar |

Un WAF analiza la petición, no la lógica de autorización ni la política de
contraseñas. LAB-03 y LAB-08 se resuelven en el código y en el proceso, no con
un WAF. `lab_matrix.sh` los reporta en una categoría aparte
(`No cubiertos por el WAF`) precisamente para que el resultado no los maquille.

### Comandos

```bash
# Escenario A
./documents/lab_matrix.sh

# Escenario B
./documents/lab_matrix.sh http://192.168.100.22:8000/fa/sistemas --waf
```

---

## 7. Despliegue en el VPS

SafeLine **no** se instala en esta máquina de laboratorio. Se despliega después
en un VPS para no contaminar las mediciones del Escenario A.

Topología prevista:

```
Navegador ──► SafeLine :8000 ──► Apache 127.0.0.1:80
                  │
                  └─ consola SafeLine :9443
```

Pasos previstos:

1. Levantar el VPS con la aplicación servida por Apache en el puerto 80.
2. Importar `database/schema.sql` y `database/seed.sql` para dejar la base
   en el estado inicial.
3. Instalar SafeLine con Docker y crear un sitio cuyo backend sea
   `http://127.0.0.1:80`.
4. Verificar primero el **Escenario A** contra Apache directo, con la matriz.
5. Cambiar el destino a SafeLine y correr la misma matriz con `--waf`.
6. Contrastar ambas corridas y, sobre todo, la tabla `login_attempts`: en el
   Escenario A debe llenarse con la racha de intentos; en el B, quedar vacía
   porque las peticiones no llegaron a la aplicación.

Esa última comparación es la evidencia más clara de que el bloqueo ocurrió
antes de la aplicación y no dentro de ella.

---

## 8. Estado de las fases

| Fase | Módulo | Estado |
|---|---|---|
| 1 | Esquema, seed e integridad de datos | Completada |
| 2 | Login, roles, auditoría, dashboard, CRUD de usuarios | Completada |
| 3 | Equipos | Completada |
| 4 | Marcas | Pendiente |
| 5 | Categorías | Pendiente |
| 6 | Empleados | Completada |
| 7 | Asignaciones y devoluciones | Completada |
| 8 | Mantenimientos | Pendiente |
| 9 | Reportes y exportación a PDF | Pendiente |
| 10 | Auditoría con filtros | Completada |
| 11 | Endurecimiento del panel | Pendiente |
| 12 | Consola del laboratorio | Completada |
| 13 | Documentación | Completada (9 fichas + matriz) |
| 14 | Instalación de SafeLine y capturas | Pendiente (se hará en el VPS) |

### Vulnerabilidades

Las nueve están implementadas y verificadas con `lab_matrix.sh`.

| ID | Tipo | Riesgo | Estado |
|---|---|---|---|
| LAB-01 | SQL Injection en autenticación | Crítico | **Verificada** |
| LAB-02 | SQL Injection en búsqueda de equipos | Alto | **Verificada** |
| LAB-03 | IDOR en datos de empleados | Alto | **Verificada** — no la cubre un WAF |
| LAB-04 | XSS almacenado de segunda orden | Medio | **Verificada** |
| LAB-05 | Path Traversal (LFI) | Alto | **Verificada** |
| LAB-06 | Subida de archivos sin control → RCE | Crítico | **Verificada** |
| LAB-07 | Command Injection | Crítico | **Verificada** |
| LAB-08 | Credenciales débiles | Alto | **Verificada** — no la cubre un WAF |
| LAB-09 | Fuerza bruta sin bloqueo | Medio | **Verificada** — cobertura parcial del WAF |

---

## 9. Restricciones del entorno

- `AllowOverride None`: los archivos `.htaccess` **no funcionan**. Toda regla de
  Apache debe resolverse en la configuración del servidor, que requiere `sudo`.
- `/var/www/html` tiene permisos `777`: un `.php` que se depositen allí queda
  ejecutable. Riesgo preexistente del entorno.
- Los archivos `.sql` de `database/` son alcanzables por HTTP. Contienen hashes
  MD5, que son reversibles. Aislado en la red de laboratorio.
- El proyecto se despliega por HTTP sin TLS porque SafeLine debe terminar la
  conexión y reenviar por HTTP plano a Apache.
