# Documentación del módulo de ofertas

Este documento describe la implementación del módulo de ofertas/licitaciones: creación y edición por AJAX, listado con filtros y paginación, y vista de detalle consolidada con pestañas.

## 1. Alcance

Se implementó:

- Formulario de creación de ofertas con validación en cliente y envío AJAX.
- Alerta Bootstrap con el resultado de la operación.
- Redirección automática al listado 4 segundos después de guardar o actualizar correctamente.
- Generación del consecutivo en el servidor.
- Listado de ofertas con todos los campos, filtros por columna, paginación y acciones **Editar** y **Detalle**.
- Botón **Exportar Excel** en la columna de acciones del listado, que genera desde el servidor un `.xlsx` con todos los registros filtrados, sin paginar.
- Edición por AJAX reutilizando el mismo formulario.
- Vista de detalle con pestañas: General, Actividad, Cronograma, Documentos y Auditoría.
- Validación completa en el servidor y respuestas JSON con códigos HTTP adecuados.

## 2. Archivos del módulo

| Archivo | Función |
|---|---|
| `Modelo/Ofertas.php` | Modelo Eloquent de `ofertas`; relaciones, generación de consecutivo, filtros y paginación. |
| `Modelo/OfertaDocumento.php` | Modelo de `ofertas_documentos` para la pestaña Documentos. |
| `Modelo/Actividad.php` | Catálogo de actividades; el autocompletado ahora devuelve también código y clase. |
| `Controlador/OfertasControlador.php` | Listado, creación, edición, detalle, validación y respuestas JSON. |
| `Controlador/ReporteOfertasExcel.php` | Genera el `.xlsx` del listado con PHP nativo y `ZipArchive`. |
| `Vista/partials/formulario_oferta.php` | Formulario compartido por crear y editar, con Vue.js y envío AJAX. |
| `Vista/crear.php` | Envoltorio de creación; incluye el formulario compartido. |
| `Vista/editar.php` | Envoltorio de edición; incluye el formulario compartido con datos precargados. |
| `Vista/listar.php` | Tabla de ofertas con filtros por columna, paginación y acciones. |
| `Vista/detalle.php` | Detalle consolidado con pestañas de Bootstrap. |
| `index.php` | Rutas `guardar_oferta`, `actualizar_oferta`, `detalle`, `exportar_excel` y alias. |

## 3. Modelo de datos

La tabla `ofertas` tiene las siguientes columnas:

| Columna | Tipo | Uso en el formulario |
|---|---|---|
| `id` | INT | Clave primaria; no se edita. |
| `consecutivo` | VARCHAR(255), único | Se genera en el servidor con formato `OF-YYYYMMDD-XXXXXX`. |
| `objeto` | VARCHAR(150) | Campo `objeto`. |
| `descripcion` | VARCHAR(400) | Campo `descripcion`. |
| `moneda` | VARCHAR(3) | Campo `moneda`: COP, USD o EUR. |
| `presupuesto` | DECIMAL(15,2) | Campo `presupuesto`. |
| `actividad_id` | INT | ID de la actividad seleccionada en el autocompletado. |
| `fecha_inicio` | DATE | Campo `fecha_inicio`. |
| `hora_inicio` | TIME | Campo `hora_inicio`; se guarda como `HH:MM:SS`. |
| `fecha_cierre` | DATE | Campo `fecha_cierre`. |
| `hora_cierre` | TIME | Campo `hora_cierre`; se guarda como `HH:MM:SS`. |
| `estado` | VARCHAR(20) | Campo `estado`: ACTIVO, PUBLICADA o EN_EVALUACION. |
| `creado_en` | DATETIME | Se asigna automáticamente al crear. |
| `actualizado_en` | DATETIME | Se actualiza en cada modificación. |

La relación con actividades se define así:

```php
public function actividad()
{
    return $this->belongsTo(Actividad::class, 'actividad_id', 'id');
}
```

La relación con documentos:

```php
public function documentos()
{
    return $this->hasMany(OfertaDocumento::class, 'licitacion_id', 'id');
}
```

## 4. Creación por AJAX

### 4.1 Flujo general

1. El usuario abre `index.php?accion=crear`.
2. Completa el formulario y selecciona una actividad del autocompletado.
3. Vue.js valida los campos antes de enviar.
4. El formulario se envía con `fetch` a `index.php?accion=guardar_oferta`.
5. PHP valida nuevamente, genera el consecutivo y guarda la oferta.
6. El servidor responde JSON.
7. Se muestra una alerta Bootstrap con el resultado.
8. Si la operación fue exitosa, después de 4 segundos se redirige a `index.php?accion=listar&mensaje=creado`.

### 4.2 Campos enviados

El formulario envía:

```text
objeto
descripcion
moneda
presupuesto
actividad        (ID de la actividad, en un input oculto)
fecha_inicio
hora_inicio
fecha_cierre
hora_cierre
estado
```

El `consecutivo`, `creado_en` y `actualizado_en` los genera el servidor. El usuario no puede modificarlos.

### 4.3 Validación en el cliente

El formulario usa Vue.js 3 con la API de composición. Las reglas son:

| Campo | Regla |
|---|---|
| `objeto` | Obligatorio, entre 3 y 150 caracteres. |
| `descripcion` | Obligatoria, máximo 400 caracteres. |
| `moneda` | Obligatoria; COP, USD o EUR. |
| `presupuesto` | Obligatorio, mayor a cero, máximo 13 enteros y 2 decimales. |
| `actividad` | Obligatoria; debe seleccionarse de la lista. |
| `fecha_inicio` / `hora_inicio` | Obligatorias y válidas. |
| `fecha_cierre` / `hora_cierre` | Obligatorias y posteriores al inicio. |
| `estado` | Obligatorio; ACTIVO, PUBLICADA o EN_EVALUACION. |

Los errores se muestran debajo de cada campo con las clases `is-invalid` e `invalid-feedback` de Bootstrap. Además, se muestra una alerta general cuando el formulario no es válido.

La fecha de cierre no puede ser anterior a la fecha de inicio y, si es el mismo día, la hora de cierre debe ser mayor que la hora de inicio. El formulario ajusta los atributos `min` de los campos de fecha y hora para ayudar al usuario.

### 4.4 Envío con `fetch`

El formulario compartido envía un `FormData`:

```js
const formulario = document.getElementById('formOferta');
const datos = new FormData(formulario);

const respuesta = await fetch(urlGuardar, {
    method: 'POST',
    body: datos,
    headers: { 'Accept': 'application/json' },
    credentials: 'include',
    cache: 'no-store'
});
```

Mientras se envía, el botón se deshabilita y muestra un spinner. Si ya se guardó correctamente, se bloquea el botón para evitar envíos duplicados.

### 4.5 Validación y guardado en el servidor

`OfertasControlador::guardarAjax()` realiza estas acciones:

1. Verifica que el método sea `POST`; si no, responde `405`.
2. Valida y normaliza los datos con `validarOferta()`.
3. Si hay errores, responde `422` con un objeto `errores` por campo.
4. Genera el consecutivo con `Ofertas::generarConsecutivo()`.
5. Asigna `creado_en` y `actualizado_en` con la fecha y hora actuales.
6. Crea la oferta con Eloquent.
7. Responde `201` con el ID y el consecutivo.

El consecutivo se genera así:

```php
$consecutivo = 'OF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
```

Antes de usarlo se verifica que no exista en la tabla. Si hubiera una colisión, se intenta generar otro.

### 4.6 Respuestas JSON

**Creación exitosa (`201`):**

```json
{
    "success": true,
    "mensaje": "La oferta se guardó correctamente.",
    "id": 1,
    "consecutivo": "OF-20261026-616408"
}
```

**Error de validación (`422`):**

```json
{
    "success": false,
    "mensaje": "Revise los campos marcados en el formulario.",
    "errores": {
        "objeto": "El objeto es obligatorio.",
        "presupuesto": "El presupuesto debe ser mayor a cero.",
        "fecha_cierre": "La fecha y hora de cierre debe ser posterior a la fecha y hora de inicio."
    }
}
```

**Método incorrecto (`405`):**

```json
{
    "success": false,
    "mensaje": "Método no permitido. Use POST."
}
```

**Error interno (`500`):**

```json
{
    "success": false,
    "mensaje": "No fue posible guardar la oferta. Intente nuevamente."
}
```

El error técnico se registra con `error_log()` y no se expone al usuario.

### 4.7 Alerta y redirección

La alerta se muestra en la parte superior del formulario:

- `alert-success` cuando la operación fue exitosa.
- `alert-danger` cuando hay errores de validación, conexión o servidor.

En caso exitoso se conserva el consecutivo en el mensaje y se programa la redirección:

```js
setTimeout(() => {
    const mensaje = esEdicion ? 'actualizado' : 'creado';
    window.location.href = 'index.php?accion=listar&mensaje=' + mensaje;
}, 4000);
```

### 4.8 Ciclo de vida del estado

El campo **Estado** quedó en el apartado **Información básica** del formulario y solo se muestra en las vistas de crear, editar y detalle. En el listado sigue oculto, tal como estaba.

Los valores que se guardan en la base son estos:

| Valor en base | Se muestra como | Cuándo se usa |
|---|---|---|
| `ACTIVO` | Activo | Estado inicial al crear la oferta. Es el único estado que permite editar. |
| `PUBLICADA` | Publicada | Se asigna automáticamente cuando llega la fecha y hora de inicio. |
| `EN_EVALUACION` | En evaluación | Se asigna automáticamente cuando llega la fecha y hora de cierre. |

Reglas que se aplicaron:

- El estado es obligatorio en el formulario y también en el servidor.
- Los dos endpoints AJAX (`guardar_oferta` y `actualizar_oferta`) validan con `in_array()` que el valor sea exactamente uno de los tres permitidos. Cualquier otro valor responde `422` con el error en el campo `estado`.
- `estado` se agregó al `$fillable` del modelo `Ofertas`, que antes no lo tenía. Por eso ahora sí se guarda al crear y se modifica al actualizar.
- Solo se pueden editar ofertas en estado `ACTIVO`. Si se intenta abrir el formulario de una oferta publicada o en evaluación, el controlador redirige al detalle con el mensaje `noeditable`. Si alguien intenta forzar el guardado por AJAX, recibe `403` con el mensaje **“Solo se pueden editar ofertas con estado Activo.”**
- En el detalle, el botón **Editar oferta** solo aparece cuando la oferta está activa y el estado se muestra con su etiqueta amigable.

Los cambios automáticos no dependen de un cron: se revisan cada vez que se entra al listado, al detalle, al formulario de edición, al exportar el Excel o después de crear/actualizar una oferta. La lógica está en `Ofertas::actualizarEstadosAutomaticos()` y hace esto:

1. Convierte estados viejos: `BORRADOR` y `ACTIVA` pasan a `ACTIVO`; `CERRADA` pasa a `EN_EVALUACION`; los registros sin estado también arrancan como `ACTIVO`.
2. Si una oferta sigue `ACTIVO` y ya llegó su fecha de inicio pero todavía no cierra, pasa a `PUBLICADA`.
3. Si está `ACTIVO` o `PUBLICADA` y ya llegó la fecha de cierre, pasa a `EN_EVALUACION`.

Cuando el cambio es automático también se actualiza `actualizado_en`, para que quede rastro en la pestaña de auditoría.

## 5. Edición por AJAX

La edición reutiliza `Vista/partials/formulario_oferta.php`.

1. El listado o el detalle enlazan a `index.php?accion=editar&id=ID`.
2. `OfertasControlador::editar()` actualiza primero los estados automáticos y verifica que la oferta siga en `ACTIVO`. Si no, redirige al detalle con `mensaje=noeditable`.
3. `OfertasControlador::editar()` carga la oferta con su actividad y prepara `$datosIniciales`.
4. El formulario muestra el consecutivo como dato informativo y precarga todos los campos, incluido el estado.
5. Al enviar, se usa el mismo `fetch` contra `index.php?accion=actualizar_oferta`.
6. El servidor vuelve a verificar el estado antes de guardar; si la oferta ya no está activa, responde `403`.
7. Si todo está bien, valida, actualiza `actualizado_en` y responde `200`.
8. Se muestra la alerta y se redirige a `index.php?accion=listar&mensaje=actualizado` después de 4 segundos.

El `consecutivo` no se actualiza durante la edición. El ID se envía en un campo oculto.

## 6. Listado con filtros y paginación

### 6.1 Columnas mostradas

El listado muestra:

- ID.
- Consecutivo.
- Objeto.
- Descripción (truncada en la tabla, completa en el atributo `title`).
- Moneda.
- Presupuesto.
- Actividad (producto y código).
- Fecha y hora de inicio.
- Fecha y hora de cierre.
- Fecha de creación.
- Fecha de actualización.
- Acciones: **Editar** y **Detalle**.

El estado no se muestra en el listado; solo aparece en crear, editar y detalle.

### 6.2 Filtros por columna

La fila de filtros está dentro del formulario GET del listado. Parámetros disponibles:

| Parámetro | Tipo | Efecto |
|---|---|---|
| `id` | Entero | Filtra por ID exacto. |
| `consecutivo` | Texto | Búsqueda parcial con `LIKE`. |
| `objeto` | Texto | Búsqueda parcial con `LIKE`. |
| `descripcion` | Texto | Búsqueda parcial con `LIKE`. |
| `moneda` | COP/USD/EUR | Igualdad. |
| `actividad` | Texto | Búsqueda parcial en `actividades.producto`. |
| `presupuesto_min` | Número | Presupuesto mayor o igual. |
| `presupuesto_max` | Número | Presupuesto menor o igual. |
| `fecha_desde` | Fecha | Fecha de inicio mayor o igual. |
| `fecha_hasta` | Fecha | Fecha de inicio menor o igual. |
| `por_pagina` | 10/25/50 | Tamaño de página. |
| `pagina` | Entero | Página solicitada. |

Los filtros de texto escapan los comodines `%` y `_` antes de enviarlos a MySQL. Los filtros numéricos y de fecha se validan antes de aplicarse. Las consultas usan parámetros vinculados de Eloquent, no concatenación de SQL.

### 6.3 Paginación

La paginación se hace en el servidor:

1. Se construye la consulta base con `leftJoin` a `actividades`.
2. Se aplican los filtros.
3. Se obtiene el total con `count()`.
4. Se calcula el total de páginas.
5. Se normaliza la página actual para evitar páginas fuera de rango.
6. Se aplican `skip()` y `take()`.
7. Se ordena por `ofertas.id` descendente.

El tamaño de página permitido es 10, 25 o 50. La vista muestra el rango actual, el total y enlaces de paginación que conservan todos los filtros.

### 6.4 Mensajes del listado

El listado reconoce estos mensajes de la URL:

- `creado`: alerta de éxito.
- `actualizado`: alerta de éxito.
- `eliminado`: alerta informativa (la acción de eliminar no está en la tabla).
- `noencontrado`: alerta de error.

### 6.5 Reporte en Excel

En el encabezado de la columna **Acciones** hay un botón verde **Exportar Excel**. El enlace es `index.php?accion=exportar_excel` y arrastra los filtros aplicados en ese momento; no manda `pagina` ni `por_pagina`, así que el reporte siempre sale completo.

El flujo es corto:

1. `OfertasControlador::exportarExcel()` lee los mismos filtros del listado y llama a `Ofertas::listarTodos()`.
2. `listarTodos()` reutiliza `listarPaginado()` pidiendo una sola página con el total de registros, así no hay que duplicar la lógica de filtros.
3. `Controlador/ReporteOfertasExcel.php` arma el `.xlsx` con PHP nativo y `ZipArchive`, y lo manda como descarga.

El archivo se llama `reporte_ofertas_AAAAMMDD_HHMMSS.xlsx`, trae la hoja **Ofertas**, encabezado fijo y autofiltro. Las columnas son las mismas del listado: Consecutivo, Objeto, Descripción, Presupuesto, Fecha inicio y Fecha cierre. La descripción va completa (en la tabla se recorta a 70 caracteres) y el presupuesto se escribe igual que en la vista, por ejemplo `COP 15.000.000,00`. Si el filtro no encuentra nada, el Excel sale solo con los encabezados.

No se instaló ninguna librería: el XLSX es un ZIP con los XML mínimos (workbook, hoja, estilos y relaciones). Si `ZipArchive` no estuviera habilitado, el controlador responde `500` con un mensaje y deja el error en el log.

## 7. Vista de detalle con pestañas

La vista `Vista/detalle.php` se carga con `index.php?accion=detalle&id=ID` y usa las pestañas de Bootstrap 5.3.

### Pestaña General

- ID.
- Consecutivo.
- Objeto.
- Descripción completa.
- Moneda.
- Presupuesto.
- Estado, con su etiqueta y color: **Activo**, **Publicada** o **En evaluación**.

El botón **Editar oferta** solo se muestra si el estado es `ACTIVO`. Si una oferta deja de estar activa y alguien entra a la fuerza a la URL de edición, el sistema lo devuelve al detalle con una alerta.

### Pestaña Actividad

Muestra la jerarquía UNSPSC asociada:

- ID de la actividad.
- Segmento (código y nombre).
- Familia (código y nombre).
- Clase (código y nombre).
- Producto (código y nombre).

Si la oferta no tiene actividad, se muestra un aviso informativo.

### Pestaña Cronograma

- Fecha y hora de inicio.
- Fecha y hora de cierre.
- Duración calculada entre ambos momentos.

### Pestaña Documentos

Lista los registros de `ofertas_documentos` asociados por `licitacion_id`:

- ID.
- Título.
- Descripción.
- Archivo (si es una URL `http` o `https`, se muestra como enlace).
- Fecha de creación.

Si no hay documentos, se muestra un estado vacío.

### Pestaña Auditoría

- ID.
- Consecutivo.
- Fecha de creación.
- Fecha de actualización.
- Estado actual.

## 8. Autocompletado de actividades

El formulario no carga las 49.022 actividades en un `<select>`. Usa un input de texto con búsqueda AJAX:

- Endpoint: `index.php?accion=obtener_actividades&q=texto`.
- Mínimo: 2 caracteres.
- Debounce: 300 ms.
- Máximo de resultados: 15.
- Respuesta: `{ success, total, actividades }`.
- Cada actividad incluye `id`, `producto`, `codigo_producto` y `clase`.
- Al seleccionar una opción, el ID se guarda en el input oculto `name="actividad"` y el nombre se muestra en el input visible.
- Si el usuario vuelve a escribir, se invalida la selección anterior.
- Se descartan respuestas fuera de orden mediante un contador de petición.
- La lista se cierra al seleccionar, con `Escape` o al hacer clic fuera.

El control de respuestas fuera de orden evita que una búsqueda lenta sobrescriba los resultados de una búsqueda más reciente.

## 9. Seguridad y buenas prácticas

- **Validación doble:** cliente con Vue.js y servidor con PHP.
- **Consultas parametrizadas:** Eloquent utiliza bindings; no se concatena SQL con datos del usuario.
- **Escapado de salida:** `htmlspecialchars` en todas las vistas.
- **Códigos HTTP correctos:** `201`, `200`, `422`, `404`, `405` y `500` según el caso.
- **Método POST** para crear y actualizar; los endpoints rechazan GET.
- **Consecutivo generado en el servidor** y verificado contra la restricción única.
- **Filtros y paginación del lado del servidor** para evitar cargar toda la tabla.
- **Manejo de errores:** los detalles técnicos se registran en el log y el usuario recibe un mensaje genérico.
- **Eliminación solo por POST**, aunque la acción no esté expuesta en el listado.

## 10. Cómo probar manualmente

1. Iniciar el servidor:

   ```bash
   php -S localhost:8000
   ```

2. Abrir `http://localhost:8000/index.php?accion=listar`.
3. Crear una oferta con **Nueva oferta**:
   - Completar objeto y descripción.
   - Seleccionar moneda y presupuesto.
   - Buscar una actividad con al menos 2 caracteres y seleccionarla.
   - Elegir fechas y horas. El estado inicia en **Activo** y también se puede cambiar desde el select.
   - Guardar.
4. Verificar la alerta de éxito y esperar la redirección al listado.
5. En el listado, probar filtros por consecutivo, moneda, actividad, presupuesto y fechas.
6. Con filtros puestos, pulsar **Exportar Excel** y confirmar que el archivo trae todos los resultados, no solo los de la página actual.
7. Cambiar el tamaño de página y navegar entre páginas.
8. Usar **Editar** para modificar una oferta activa y comprobar la actualización por AJAX.
9. Usar **Detalle** y recorrer las pestañas General, Actividad, Cronograma, Documentos y Auditoría. Confirmar que el estado se ve con su etiqueta.
10. Probar un envío inválido (por ejemplo, fechas invertidas) y comprobar los mensajes por campo.
11. Probar el tema del estado:
    - Enviar un `estado` inventado por AJAX y confirmar que responde `422`.
    - Crear una oferta con fecha de inicio futura y verificar que queda **Activo** y se puede editar.
    - Poner una fecha de inicio ya cumplida y recargar el listado o el detalle: debe pasar a **Publicada** y el botón de editar debe desaparecer.
    - Poner una fecha de cierre ya cumplida: debe pasar a **En evaluación** y el intento de edición debe responder `403` o redirigir al detalle con la alerta.

## 11. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| `php -l` en modelos, controlador, vistas y parcial | Sin errores de sintaxis. |
| Render de `listar`, `crear`, `editar` y `detalle` | HTTP 200 sin warnings de PHP. |
| Creación AJAX con datos válidos | HTTP 201, oferta insertada y consecutivo generado. |
| Validación con datos inválidos | HTTP 422 con errores por campo. |
| Actualización AJAX | HTTP 200 y datos actualizados en la base de datos. |
| GET a `guardar_oferta` | HTTP 405. |
| Filtros combinados | Resultado correcto por moneda, actividad, presupuesto y fechas. |
| Paginación con 12 registros | Página 1: 10 registros; página 2: 2 registros. |
| Detalle con actividad y sin documentos | Render correcto y estado vacío de documentos. |
| Autocompletado `q=Gatos` | Respuesta JSON con ID, producto, código y clase. |
| Exportar Excel con filtro y 12 registros | `.xlsx` válido con encabezado + 12 filas, aunque el listado pagina de a 10. |
| Exportar Excel sin resultados | `.xlsx` válido solo con el encabezado. |
| Enlace **Exportar Excel** con filtros | Conserva los filtros activos y no incluye `pagina`. |

Los registros temporales usados en las pruebas se eliminaron al finalizar.

## 12. Limitaciones y recomendaciones

- **Sin autenticación:** cualquier usuario con acceso a la URL puede crear o editar ofertas. Para producción se debe agregar login y control de roles.
- **Sin CSRF:** al no usar sesiones, no se implementó token CSRF. Se recomienda agregarlo si se incorpora autenticación.
- **Documentos:** la tabla y la pestaña existen, pero no hay formulario de carga ni descarga controlada. Se puede implementar un `input type="file"` con validación de tipo y tamaño.
- **Índices:** para grandes volúmenes de ofertas se recomienda indexar `estado`, `fecha_inicio` y `moneda`. La búsqueda con `LIKE '%texto%'` no usa índices.
- **Consecutivo:** el formato es legible y único, pero depende de la fecha del servidor. Si se requiere una secuencia institucional, se puede cambiar por una tabla de consecutivos.
- **Eliminación:** el endpoint existe solo por POST y no está expuesto en la interfaz. Si se habilita, conviene pedir confirmación y registrar auditoría.

## 13. Resumen

- La creación y edición de ofertas se hacen por AJAX con `fetch` y respuestas JSON.
- El servidor valida todos los campos, incluido el estado, y genera el consecutivo.
- El estado de la oferta avanza solo según las fechas: Activo, Publicada y En evaluación. Solo las activas se pueden editar.
- El listado muestra todos los campos, permite filtrar por columna y pagina en el servidor.
- El botón **Exportar Excel** descarga todo el resultado filtrado en un `.xlsx` generado en el backend con PHP nativo.
- Cada oferta tiene una vista de detalle con pestañas que consolida actividad, cronograma, documentos y auditoría.
- La documentación y los comentarios del código están en español.
