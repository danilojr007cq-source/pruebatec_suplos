# CRUD de licitaciones (ofertas) — PHP nativo + Eloquent + Vue.js + Bootstrap

Aplicación para administrar ofertas/licitaciones, cargar actividades desde un archivo CSV y asociarlas a cada oferta. Usa PHP nativo, Eloquent ORM, Vue.js 3 y Bootstrap 5.3.

## Requisitos

- PHP 8.x con las extensiones `pdo_mysql` y `mbstring`.
- MySQL o MariaDB.
- Composer instalado (las dependencias ya están en `vendor/`).
- Navegador moderno con JavaScript habilitado.

## Estructura del proyecto

```text
pruebatec_suplos/
├── Config/
│   └── Conexion.php                    # Conexión de Eloquent a MySQL
├── Controlador/
│   ├── OfertasControlador.php          # Listado, AJAX crear/editar, detalle
│   ├── ValidadorCargasControlador.php  # Carga CSV y autocompletado de actividades
│   └── ProductoControlador.php         # Heredado de la práctica inicial (sin rutas activas)
├── Modelo/
│   ├── Ofertas.php                     # Modelo de la tabla ofertas
│   ├── OfertaDocumento.php             # Modelo de ofertas_documentos
│   ├── Actividad.php                   # Modelo de actividades UNSPSC
│   └── Producto.php                    # Heredado de la práctica inicial (sin rutas activas)
├── Vista/
│   ├── listar.php                      # Listado con filtros por columna y paginación
│   ├── crear.php                       # Crear oferta (AJAX)
│   ├── editar.php                      # Editar oferta (AJAX)
│   ├── detalle.php                     # Detalle consolidado con pestañas
│   ├── validador_cargas.php            # Carga del CSV con progreso
│   └── partials/
│       ├── formulario_oferta.php       # Formulario compartido crear/editar
│       ├── encabezado.php
│       └── pie.php
├── index.php                           # Enrutador de acciones
├── query_BD_mysql.sql                  # Esquema de la base de datos
├── DOCUMENTACION_OFERTAS.md            # Documentación del módulo de ofertas
├── DOCUMENTACION_ACTIVIDADES.md        # Documentación del módulo de actividades
└── README_CRUD.md                      # Este documento
```

## Base de datos

El esquema está en `query_BD_mysql.sql`. Las tablas principales son:

- `actividades`: catálogo UNSPSC (segmento, familia, clase y producto).
- `ofertas`: licitaciones u ofertas registradas.
- `ofertas_documentos`: documentos asociados a una oferta.

La tabla `ofertas` usa un `consecutivo` único que genera el servidor con el formato `OF-YYYYMMDD-XXXXXX`.

## Flujo principal

1. **Cargar actividades:** `index.php?accion=validador_cargas` toma `csv_suplos_temp.csv` de la raíz e inserta las actividades si la tabla está vacía.
2. **Crear una oferta:** `index.php?accion=crear`. El formulario busca la actividad por AJAX y envía los datos con `fetch` a `index.php?accion=guardar_oferta`.
3. **Listar y filtrar:** `index.php?accion=listar`. Permite filtrar por cada columna y paginar los resultados del lado del servidor.
4. **Editar:** desde el listado o el detalle. El formulario se envía por AJAX a `index.php?accion=actualizar_oferta`.
5. **Ver detalle:** `index.php?accion=detalle&id=ID`. Consolida información general, actividad, cronograma, documentos y auditoría en pestañas.

## Endpoints

| Endpoint | Método | Descripción |
|---|---|---|
| `index.php?accion=listar` | GET | Listado con filtros y paginación. |
| `index.php?accion=crear` | GET | Formulario de creación. |
| `index.php?accion=guardar_oferta` | POST (AJAX) | Crea la oferta y responde JSON. |
| `index.php?accion=editar&id=ID` | GET | Formulario de edición con datos precargados. |
| `index.php?accion=actualizar_oferta` | POST (AJAX) | Actualiza la oferta y responde JSON. |
| `index.php?accion=detalle&id=ID` | GET | Detalle consolidado con pestañas. |
| `index.php?accion=obtener_actividades&q=texto` | GET (AJAX) | Autocompletado de actividades (máximo 15). |
| `index.php?accion=validador_cargas` | GET | Vista de carga de actividades. |
| `index.php?accion=procesar_carga` | POST (NDJSON) | Procesa el CSV con progreso. |

Los alias `guardar` y `actualizar` se conservan por compatibilidad y apuntan a los mismos endpoints AJAX.

## Configurar la conexión

Editar `Config/Conexion.php`:

```php
'host'     => 'localhost',
'database' => 'crud_pruebatec',
'username' => 'root',
'password' => '',
```

## Ejecutar localmente

Desde la raíz del proyecto:

```bash
php -S localhost:8000
```

Abrir: `http://localhost:8000/`

## Validaciones y buenas prácticas aplicadas

- Validación doble: Vue.js en el cliente y PHP en el servidor.
- Consultas con Eloquent y parámetros vinculados (sin SQL concatenado).
- Escapado de salida con `htmlspecialchars` para evitar XSS.
- Respuestas JSON con códigos HTTP adecuados: `201` al crear, `200` al actualizar, `422` en validación, `405` si el método no es POST, `404` si no existe y `500` ante errores internos.
- Generación del consecutivo en el servidor y verificación de unicidad.
- Filtros y paginación del lado del servidor para no cargar toda la tabla.
- Uso de relaciones Eloquent para actividad y documentos.
- Redirección al listado 4 segundos después de guardar correctamente.

## Documentación adicional

- [Módulo de ofertas: AJAX, listado, filtros, paginación y detalle](DOCUMENTACION_OFERTAS.md)
- [Módulo de actividades: carga desde CSV y autocompletado AJAX](DOCUMENTACION_ACTIVIDADES.md)

## Notas

- Los archivos `ProductoControlador.php` y `Producto.php` pertenecen a la práctica inicial y ya no tienen rutas activas en `index.php`.
- La subida de documentos a `ofertas_documentos` no está implementada; la vista de detalle solo muestra los documentos existentes.
- No hay autenticación ni protección CSRF porque el proyecto es una práctica local sin sesiones. Para un entorno real se recomienda agregarlas.
