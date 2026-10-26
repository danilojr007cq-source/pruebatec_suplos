# Documentación del módulo de actividades

**Proyecto:** `pruebatec_suplos`
**Última actualización:** 25/10/2026
**Tecnologías:** PHP nativo, Eloquent ORM, Vue.js 3 y Bootstrap 5.3

Este documento describe los cambios realizados en el módulo de actividades:

1. Carga automática de actividades desde el archivo CSV de la raíz.
2. Autocompletado de actividades por AJAX para el formulario de creación.

---

## 1. Carga de actividades desde CSV (validador de cargas)

### Objetivo

Cargar la tabla `actividades` desde el archivo `csv_suplos_temp.csv` ubicado en la raíz del proyecto, sin pedir el archivo por formulario y sin repetir la carga si la tabla ya contiene registros.

### Archivos involucrados

| Archivo | Responsabilidad |
|---|---|
| `Controlador/ValidadorCargasControlador.php` | Valida el estado de la tabla, lee el CSV, inserta por bloques y reporta el progreso. |
| `Vista/validador_cargas.php` | Muestra el proceso de carga o el aviso de carga previa. |
| `index.php` | Enruta las acciones `validador_cargas` y `procesar_carga`. |
| `csv_suplos_temp.csv` | Archivo de datos separado por `;` (raíz del proyecto). |
| `Modelo/Actividad.php` | Modelo Eloquent de la tabla `actividades`. |

### Comportamiento

1. Al abrir `index.php?accion=validador_cargas`, el método `mostrar()` ejecuta `Actividad::count()`.
2. Si el resultado es mayor que `0`:
   - La vista muestra el mensaje **“La carga de datos ya se realizó previamente”**.
   - Se indica el total de registros existentes.
   - No se ejecuta ninguna inserción.
3. Si la tabla está vacía:
   - La vista inicia la carga automáticamente.
   - Se muestra una barra de progreso real y el número de registros procesados.
4. El endpoint `index.php?accion=procesar_carga` vuelve a validar el conteo antes de insertar, como protección ante doble ejecución o múltiples pestañas.
5. El CSV se lee desde:

```php
private $rutaCsv = __DIR__ . '/../csv_suplos_temp.csv';
```

6. Se omite la primera línea si corresponde al encabezado.
7. Los registros se insertan por bloques de 1000 con `Actividad::insertarMasivo()`.

### Formato de respuesta del progreso

El endpoint `procesar_carga` responde en **NDJSON** (`application/x-ndjson`): una línea JSON por evento. La vista lo consume con `fetch` y `response.body.getReader()`.

Eventos emitidos:

```json
{"tipo":"inicio","total_registros":49022}
{"tipo":"progreso","total_registros":49022,"registros_insertados":1000,"porcentaje":2}
{"tipo":"fin","success":true,"mensaje":"Archivo procesado correctamente...","total_registros":49022,"registros_insertados":49022,"total_actividades":49022}
```

Si ya había registros:

```json
{"tipo":"fin","success":true,"ya_cargado":true,"mensaje":"La carga de los datos ya se había realizado previamente...","total_registros":0,"registros_insertados":0,"total_actividades":49022}
```

### Cómo volver a cargar los datos

1. Vaciar la tabla `actividades`.
2. Abrir nuevamente `index.php?accion=validador_cargas`.

---

## 2. Autocompletado de actividades por AJAX

### Problema que resuelve

La tabla `actividades` contiene **49.022 registros**. Cargar todos los registros en un `<select>` generaba:

- 49.022 nodos `<option>` en el DOM.
- Un JSON muy grande.
- Lentitud al abrir y renderizar la vista.
- Dificultad para encontrar una actividad.

Por eso se reemplazó el `<select>` por un **input de texto con autocompletado**.

### Flujo general

1. El usuario escribe al menos **2 caracteres** en el input de actividad.
2. Vue aplica un **debounce de 300 ms**.
3. Se ejecuta una petición `fetch` a:

```text
index.php?accion=obtener_actividades&q=TEXTO
```

4. El controlador consulta con Eloquent usando `LIKE` y devuelve como máximo **15 resultados**.
5. Vue muestra los resultados en una lista flotante de Bootstrap.
6. Al hacer clic en una opción:
   - Se guarda el `id` en `form.actividad`.
   - El nombre del producto se muestra en el input.
   - La lista se oculta.

---

### 2.1 Backend

#### Método del controlador

`Controlador/ValidadorCargasControlador.php` → `listarActividades()`

Parámetros:

| Parámetro | Tipo | Descripción |
|---|---|---|
| `q` | string | Texto a buscar dentro de `producto`. |

Lógica:

1. Si `q` está vacío, no consulta la base de datos y devuelve una lista vacía.
2. Escapa `%` y `_` para que se busquen como texto literal y no como comodines de `LIKE`:

```php
$termino = str_replace(
    array('\\', '%', '_'),
    array('\\\\', '\\%', '\\_'),
    $q
);
```

3. Consulta con Eloquent:

```php
$actividades = Actividad::query()
    ->select('id', 'producto', 'codigo_producto', 'clase')
    ->where('producto', 'LIKE', '%' . $termino . '%')
    ->orderBy('producto', 'asc')
    ->limit(15)
    ->get();
```

4. Devuelve JSON con `JSON_UNESCAPED_UNICODE`.

#### Ruta

En `index.php`:

```php
case 'obtener_actividades':
    $controladorValidador->listarActividades();
    break;
```

#### Respuesta exitosa

```json
{
  "success": true,
  "total": 2,
  "actividades": [
    { "id": 262033, "producto": "Válvulas de bola", "codigo_producto": 40141607, "clase": "Válvulas" },
    { "id": 262028, "producto": "Válvulas de aguja", "codigo_producto": 40141602, "clase": "Válvulas" }
  ]
}
```

- `success`: indica si la consulta fue correcta.
- `total`: cantidad de resultados devueltos (máximo 15).
- `actividades`: arreglo con `id`, `producto`, `codigo_producto` y `clase`.
- `id` se usará como valor del formulario.
- `producto`, `codigo_producto` y `clase` se muestran al usuario en la lista flotante.

#### Respuesta de error

```json
{
  "success": false,
  "mensaje": "Error al consultar las actividades: ...",
  "actividades": []
}
```

En caso de error se responde con código HTTP `500`.

#### Seguridad

- La consulta usa Eloquent, por lo que los valores van parametrizados (previene inyección SQL).
- Los comodines `%` y `_` se escapan.
- No se devuelven datos innecesarios: `id`, `producto`, `codigo_producto` y `clase`.
- El límite de 15 evita respuestas grandes.

---

### 2.2 Frontend

#### Archivo

`Vista/partials/formulario_oferta.php` → bloque **Actividad (autocompletado con búsqueda AJAX)**. Este parcial es compartido por las vistas de crear y editar oferta.

#### Estructura HTML

```html
<input
    type="text"
    class="form-control"
    v-model="busquedaActividad"
    @input="alEscribirActividad"
    @focus="abrirResultados"
    @keydown.esc="cerrarResultados"
    placeholder="Escriba al menos 2 caracteres para buscar"
    autocomplete="off"
>

<!-- ID de la actividad seleccionada, se envía con el formulario -->
<input type="hidden" name="actividad" :value="form.actividad ?? ''">
```

La lista flotante usa utilidades de Bootstrap 5.3:

```html
<div
    v-if="mostrarResultados"
    class="list-group position-absolute w-100 shadow"
    style="z-index: 1050; max-height: 300px; overflow-y: auto;"
>
```

Cada resultado:

```html
<button
    v-for="actividad in actividades"
    :key="actividad.id"
    type="button"
    class="list-group-item list-group-item-action"
    @click="seleccionarActividad(actividad)"
>
    <div class="fw-semibold">{{ actividad.producto }}</div>
    <small class="text-muted">
        Código {{ actividad.codigo_producto }}
        <span v-if="actividad.clase"> · {{ actividad.clase }}</span>
    </small>
</button>
```

#### Estado reactivo

| Variable | Tipo | Función |
|---|---|---|
| `form.actividad` | number/null | ID de la actividad seleccionada. Es lo que se envía en el formulario. |
| `busquedaActividad` | `ref('')` | Texto visible en el input. |
| `actividades` | `ref([])` | Resultados devueltos por el servidor. |
| `mostrarResultados` | `ref(false)` | Controla la visibilidad de la lista flotante. |
| `cargandoActividades` | `ref(false)` | Muestra el spinner de búsqueda. |
| `errorActividades` | `ref('')` | Mensaje de error de la petición. |
| `temporizadorBusqueda` | variable local | Guarda el timer del debounce de 300 ms. |
| `idPeticion` | variable local | Evita aplicar respuestas de búsquedas antiguas. |

#### Funciones principales

| Función | Descripción |
|---|---|
| `buscarActividades(termino)` | Ejecuta el `fetch` al endpoint y actualiza los resultados. |
| `alEscribirActividad()` | Limpia la selección previa, aplica el debounce y controla el mínimo de caracteres. |
| `seleccionarActividad(actividad)` | Guarda el `id`, coloca el nombre en el input y oculta la lista. |
| `abrirResultados()` | Vuelve a mostrar la lista al recuperar el foco si hay resultados. |
| `cerrarResultados()` | Oculta la lista (tecla `Escape`). |
| `manejarClicFuera(evento)` | Oculta la lista cuando el clic ocurre fuera del contenedor del autocompletado. |

#### Debounce

```js
temporizadorBusqueda = setTimeout(() => {
    buscarActividades(termino);
}, 300);
```

Si el usuario sigue escribiendo, el timer anterior se cancela con `clearTimeout`. Así solo se consulta al servidor cuando el usuario deja de escribir por 300 ms.

#### Mínimo de caracteres

Se consulta a partir de **2 caracteres**. Con menos, se limpian los resultados y no se hace petición al servidor.

#### Control de respuestas fuera de orden

Cada petición incrementa `idPeticion`. Cuando una respuesta llega, solo se aplica si corresponde a la última petición:

```js
const peticionActual = ++idPeticion;
// ...
if (peticionActual !== idPeticion) {
    return;
}
```

Esto evita que una respuesta lenta de una búsqueda anterior sobrescriba los resultados de la búsqueda actual.

#### Selección de una actividad

```js
const seleccionarActividad = (actividad) => {
    form.actividad = actividad.id;
    busquedaActividad.value = actividad.producto;
    mostrarResultados.value = false;
    actividades.value = [];
    errorActividades.value = '';
    cargandoActividades.value = false;
    idPeticion++;
    if (temporizadorBusqueda) {
        clearTimeout(temporizadorBusqueda);
    }
};
```

Resultado:

- El input muestra el nombre del producto.
- `form.actividad` contiene el `id`.
- El `<input type="hidden" name="actividad">` envía ese `id` con el formulario.
- La lista desaparece.

#### Cierre de la lista

- Al seleccionar una opción.
- Con la tecla `Escape`.
- Al hacer clic fuera del contenedor del autocompletado.
- La lista se encuentra dentro del mismo contenedor que el input, por lo que el clic en una opción se registra antes de cerrar.

#### Limpieza

`onMounted` registra el listener de clic fuera y `onBeforeUnmount` lo elimina y cancela el timer pendiente cuando se destruye el componente.

---

## 3. Archivos modificados

| Archivo | Cambio |
|---|---|
| `Controlador/ValidadorCargasControlador.php` | Carga del CSV desde la raíz, validación de carga previa, progreso NDJSON y endpoint de búsqueda de actividades con `LIKE` y límite. |
| `Vista/validador_cargas.php` | Eliminación del formulario de subida; aviso de carga previa y barra de progreso automática. |
| `Vista/partials/formulario_oferta.php` | Autocompletado AJAX con debounce, lista flotante y campo oculto para el ID; formulario compartido por crear y editar. |
| `Modelo/Actividad.php` | La consulta del autocompletado devuelve también `codigo_producto` y `clase`. |
| `index.php` | Rutas de ofertas y ruta `obtener_actividades`. |
| `DOCUMENTACION_ACTIVIDADES.md` | Este documento. |
| `DOCUMENTACION_OFERTAS.md` | Documentación del módulo de ofertas. |
| `README_CRUD.md` | Enlaces a la documentación. |

---

## 4. Cómo probar manualmente

### Requisitos

- Servidor con PHP y MySQL.
- Base de datos `crud_pruebatec` con la tabla `actividades`.
- Archivo `csv_suplos_temp.csv` en la raíz del proyecto.

### Iniciar el servidor

```bash
php -S localhost:8000
```

### Probar la carga de actividades

1. Abrir `http://localhost:8000/index.php?accion=validador_cargas`.
2. Si la tabla está vacía, la carga inicia automáticamente.
3. Verificar el progreso y el resultado final.
4. Abrir nuevamente la URL: debe aparecer el aviso de carga previa.

### Probar el autocompletado

1. Abrir `http://localhost:8000/index.php?accion=crear`.
2. Escribir `válvula` en el campo **Actividad**.
3. Esperar 300 ms y verificar la lista flotante.
4. Hacer clic en una opción.
5. Comprobar que:
   - El input muestra el nombre del producto.
   - La lista se oculta.
   - En el HTML del formulario, el campo oculto `actividad` tiene el `id` seleccionado.

### Probar el endpoint directamente

```text
http://localhost:8000/index.php?accion=obtener_actividades&q=gato
```

Respuesta esperada: JSON con máximo 15 actividades.

---

## 5. Pruebas realizadas

| Prueba | Resultado |
|---|---|
| `php -l` en controlador y vistas | Sin errores de sintaxis. |
| Render de `Vista/crear.php` | Sin warnings. |
| Búsqueda `uva` | 15 resultados, ~130 ms. |
| Búsqueda `válvulas de bola` | 1 resultado, ~124 ms. |
| Búsqueda `valvula` (sin tilde) | 15 resultados, ~139 ms. |
| Búsqueda `VÁLVULA` (mayúsculas) | 15 resultados, ~146 ms. |
| Búsqueda `%` | 0 resultados (el comodín se trata como texto). |
| Búsqueda `_` | 0 resultados (el comodín se trata como texto). |
| Carga del CSV | 49.022 registros válidos insertados. |

Las búsquedas funcionan con mayúsculas, minúsculas y acentos gracias a la colación `utf8mb4_unicode_ci` de MySQL.

---

## 6. Limitaciones y recomendaciones

### Búsqueda con `LIKE '%texto%'`

El comodín inicial impide usar un índice normal. Con 49.022 registros el tiempo de respuesta es aceptable (~130 ms), pero si la tabla crece mucho se recomienda:

- Agregar un índice `FULLTEXT` en `producto` y usar `MATCH ... AGAINST`.
- O usar búsqueda por prefijo `LIKE 'texto%'`, que sí puede aprovechar un índice.

### Resultados limitados

El autocompletado devuelve máximo 15 resultados. Si el término es muy genérico, el usuario deberá escribir algo más específico. Esto es intencional para no saturar la vista.

### Envío del formulario

El campo oculto `name="actividad"` envía el ID seleccionado al formulario de oferta. La creación y la edición se procesan por AJAX en `OfertasControlador::guardarAjax()` y `OfertasControlador::actualizarAjax()`. El detalle de ese flujo está en [DOCUMENTACION_OFERTAS.md](DOCUMENTACION_OFERTAS.md).

### Formulario de oferta

El formulario compartido `Vista/partials/formulario_oferta.php` ya no contiene los bloques duplicados de moneda/presupuesto ni campos de producto de la práctica anterior. Los campos corresponden a la tabla `ofertas`.

---

## 7. Resumen rápido

- El CSV ya no se sube: se toma de `csv_suplos_temp.csv` en la raíz.
- Si `actividades` tiene registros, no se vuelve a cargar.
- Si está vacía, la carga inicia automáticamente con progreso real.
- El campo `actividad` ya no carga 49.022 opciones: busca por AJAX con debounce de 300 ms.
- El backend filtra con `LIKE` y devuelve máximo 15 resultados con `id`, `producto`, `codigo_producto` y `clase`.
- Al seleccionar una opción se guarda el `id` en el formulario y se muestra el nombre en el input.
- El formulario de oferta se comparte entre crear y editar y se envía por AJAX; ver [DOCUMENTACION_OFERTAS.md](DOCUMENTACION_OFERTAS.md).
