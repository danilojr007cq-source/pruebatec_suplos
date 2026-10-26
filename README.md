# Prueba técnica Suplos — CRUD de licitaciones

Una aplicación sencilla para administrar **ofertas/licitaciones**: crearlas, editarlas, filtrarlas, ver su detalle, adjuntar documentos y exportar el listado a Excel. También permite cargar el catálogo de actividades (UNSPSC) desde un CSV y buscarlas con autocompletado.

Está hecha con **PHP nativo**, **Eloquent ORM**, **Vue.js 3** y **Bootstrap 5**, sin frameworks de frontend ni backend de por medio. La idea era resolver la prueba con lo justo y dejar el código organizado en un MVC en español.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![Eloquent](https://img.shields.io/badge/Eloquent-ORM-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)

## ¿Qué hace?

- **CRUD de ofertas**: crear, listar, editar, ver detalle y eliminar.
- **Listado con filtros** por consecutivo, objeto, descripción, moneda, actividad, presupuesto y fechas, con paginación del lado del servidor.
- **Detalle en pestañas**: información general, actividad, cronograma, documentos y auditoría.
- **Documentos adjuntos**: carga de archivos PDF o ZIP asociados a cada oferta, con opción de reemplazarlos o eliminarlos.
- **Exportar a Excel**: descarga el listado filtrado completo en `.xlsx`.
- **Carga de actividades desde CSV** (`csv_suplos_temp.csv`) con barra de progreso.
- **Autocompletado de actividades** por nombre de producto, sin cargar las ~49.000 filas en un `<select>`.
- **Validación doble**: Vue.js en el navegador y PHP en el servidor, con respuestas JSON y códigos HTTP acordes.

## Tecnologías

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.1+ nativo, MVC propio |
| Base de datos | MySQL / MariaDB + Eloquent ORM |
| Frontend | Vue.js 3 y Bootstrap 5.3 (vía CDN) |
| Dependencias | Composer (`illuminate/database`, `illuminate/events`) |
| Reportes | Generación de XLSX con `ZipArchive` (sin librerías externas) |

## Requisitos

Antes de arrancar, necesitas tener instalado:

- **PHP 8.1 o superior** con las extensiones:
  - `pdo_mysql`
  - `mbstring`
  - `zip` (se usa para generar el Excel)
- **Composer 2**
- **MySQL 5.7+** o **MariaDB 10.3+**
- Un navegador moderno con JavaScript habilitado
- Conexión a internet para cargar Vue y Bootstrap desde CDN

> En Windows, XAMPP o Laragon ya traen casi todo. Solo revisa que las extensiones `mbstring`, `pdo_mysql` y `zip` estén habilitadas en `php.ini`.

## Instalación paso a paso

### 1. Clona el repositorio

```bash
git clone https://github.com/danilojr007cq-source/pruebatec_suplos.git
cd pruebatec_suplos
```

### 2. Instala las dependencias

La carpeta `vendor/` no se sube al repositorio, así que hay que generarla:

```bash
composer install
```

### 3. Crea la base de datos

El esquema está en `query_BD_mysql.sql` y crea las tablas `actividades`, `ofertas` y `ofertas_documentos`.

**Opción A — phpMyAdmin / MySQL Workbench**

1. Crea una base de datos llamada `crud_pruebatec`.
2. Selecciónala e importa el archivo `query_BD_mysql.sql`.

**Opción B — Consola**

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS crud_pruebatec"
mysql -u root -p --force crud_pruebatec < query_BD_mysql.sql
```

> El `--force` está ahí porque el script incluye su propio `CREATE DATABASE` al inicio y MySQL avisará que ya existe. El resto de las tablas se crea normal dentro de `crud_pruebatec`.

### 4. Configura la conexión

Edita `Config/Conexion.php` y ajusta tus credenciales:

```php
'host'     => 'localhost',
'database' => 'crud_pruebatec',
'username' => 'root',
'password' => '',
```

Si tu MySQL tiene contraseña, cámbiala ahí. Y si vas a publicar el proyecto, no subas credenciales reales al repositorio. 😉

### 5. Permisos para los documentos

La carpeta `documentos/` es donde se guardan los PDF y ZIP que se adjuntan a las ofertas. Normalmente se crea sola, pero el servidor web necesita permiso de escritura:

```bash
chmod -R 775 documentos
```

En Windows con XAMPP/Laragon no suele ser necesario tocar nada.

### 6. Levanta el proyecto

**Con el servidor embebido de PHP** (desde la raíz del proyecto):

```bash
php -S localhost:8000
```

Luego abre:

```text
http://localhost:8000/index.php?accion=listar
```

**Con Apache (XAMPP/Laragon/WAMP):** copia el proyecto dentro de `htdocs` o `www` y entra a `http://localhost/pruebatec_suplos/`.

> El archivo `.htaccess` sube los límites de subida a 50 MB cuando se usa Apache con PHP. Si usas `php -S`, esos valores no se aplican; si necesitas subir archivos grandes, ajusta `upload_max_filesize` y `post_max_size` en tu `php.ini`.

## Primer uso: cargar las actividades

Antes de crear ofertas hay que llenar el catálogo de actividades. El proyecto ya trae el archivo `csv_suplos_temp.csv` en la raíz (~49.000 registros).

1. Entra a `http://localhost:8000/index.php?accion=validador_cargas`.
2. Pulsa el botón para procesar la carga y espera el progreso.
3. Cuando termine, ya puedes buscar actividades desde el formulario de ofertas.

La carga solo inserta datos si la tabla `actividades` está vacía, así que no hay riesgo de duplicarla si entras dos veces. Ten en cuenta que ese CSV debe quedarse en la raíz del proyecto para poder hacer la carga inicial.

## Flujo rápido de la app

1. **Listado** (`index.php?accion=listar`): filtra, pagina y exporta las ofertas.
2. **Nueva oferta** (`index.php?accion=crear`): completa los datos, busca la actividad por autocompletado y guarda por AJAX.
3. **Detalle** (`index.php?accion=detalle&id=ID`): revisa toda la información de la oferta en pestañas y gestiona sus documentos.
4. **Editar** (`index.php?accion=editar&id=ID`): actualiza los datos sin recargar la página.

## Estructura del proyecto

```text
pruebatec_suplos/
├── Config/
│   └── Conexion.php                 # Conexión de Eloquent a MySQL
├── Controlador/
│   ├── OfertasControlador.php       # Listado, filtros, AJAX, detalle y documentos
│   ├── ValidadorCargasControlador.php # Carga del CSV y autocompletado
│   └── ReporteOfertasExcel.php      # Generación del .xlsx
├── Modelo/
│   ├── Ofertas.php
│   ├── OfertaDocumento.php
│   └── Actividad.php
├── Vista/
│   ├── listar.php
│   ├── crear.php
│   ├── editar.php
│   ├── detalle.php
│   ├── validador_cargas.php
│   └── partials/
├── documentos/                      # Archivos PDF/ZIP subidos
├── Public/js/                       # JavaScript del formulario
├── index.php                        # Enrutador principal
├── query_BD_mysql.sql               # Esquema de la base de datos
└── csv_suplos_temp.csv              # Catálogo de actividades para la carga inicial
```

## Rutas principales

| Ruta | Método | Descripción |
|---|---|---|
| `index.php?accion=listar` | GET | Listado con filtros y paginación |
| `index.php?accion=crear` | GET | Formulario de creación |
| `index.php?accion=guardar_oferta` | POST (AJAX) | Crea una oferta y responde JSON |
| `index.php?accion=editar&id=ID` | GET | Formulario de edición |
| `index.php?accion=actualizar_oferta` | POST (AJAX) | Actualiza una oferta |
| `index.php?accion=detalle&id=ID` | GET | Detalle con pestañas |
| `index.php?accion=obtener_actividades&q=texto` | GET (AJAX) | Autocompletado de actividades |
| `index.php?accion=validador_cargas` | GET | Vista de carga del CSV |
| `index.php?accion=procesar_carga` | POST | Procesa el CSV con progreso |
| `index.php?accion=exportar_excel` | GET | Descarga el listado en Excel |
| `index.php?accion=eliminar` | POST | Elimina una oferta |

## Notas

- Es un proyecto de prueba técnica, pensado para correr en local. **No tiene autenticación ni protección CSRF**; para un entorno real habría que agregarlas.
- La subida de documentos acepta únicamente `.pdf` y `.zip`, con un límite total de 20 MB por actualización.
- Las dependencias de frontend (Vue y Bootstrap) vienen por CDN, así que sin internet la interfaz no se verá bien.
- Si algo falla al generar el Excel, revisa que la extensión `zip` de PHP esté habilitada.

## Documentación adicional

- [Módulo de ofertas: AJAX, filtros, paginación y detalle](DOCUMENTACION_OFERTAS.md)
- [Módulo de actividades: carga desde CSV y autocompletado](DOCUMENTACION_ACTIVIDADES.md)
- [Resumen técnico del CRUD](README_CRUD.md)

---

Hecho para la prueba técnica de **Suplos** por **Danilo Cortes Quintana**.
