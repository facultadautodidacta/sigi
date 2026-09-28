# Credenciales

> **Aviso.** Estas credenciales son de demostración y están publicadas a
> propósito dentro de un laboratorio aislado. No son credenciales reales y no
> deben usarse fuera de este entorno.

Todas las contraseñas fueron verificadas contra la base de datos `sigi` el
**26 de septiembre de 2026**.

---

## 1. URLs de acceso

| Escenario | URL |
|---|---|
| Apache directo (Escenario A, sin WAF) | `http://localhost/fa/sistemas/` |
| Apache por red local | `http://192.168.100.22/fa/sistemas/` |
| SafeLine (Escenario B, Fase 14) | `http://192.168.100.22:8000/fa/sistemas/` |
| Consola de SafeLine | `https://192.168.100.22:9443` |

---

## 2. Usuarios de la aplicación

### 2.1 Cuentas principales

Estas tres son las que conviene usar en la demostración del video.

| Usuario | Contraseña | Nombre | Rol | Uso |
|---|---|---|---|---|
| `admin` | `Admin_2026!` | Carlos Mendoza | Administrador (1) | Acceso total, gestión de usuarios |
| `tecnico` | `Tecnico_2026!` | Lucia Ferrer | Técnico (2) | Equipos, asignaciones y mantenimientos |
| `consulta` | `Consulta_2026!` | Miguel Sanz | Consulta (3) | Solo lectura |

### 2.2 Cuentas secundarias

| Usuario | Contraseña | Nombre | Rol | Estado | Uso |
|---|---|---|---|---|---|
| `m.quiroga` | `Quiroga_2026!` | Marta Quiroga | Consulta (3) | Activo | Objetivo del ataque de fuerza bruta (LAB-09) |
| `a.delgado` | `Delgado_2026!` | Ana Delgado | Técnico (2) | **Inactivo** | Prueba del rechazo por estado inactivo |
| `r.ibarra` | `Ibarra_2026!` | Raul Ibarra | Administrador (1) | Activo | Segundo administrador |
| `s.navarro` | `Navarro_2026!` | Sofia Navarro | Consulta (3) | Activo | Tercera cuenta de solo lectura |

> `a.delgado` está **Inactivo**: aunque las credenciales sean correctas, el login
> la rechaza. Sirve para mostrar que el sistema valida el estado de la cuenta.

### 2.3 Permisos por rol

| Rol | ID | Puede hacer |
|---|---:|---|
| Administrador | 1 | Todo, incluida la gestión de usuarios y la auditoría |
| Técnico | 2 | Equipos, asignaciones, mantenimientos. Sin acceso a usuarios |
| Consulta | 3 | Solo lectura |

### 2.4 Control de acceso

Verificar que el control por rol funciona: `tecnico` y `consulta` reciben
**HTTP 403** al abrir `/users/index.php`.

```bash
# admin -> 200
curl -s -o /dev/null -w "%{http_code}\n" -c /tmp/c.txt \
  --data-urlencode "username=admin" --data-urlencode "password=Admin_2026!" \
  http://localhost/fa/sistemas/login.php
curl -s -o /dev/null -w "%{http_code}\n" -b /tmp/c.txt \
  http://localhost/fa/sistemas/users/index.php

# tecnico -> 403
curl -s -o /dev/null -w "%{http_code}\n" -c /tmp/t.txt \
  --data-urlencode "username=tecnico" --data-urlencode "password=Tecnico_2026!" \
  http://localhost/fa/sistemas/login.php
curl -s -o /dev/null -w "%{http_code}\n" -b /tmp/t.txt \
  http://localhost/fa/sistemas/users/index.php
```

### 2.5 Control de acceso de los módulos del inventario

Los roles sí se aplican en las páginas de escritura. Esto es importante para la
demostración, porque contrasta con LAB-03: el rol se comprueba al **escribir**,
pero no al **leer** datos sensibles de otro empleado.

| Página | admin | tecnico | consulta |
|---|---:|---:|---:|
| `dashboard.php` | 200 | 200 | 200 |
| `equipment/index.php` | 200 | 200 | 200 |
| `equipment/create.php` | 200 | 200 | **403** |
| `employees/index.php` | 200 | 200 | 200 |
| `employees/view.php?id=1` | 200 | 200 | **200 ← LAB-03** |
| `assignments/index.php` | 200 | 200 | 200 |
| `assignments/create.php` | 200 | 200 | **403** |
| `assignments/return.php` | 200 | 200 | **403** |
| `audit/index.php` | 200 | 200 | **403** |
| `lab/documentos.php` | 200 | 200 | 200 |
| `lab/subidas.php` | 200 | 200 | **403** |
| `users/index.php` | 200 | **403** | **403** |

La fila de `employees/view.php` es la única donde el rol de solo lectura recibe
datos que no debería ver. Ese contraste es el argumento del laboratorio.

---

## 3. Credenciales de MariaDB

### 3.1 Usuario acotado de la aplicación

Es el que usa `config/db.php`. Puede leer y modificar filas, pero **no** crear
ni eliminar tablas.

| Campo | Valor |
|---|---|
| Usuario | `sigi` |
| Contraseña | Configurar localmente; no se publica en este repositorio |
| Base de datos | `sigi` |
| Host | `localhost` |
| Permisos | `SELECT`, `INSERT`, `UPDATE`, `DELETE` |

```bash
mysql -usigi -p sigi
```

La contraseña se solicita de forma interactiva. Configura la conexión de PHP
en `config/db.php`, a partir de `config/db.example.php`; ese archivo local está
excluido de Git.

### 3.2 Administrador global

Se usa para el DDL: crear la base, importar `schema.sql` y `seed.sql`, o hacer
`ALTER`. **La aplicación nunca usa esta cuenta.**

| Campo | Valor |
|---|---|
| Usuario | `admin` |
| Contraseña | La configurada en MariaDB; no se publica en este repositorio |
| Permisos | Globales, con `GRANT OPTION` |

```bash
mysql -uadmin -p
```

---

## 4. Dónde vive cada cosa

| Dato | Ubicación |
|---|---|
| Contraseñas en texto plano | Este documento y los comentarios de `database/seed.sql` |
| Hash bcrypt (`users.password`) | `database/seed.sql` |
| Hash MD5 (`users.password_legacy`) | `database/seed.sql` |
| Usuario y clave de la app | `config/db.php` |
| Contraseña en el navegador | Cookie de sesión `SIGISESSID` |

---

## 5. Nota sobre el hash MD5

La columna `password_legacy` guarda `MD5(password)`. **MD5 no es un hash de
contraseña**: no tiene sal, es determinístico y se revierte en segundos con
tablas precalculadas.

Esto importa para el video: cualquiera que descargue `database/seed.sql` por
HTTP obtiene esos MD5 y puede reconstruir todas las contraseñas de la tabla sin
tocar la aplicación. Es la razón por la que la app guarda dos columnas, y
justifica la conclusión final del laboratorio: el WAF agrega una capa de
defensa, pero la corrección real es reemplazar ese módulo por `secure_login()`.

---

## 6. Reiniciar el estado de la base

Para volver a los datos iniciales durante la demo:

```bash
mysql -uadmin -p sigi < database/schema.sql
mysql -uadmin -p sigi < database/seed.sql
```

El seed es reproducible: todas las fechas se calculan con `CURDATE()`, así que
reiniciarlo deja el conjunto siempre con actividad reciente y con el estado de
partida que espera el dashboard.

Requiere el usuario `admin` de MariaDB: el usuario de la aplicación (`sigi`) solo
tiene permisos `SELECT, INSERT, UPDATE, DELETE` y no puede crear el esquema.

Y para limpiar solo los registros que generan las pruebas:

```sql
DELETE FROM audit_log;
DELETE FROM login_attempts;
```

> Esto borra también el historial que trae el seed (32 y 20 filas). Para
> restaurar una base de demostración limpia, vuelve a ejecutar
> `schema.sql` + `seed.sql` en lugar de borrar a mano.

### Lo que deja `lab_matrix.sh`

La matriz es **repetible**: no deja la base en un estado raro.

- La asignación que crea LAB-04 se cierra con la propia aplicación
  (`assignments/return.php`), así que el equipo vuelve a `Disponible` y el
  inventario queda igual que antes de la prueba. La fila permanece como
  historial, con el payload dentro de `observaciones`.
- El archivo que sube LAB-06 se borra de `uploads/`.
- Sí se acumulan filas en `audit_log` y `login_attempts`, y es lo deseable:
  esa acumulación es justamente la evidencia que se compara entre los dos
  escenarios.

Para volver al estado de partida entre capturas:

```bash
mysql -uadmin -p sigi < database/schema.sql
mysql -uadmin -p sigi < database/seed.sql
```

Conviene hacerlo **entre** el Escenario A y el B, para que la comparación de
`login_attempts` no arrastre los intentos de la corrida anterior.
