<?php
// Archivo: Vista/validador_cargas.php
// Propósito: Vista para el "Validador de Cargas"
// El archivo CSV ya no se adjunta: se toma automáticamente de la raíz del proyecto
// (csv_suplos_temp.csv). Si la tabla actividades ya tiene registros, la vista muestra
// que la carga se realizó previamente y no vuelve a ejecutarla. Si está vacía,
// inicia el proceso automáticamente y muestra el progreso real de la inserción.
// Usa Vue.js 3 (cargado en el encabezado) y Bootstrap 5.3 para estilos.

// Valores por defecto por si la vista se incluye sin pasar por el controlador
$cargaPrevia = isset($cargaPrevia) ? $cargaPrevia : false;
$totalActividades = isset($totalActividades) ? $totalActividades : 0;
$errorValidacion = isset($errorValidacion) ? $errorValidacion : null;

include __DIR__ . '/partials/encabezado.php';
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Validador de Cargas</h2>
                <a href="index.php?accion=listar" class="btn btn-secondary">Volver al Listado</a>
            </div>

            <?php if ($errorValidacion): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Error:</strong> <?= htmlspecialchars($errorValidacion) ?>
                </div>
            <?php endif; ?>

            <?php if ($cargaPrevia): ?>
                <!-- La tabla actividades ya tiene registros: no se vuelve a cargar -->
                <div class="alert alert-info" role="alert">
                    <h5 class="alert-heading">La carga de datos ya se realizó previamente</h5>
                    <p class="mb-0">
                        La tabla <strong>actividades</strong> ya contiene
                        <strong><?= number_format($totalActividades, 0, ',', '.') ?></strong>
                        registros, por lo que no se volverá a ejecutar la carga del archivo
                        <code>csv_suplos_temp.csv</code>.
                    </p>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Carga completada</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            Los datos del archivo ya fueron insertados en la base de datos.
                            Para volver a cargarlos, primero debe vaciar la tabla <strong>actividades</strong>.
                        </p>
                        <a href="index.php?accion=listar" class="btn btn-primary">Ir al Listado</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- No hay registros: se inicia el proceso automáticamente -->
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>¡Importante!</strong> La carga se está ejecutando automáticamente desde el archivo
                    <code>csv_suplos_temp.csv</code> ubicado en la raíz del proyecto.
                    No recargue ni cierre la pantalla mientras se procesan los registros.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div id="appValidadorCargas">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Proceso de carga de registros</h5>
                        </div>
                        <div class="card-body">
                            <div v-if="!procesoCompletado && !mensajeError" class="mb-3">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                <span v-if="totalRegistros === 0">Leyendo el archivo y preparando los registros...</span>
                                <span v-else>Insertando registros en la tabla actividades...</span>
                            </div>

                            <div class="mb-2">
                                <strong>Total de registros: </strong>
                                {{ totalRegistros === '?' ? 'Calculando...' : totalRegistros }}
                            </div>

                            <div class="progress" style="height: 30px;">
                                <div
                                    class="progress-bar progress-bar-striped bg-success"
                                    :class="{ 'progress-bar-animated': !procesoCompletado }"
                                    role="progressbar"
                                    :style="{ width: porcentajeProgreso + '%' }"
                                    :aria-valuenow="porcentajeProgreso"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <strong>{{ porcentajeProgreso }}%</strong>
                                </div>
                            </div>

                            <div class="mt-2 text-center">
                                <small>Registros procesados: {{ registrosProcesados }} / {{ totalRegistros }}</small>
                            </div>

                            <div v-if="mensajeError" class="alert alert-danger mt-3 mb-0">
                                <strong>Error:</strong> {{ mensajeError }}
                            </div>

                            <div v-if="mensajeError" class="mt-3">
                                <button type="button" class="btn btn-warning" @click="iniciarCarga">
                                    Reintentar carga
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="procesoCompletado" class="card shadow-sm">
                        <div class="card-header" :class="resultadoExitoso ? 'bg-success text-white' : 'bg-danger text-white'">
                            <h5 class="mb-0">Resultado</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ mensajeResultado }}</p>
                            <div v-if="resultadoExitoso && registrosInsertados > 0" class="mt-2">
                                <strong>Registros insertados en la tabla actividades:</strong> {{ registrosInsertados }}
                            </div>
                            <div v-if="yaCargado" class="mt-2">
                                <strong>Registros existentes en la tabla actividades:</strong> {{ totalActividades }}
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    const { createApp } = Vue;

                    createApp({
                        data() {
                            return {
                                procesando: false,
                                procesoCompletado: false,
                                resultadoExitoso: false,
                                yaCargado: false,
                                mensajeResultado: '',
                                mensajeError: '',
                                totalRegistros: '?',
                                registrosProcesados: 0,
                                registrosInsertados: 0,
                                porcentajeProgreso: 0,
                                totalActividades: 0
                            }
                        },
                        mounted() {
                            // Inicia la carga automáticamente al abrir la vista
                            this.iniciarCarga();
                        },
                        methods: {
                            async iniciarCarga() {
                                this.procesando = true;
                                this.procesoCompletado = false;
                                this.resultadoExitoso = false;
                                this.yaCargado = false;
                                this.mensajeResultado = '';
                                this.mensajeError = '';
                                this.totalRegistros = '?';
                                this.registrosProcesados = 0;
                                this.registrosInsertados = 0;
                                this.porcentajeProgreso = 0;
                                this.totalActividades = 0;

                                try {
                                    const response = await fetch('index.php?accion=procesar_carga', {
                                        method: 'POST',
                                        credentials: 'include',
                                        cache: 'no-store'
                                    });

                                    if (!response.ok) {
                                        throw new Error('El servidor respondió con estado ' + response.status + '.');
                                    }

                                    if (!response.body) {
                                        throw new Error('El navegador no soporta la lectura del progreso.');
                                    }

                                    const reader = response.body.getReader();
                                    const decoder = new TextDecoder();
                                    let buffer = '';
                                    let recibioFin = false;

                                    while (true) {
                                        const { done, value } = await reader.read();
                                        if (done) break;

                                        buffer += decoder.decode(value, { stream: true });

                                        let salto;
                                        while ((salto = buffer.indexOf('\n')) >= 0) {
                                            const linea = buffer.slice(0, salto).trim();
                                            buffer = buffer.slice(salto + 1);
                                            if (!linea) continue;

                                            let evento;
                                            try {
                                                evento = JSON.parse(linea);
                                            } catch (e) {
                                                // Ignora líneas que no sean JSON válido
                                                continue;
                                            }

                                            if (evento.tipo === 'inicio') {
                                                this.totalRegistros = evento.total_registros || 0;
                                            } else if (evento.tipo === 'progreso') {
                                                this.totalRegistros = evento.total_registros || 0;
                                                this.registrosProcesados = evento.registros_insertados || 0;
                                                this.porcentajeProgreso = evento.porcentaje || 0;
                                            } else if (evento.tipo === 'fin') {
                                                recibioFin = true;
                                                this.finalizar(evento);
                                            }
                                        }
                                    }

                                    if (!recibioFin) {
                                        throw new Error('El servidor no devolvió el resultado final del proceso.');
                                    }
                                } catch (error) {
                                    this.mensajeError = error.message;
                                    this.procesando = false;
                                }
                            },

                            finalizar(data) {
                                this.procesando = false;
                                this.procesoCompletado = true;
                                this.resultadoExitoso = !!data.success;
                                this.mensajeResultado = data.mensaje || '';
                                this.yaCargado = !!data.ya_cargado;
                                this.totalActividades = data.total_actividades || 0;

                                if (data.success) {
                                    this.totalRegistros = data.total_registros || 0;
                                    this.registrosInsertados = data.registros_insertados || 0;
                                    this.registrosProcesados = this.registrosInsertados;
                                    this.porcentajeProgreso = 100;
                                }
                            }
                        }
                    }).mount("#appValidadorCargas");
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . "/partials/pie.php"; ?>
