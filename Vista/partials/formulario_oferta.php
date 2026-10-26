<?php
// Archivo: Vista/partials/formulario_oferta.php
// Formulario compartido por las vistas crear.php y editar.php.
// Variables esperadas desde OfertasControlador:
// $datosIniciales, $actividadInicial, $esEdicion, $urlGuardar, $ofertaId, $consecutivo.

$tituloFormulario = $esEdicion ? 'Editar oferta' : 'Crear oferta';
$textoBoton = $esEdicion ? 'Actualizar oferta' : 'Guardar oferta';
?>

<div class="row">
    <div class="col-xl-10 offset-xl-1">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($tituloFormulario) ?></h2>
                <?php if ($esEdicion): ?>
                    <p class="text-muted mb-0">
                        Consecutivo: <strong><?= htmlspecialchars($consecutivo) ?></strong>
                    </p>
                <?php endif; ?>
            </div>
            <a href="index.php?accion=listar" class="btn btn-outline-secondary">Volver al listado</a>
        </div>

        <div id="app-oferta" v-cloak>
            <!-- apartado de alerta -->
            <div v-if="alerta.mostrar":class="'alert alert-' + alerta.tipo + ' alert-dismissible fade show'" role="alert">
                <strong>{{ alerta.titulo }}</strong> {{ alerta.mensaje }}
                <button type="button" class="btn-close" @click="alerta.mostrar = false" aria-label="Cerrar"></button>
            </div>
            <!---->
            <!-- inicio de formulario -->
            <form id="formOferta" :action="urlGuardar" method="POST" @submit.prevent="enviarFormulario" novalidate>
                <input v-if="esEdicion" type="hidden" name="id" :value="ofertaId">

                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 text-center">Información básica</h5>
                    </div>
                    <div class="card-body">
                        <!-- campo objeto --->
                        <div class="mb-3">
                            <label for="objeto" class="form-label">Objeto <span class="text-danger">*</span></label>
                            <input
                                id="objeto"
                                name="objeto"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errores.objeto }"
                                v-model="form.objeto"
                                maxlength="150"
                                placeholder="Nombre o asunto de la licitación"
                                @input="limpiarError('objeto')"
                            >
                            <div v-if="errores.objeto" class="invalid-feedback">{{ errores.objeto }}</div>
                            <div class="form-text d-flex justify-content-between">
                                <span>Entre 3 y 150 caracteres</span>
                                <span>{{ form.objeto.length }}/150</span>
                            </div>
                        </div>
                        <!-- campo descripcion --->
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                class="form-control"
                                :class="{ 'is-invalid': errores.descripcion }"
                                v-model="form.descripcion"
                                rows="4"
                                maxlength="400"
                                placeholder="Describa el alcance de la licitación"
                                @input="limpiarError('descripcion')"
                            ></textarea>
                            <div v-if="errores.descripcion" class="invalid-feedback">{{ errores.descripcion }}</div>
                            <div class="form-text d-flex justify-content-between">
                                <span>Máximo 400 caracteres</span>
                                <span>{{ form.descripcion.length }}/400</span>
                            </div>
                        </div>
                        <div class="row g-3">
                            <!-- campo moneda --->
                            <div class="col-md-4">
                                <label for="moneda" class="form-label">Moneda <span class="text-danger">*</span></label>
                                <select
                                    id="moneda"
                                    name="moneda"
                                    class="form-select"
                                    :class="{ 'is-invalid': errores.moneda }"
                                    v-model="form.moneda"
                                    @change="limpiarError('moneda')"
                                >
                                    <option value="">Seleccione...</option>
                                    <option value="COP">COP</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                </select>
                                <div v-if="errores.moneda" class="invalid-feedback">{{ errores.moneda }}</div>
                            </div>
                            <!-- campo presupuesto --->
                            <div class="col-md-8">
                                <label for="presupuesto" class="form-label">Presupuesto <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">{{ simboloMoneda }}</span>
                                    <input
                                        id="presupuesto"
                                        name="presupuesto"
                                        type="text"
                                        inputmode="decimal"
                                        class="form-control"
                                        :class="{ 'is-invalid': errores.presupuesto }"
                                        v-model="form.presupuesto"
                                        placeholder="0.00"
                                        @input="formatearPresupuesto"
                                    >
                                    <div v-if="errores.presupuesto" class="invalid-feedback">{{ errores.presupuesto }}</div>
                                </div>
                            </div>
                        </div>
                        <!-- campo actividad --->
                        <div class="mb-3">
                            <br>
                            <div class="mb-0 position-relative" ref="contenedorActividad">
                            <label for="busquedaActividad" class="form-label">Actividad <span class="text-danger">*</span>
                            </label>
                            <input
                                id="busquedaActividad"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errores.actividad }"
                                v-model="busquedaActividad"
                                placeholder="Escriba al menos 2 caracteres para buscar"
                                autocomplete="off"
                                @input="alEscribirActividad"
                                @focus="abrirResultados"
                                @keydown.esc="cerrarResultados"
                            >
                            <input type="hidden" name="actividad" :value="form.actividad ?? ''">

                            <div v-if="errores.actividad" class="invalid-feedback d-block">{{ errores.actividad }}</div>

                            <div
                                v-if="mostrarResultados"
                                class="list-group position-absolute w-100 shadow"
                                style="z-index: 1050; max-height: 300px; overflow-y: auto;"
                            >
                                <div v-if="cargandoActividades" class="list-group-item text-muted">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Buscando actividades...
                                </div>
                                <div v-else-if="errorActividades" class="list-group-item text-danger">
                                    {{ errorActividades }}
                                </div>
                                <template v-else>
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
                                    <div
                                        v-if="actividades.length === 0 && busquedaActividad.trim().length >= 2"
                                        class="list-group-item text-muted"
                                    >
                                        No se encontraron actividades.
                                    </div>
                                </template>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 text-center">Cronograma</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- campo fecha inicio --->
                            <div class="col-md-3">
                                <label for="fecha_inicio" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                                <input
                                    id="fecha_inicio"
                                    name="fecha_inicio"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': errores.fecha_inicio }"
                                    v-model="form.fecha_inicio"
                                    :min="esEdicion ? null : fechaActual"
                                    @change="actualizarCronograma"
                                >
                                <div v-if="errores.fecha_inicio" class="invalid-feedback">{{ errores.fecha_inicio }}</div>
                            </div>
                            <!-- campo hora inicio --->
                            <div class="col-md-3">
                                <label for="hora_inicio" class="form-label">Hora de inicio <span class="text-danger">*</span></label>
                                <input
                                    id="hora_inicio"
                                    name="hora_inicio"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': errores.hora_inicio }"
                                    v-model="form.hora_inicio"
                                    @change="actualizarCronograma"
                                >
                                <div v-if="errores.hora_inicio" class="invalid-feedback">{{ errores.hora_inicio }}</div>
                            </div>
                            <!-- campo fecha fin --->

                            <div class="col-md-3">
                                <label for="fecha_cierre" class="form-label">Fecha de finalización <span class="text-danger">*</span></label>
                                <input
                                    id="fecha_cierre"
                                    name="fecha_cierre"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': errores.fecha_cierre }"
                                    v-model="form.fecha_cierre"
                                    :min="form.fecha_inicio || null"
                                    @change="actualizarCronograma"
                                >
                                <div v-if="errores.fecha_cierre" class="invalid-feedback">{{ errores.fecha_cierre }}</div>
                            </div>
                            <!-- campo hora fin --->

                            <div class="col-md-3">
                                <label for="hora_cierre" class="form-label">Hora de finalización <span class="text-danger">*</span></label>
                                <input
                                    id="hora_cierre"
                                    name="hora_cierre"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': errores.hora_cierre }"
                                    v-model="form.hora_cierre"
                                    :min="minHoraCierre || null"
                                    @change="actualizarCronograma"
                                >
                                <div v-if="errores.hora_cierre" class="invalid-feedback">{{ errores.hora_cierre }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($esEdicion): ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h5 class="mb-0">Documentos</h5>
                            <button type="button" class="btn btn-sm btn-primary" @click="abrirModalDocumento">
                                Agregar documento
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Debe registrar al menos un documento. Los archivos permitidos son
                                <strong>PDF</strong> o <strong>ZIP</strong>
                            </p>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <small class="text-muted">
                                    Tamaño total a subir en esta actualización:
                                    <strong>{{ formatearTamano(totalBytesSubida) || '0 B' }}</strong>
                                </small>
                                <small class="text-muted">Máximo 20 MB en total</small>
                            </div>

                            <div v-if="errores.documentos" class="alert alert-danger py-2 mb-3">
                                {{ errores.documentos }}
                            </div>

                            <div v-if="totalDocumentos === 0" class="alert alert-warning mb-0">
                                Esta oferta todavía no tiene documentos. Use el botón
                                <strong>Agregar documento</strong> para registrar al menos uno.
                            </div>

                            <div v-else class="table-responsive">
                                <table class="table table-sm table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Título</th>
                                            <th>Descripción</th>
                                            <th>Archivo</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th style="width: 140px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="documento in documentosExistentesVista" :key="'existente-' + documento.id">
                                            <td>{{ documento.reemplazo ? documento.reemplazo.titulo : documento.titulo }}</td>
                                            <td>{{ (documento.reemplazo ? documento.reemplazo.descripcion : documento.descripcion) || '—' }}</td>
                                            <td>
                                                <template v-if="documento.reemplazo">
                                                    <span class="d-block">{{ documento.reemplazo.archivo.name }}</span>
                                                    <small class="text-muted">
                                                        Actual:
                                                        <a
                                                            :href="urlDocumento(documento.archivo)"
                                                            :download="nombreArchivo(documento.archivo)"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            {{ nombreArchivo(documento.archivo) }}
                                                        </a>
                                                    </small>
                                                </template>
                                                <a
                                                    v-else
                                                    :href="urlDocumento(documento.archivo)"
                                                    :download="nombreArchivo(documento.archivo)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    {{ nombreArchivo(documento.archivo) }}
                                                </a>
                                            </td>
                                            <td>{{ formatearFecha(documento.creado_en) }}</td>
                                            <td>
                                                <span v-if="documento.reemplazo" class="badge bg-warning text-dark">
                                                    Reemplazo pendiente
                                                </span>
                                                <span v-else class="badge bg-secondary">Guardado</span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        @click="abrirModalReemplazo(documento)"
                                                    >
                                                        Reemplazar
                                                    </button>
                                                    <button
                                                        v-if="documento.reemplazo"
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        @click="cancelarReemplazo(documento.id)"
                                                    >
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-for="(documento, indice) in documentosNuevos" :key="'nuevo-' + indice">
                                            <td>{{ documento.titulo }}</td>
                                            <td>{{ documento.descripcion || '—' }}</td>
                                            <td>{{ documento.archivo.name }}</td>
                                            <td class="text-muted">Al guardar</td>
                                            <td><span class="badge bg-success">Nuevo</span></td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    @click="quitarDocumentoNuevo(indice)"
                                                >
                                                    Quitar
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-end gap-2 mb-5">
                    <a href="index.php?accion=listar" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary" :disabled="enviando || guardadoExitoso">
                        <span v-if="enviando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ enviando ? 'Guardando...' : (guardadoExitoso ? 'Guardado' : textoBoton) }}
                    </button>
                </div>
            </form>

            <?php if ($esEdicion): ?>
                <div
                    v-if="modalDocumento"
                    class="modal fade show d-block"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="tituloModalDocumento"
                    style="background: rgba(0, 0, 0, 0.5);"
                >
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form @submit.prevent="agregarDocumento" novalidate>
                                <div class="modal-header">
                                    <h5 class="modal-title" id="tituloModalDocumento">
                                        {{ modoDocumento === 'reemplazo' ? 'Reemplazar documento' : 'Agregar documento' }}
                                    </h5>
                                    <button type="button" class="btn-close" aria-label="Cerrar" @click="cerrarModalDocumento"></button>
                                </div>
                                <div class="modal-body">
                                    <div v-if="erroresDocumento.general" class="alert alert-danger py-2">
                                        {{ erroresDocumento.general }}
                                    </div>

                                    <div v-if="documentoEnReemplazo" class="alert alert-light border py-2">
                                        <small>
                                            Archivo actual:
                                            <a
                                                :href="urlDocumento(documentoEnReemplazo.archivo)"
                                                :download="nombreArchivo(documentoEnReemplazo.archivo)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {{ nombreArchivo(documentoEnReemplazo.archivo) }}
                                            </a>
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="documentoTitulo" class="form-label">
                                            Título <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="documentoTitulo"
                                            type="text"
                                            class="form-control"
                                            :class="{ 'is-invalid': erroresDocumento.titulo }"
                                            v-model="nuevoDocumento.titulo"
                                            maxlength="100"
                                            placeholder="Nombre del documento"
                                            @input="limpiarErrorDocumento('titulo')"
                                        >
                                        <div v-if="erroresDocumento.titulo" class="invalid-feedback">
                                            {{ erroresDocumento.titulo }}
                                        </div>
                                        <div class="form-text d-flex justify-content-between">
                                            <span>Máximo 100 caracteres</span>
                                            <span>{{ nuevoDocumento.titulo.length }}/100</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="documentoDescripcion" class="form-label">Descripción</label>
                                        <textarea
                                            id="documentoDescripcion"
                                            class="form-control"
                                            :class="{ 'is-invalid': erroresDocumento.descripcion }"
                                            v-model="nuevoDocumento.descripcion"
                                            rows="3"
                                            maxlength="200"
                                            placeholder="Descripción opcional del documento"
                                            @input="limpiarErrorDocumento('descripcion')"
                                        ></textarea>
                                        <div v-if="erroresDocumento.descripcion" class="invalid-feedback">
                                            {{ erroresDocumento.descripcion }}
                                        </div>
                                        <div class="form-text d-flex justify-content-between">
                                            <span>Máximo 200 caracteres</span>
                                            <span>{{ nuevoDocumento.descripcion.length }}/200</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="documentoArchivo" class="form-label">
                                            Archivo <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="documentoArchivo"
                                            ref="inputArchivo"
                                            type="file"
                                            class="form-control"
                                            :class="{ 'is-invalid': erroresDocumento.archivo }"
                                            accept=".pdf,.zip,application/pdf,application/zip"
                                            @change="seleccionarArchivo"
                                        >
                                        <div v-if="erroresDocumento.archivo" class="invalid-feedback d-block">
                                            {{ erroresDocumento.archivo }}
                                        </div>
                                        <div v-if="nuevoDocumento.archivo" class="form-text">
                                            {{ nuevoDocumento.archivo.name }}
                                            <span v-if="nuevoDocumento.archivo.size">
                                                · {{ formatearTamano(nuevoDocumento.archivo.size) }}
                                            </span>
                                        </div>
                                        <div v-else class="form-text">
                                            {{ modoDocumento === 'reemplazo'
                                                ? 'Seleccione el nuevo archivo .pdf o .zip que reemplazará al actual.'
                                                : 'Solo se permiten archivos .pdf o .zip.' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" @click="cerrarModalDocumento">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        {{ modoDocumento === 'reemplazo' ? 'Guardar reemplazo' : 'Agregar documento' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    [v-cloak] {
        display: none;
    }
</style>

<?php
// Datos preparados por el servidor y para que JavaScript pueda tratar mediante un bloque JSON.
$configuracionFormulario = array(
    'datosIniciales' => $datosIniciales,
    'actividadInicial' => $actividadInicial,
    'documentosIniciales' => $documentosIniciales ?? array(),
    'esEdicion' => (bool) $esEdicion,
    'urlGuardar' => $urlGuardar,
    'ofertaId' => (int) $ofertaId,
    'textoBoton' => $textoBoton,
);
?>
<script type="application/json" id="datos-formulario-oferta">
    <?= json_encode($configuracionFormulario, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
</script>
<script src="Public/js/formulario_oferta.js"></script>
