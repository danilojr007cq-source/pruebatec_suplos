// Archivo: Public/js/formulario_oferta.js
// Lógica Vue 3 del formulario compartido por las vistas crear.php y editar.php
// Los datos que prepara PHP llegan mediante el bloque JSON: <script type="application/json" id="datos-formulario-oferta">.

(function () {
    'use strict';

    const contenedorDatos = document.getElementById('datos-formulario-oferta');

    if (!contenedorDatos) {
        console.error('No se encontró la configuración inicial del formulario de oferta.');
        return;
    }

    const configuracion = JSON.parse(contenedorDatos.textContent);

    // Datos preparados por PHP y enviados de forma segura a JavaScript
    const datosIniciales = configuracion.datosIniciales || {};
    const actividadInicial = configuracion.actividadInicial || null;
    const esEdicion = Boolean(configuracion.esEdicion);
    const urlGuardar = configuracion.urlGuardar || '';
    const ofertaId = Number(configuracion.ofertaId) || 0;
    const textoBoton = configuracion.textoBoton || '';
    const documentosIniciales = Array.isArray(configuracion.documentosIniciales)
        ? configuracion.documentosIniciales
        : [];

    // Límite total de todos los archivos enviados en una actualización
    const LIMITE_TOTAL_ARCHIVOS = 20 * 1024 * 1024;

    const { createApp, reactive, ref, computed, nextTick, onMounted, onBeforeUnmount } = Vue;

    createApp({
        setup() {
            const form = reactive({
                objeto: datosIniciales.objeto || '',
                descripcion: datosIniciales.descripcion || '',
                moneda: datosIniciales.moneda || 'COP',
                presupuesto: datosIniciales.presupuesto || '',
                actividad: datosIniciales.actividad ? Number(datosIniciales.actividad) : null,
                fecha_inicio: datosIniciales.fecha_inicio || '',
                hora_inicio: datosIniciales.hora_inicio || '',
                fecha_cierre: datosIniciales.fecha_cierre || '',
                hora_cierre: datosIniciales.hora_cierre || ''
            });

            const errores = ref({});
            const alerta = reactive({ mostrar: false, tipo: 'success', titulo: '', mensaje: '' });
            const enviando = ref(false);
            const guardadoExitoso = ref(false);

            // Documentos de la oferta (solo edición)
            const documentosExistentes = ref(documentosIniciales);
            const documentosNuevos = ref([]);
            const documentosReemplazos = ref([]);
            const modalDocumento = ref(false);
            const modoDocumento = ref('nuevo');
            const documentoReemplazoId = ref(null);
            const erroresDocumento = ref({});
            const inputArchivo = ref(null);
            const nuevoDocumento = reactive({
                titulo: '',
                descripcion: '',
                archivo: null
            });

            const totalDocumentos = computed(() => {
                return documentosExistentes.value.length + documentosNuevos.value.length;
            });

            const totalBytesSubida = computed(() => {
                const documentos = [...documentosNuevos.value, ...documentosReemplazos.value];

                return documentos.reduce((total, documento) => {
                    return total + (documento.archivo && documento.archivo.size ? documento.archivo.size : 0);
                }, 0);
            });

            const documentosExistentesVista = computed(() => {
                return documentosExistentes.value.map((documento) => {
                    const reemplazo = documentosReemplazos.value.find((item) => {
                        return Number(item.id) === Number(documento.id);
                    });

                    return {
                        ...documento,
                        reemplazo: reemplazo || null
                    };
                });
            });

            const documentoEnReemplazo = computed(() => {
                if (modoDocumento.value !== 'reemplazo' || !documentoReemplazoId.value) {
                    return null;
                }

                return documentosExistentes.value.find((documento) => {
                    return Number(documento.id) === Number(documentoReemplazoId.value);
                }) || null;
            });

            // Autocompletado de actividades
            const busquedaActividad = ref(
                actividadInicial && actividadInicial.producto ? String(actividadInicial.producto) : ''
            );
            const actividades = ref([]);
            const mostrarResultados = ref(false);
            const cargandoActividades = ref(false);
            const errorActividades = ref('');
            const contenedorActividad = ref(null);
            let temporizadorBusqueda = null;
            let idPeticion = 0;

            // Fecha local en formato YYYY-MM-DD para el atributo min
            const fechaActual = (() => {
                const hoy = new Date();
                const mes = String(hoy.getMonth() + 1).padStart(2, '0');
                const dia = String(hoy.getDate()).padStart(2, '0');
                return `${hoy.getFullYear()}-${mes}-${dia}`;
            })();

            const simboloMoneda = computed(() => {
                const simbolos = { COP: '$', USD: 'US$', EUR: '€' };
                return simbolos[form.moneda] || '$';
            });

            const minHoraCierre = computed(() => {
                return (form.fecha_inicio && form.fecha_cierre === form.fecha_inicio)
                    ? form.hora_inicio
                    : '';
            });

            const limpiarError = (campo) => {
                if (errores.value[campo]) {
                    const copia = { ...errores.value };
                    delete copia[campo];
                    errores.value = copia;
                }
            };

            const mostrarAlerta = (tipo, titulo, mensaje) => {
                alerta.tipo = tipo;
                alerta.titulo = titulo;
                alerta.mensaje = mensaje;
                alerta.mostrar = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            // Utilidades y validación de documentos
            const limpiarErrorDocumento = (campo) => {
                if (erroresDocumento.value[campo]) {
                    const copia = { ...erroresDocumento.value };
                    delete copia[campo];
                    erroresDocumento.value = copia;
                }
            };

            const validarDocumento = (documento) => {
                const erroresValidacion = {};
                const titulo = String(documento.titulo || '').trim();
                const descripcion = String(documento.descripcion || '').trim();
                const archivo = documento.archivo;

                if (!titulo) {
                    erroresValidacion.titulo = 'El título es obligatorio.';
                } else if (titulo.length > 100) {
                    erroresValidacion.titulo = 'El título no puede superar 100 caracteres.';
                }

                if (descripcion.length > 200) {
                    erroresValidacion.descripcion = 'La descripción no puede superar 200 caracteres.';
                }

                if (!archivo || !archivo.name) {
                    erroresValidacion.archivo = 'Seleccione un archivo PDF o ZIP.';
                } else if (!/\.(pdf|zip)$/i.test(archivo.name)) {
                    erroresValidacion.archivo = 'La extensión del archivo debe ser .pdf o .zip.';
                }

                return erroresValidacion;
            };

            const abrirModalDocumento = () => {
                modoDocumento.value = 'nuevo';
                documentoReemplazoId.value = null;
                Object.assign(nuevoDocumento, { titulo: '', descripcion: '', archivo: null });
                erroresDocumento.value = {};
                modalDocumento.value = true;
                nextTick(() => {
                    if (inputArchivo.value) {
                        inputArchivo.value.value = '';
                    }
                });
            };

            const abrirModalReemplazo = (documento) => {
                const datos = documento.reemplazo || documento;
                modoDocumento.value = 'reemplazo';
                documentoReemplazoId.value = Number(documento.id);
                Object.assign(nuevoDocumento, {
                    titulo: datos.titulo || '',
                    descripcion: datos.descripcion || '',
                    archivo: null
                });
                erroresDocumento.value = {};
                modalDocumento.value = true;
                nextTick(() => {
                    if (inputArchivo.value) {
                        inputArchivo.value.value = '';
                    }
                });
            };

            const cerrarModalDocumento = () => {
                modalDocumento.value = false;
                erroresDocumento.value = {};
                modoDocumento.value = 'nuevo';
                documentoReemplazoId.value = null;
            };

            const seleccionarArchivo = (evento) => {
                const archivo = evento.target.files && evento.target.files[0]
                    ? evento.target.files[0]
                    : null;

                nuevoDocumento.archivo = archivo;
                limpiarErrorDocumento('archivo');

                if (archivo && !/\.(pdf|zip)$/i.test(archivo.name)) {
                    erroresDocumento.value = {
                        ...erroresDocumento.value,
                        archivo: 'La extensión del archivo debe ser .pdf o .zip.'
                    };
                }
            };

            const calcularTamanoProyectado = (archivo, idReemplazo = null) => {
                let total = totalBytesSubida.value;

                if (idReemplazo !== null) {
                    const reemplazo = documentosReemplazos.value.find((item) => {
                        return Number(item.id) === Number(idReemplazo);
                    });

                    if (reemplazo && reemplazo.archivo && reemplazo.archivo.size) {
                        total -= reemplazo.archivo.size;
                    }
                }

                return total + (archivo && archivo.size ? archivo.size : 0);
            };

            const agregarDocumento = () => {
                const erroresValidacion = validarDocumento(nuevoDocumento);

                if (Object.keys(erroresValidacion).length > 0) {
                    erroresDocumento.value = erroresValidacion;
                    return;
                }

                const esReemplazo = modoDocumento.value === 'reemplazo' && documentoReemplazoId.value !== null;
                const idReemplazo = esReemplazo ? Number(documentoReemplazoId.value) : null;
                const tamanoProyectado = calcularTamanoProyectado(nuevoDocumento.archivo, idReemplazo);

                if (tamanoProyectado > LIMITE_TOTAL_ARCHIVOS) {
                    erroresDocumento.value = {
                        ...erroresDocumento.value,
                        archivo: 'El tamaño total de los archivos no puede superar 20 MB.'
                    };
                    return;
                }

                const documento = {
                    titulo: nuevoDocumento.titulo.trim(),
                    descripcion: nuevoDocumento.descripcion.trim(),
                    archivo: nuevoDocumento.archivo
                };

                if (esReemplazo) {
                    documentosReemplazos.value = documentosReemplazos.value.filter((item) => {
                        return Number(item.id) !== idReemplazo;
                    });
                    documentosReemplazos.value.push({
                        id: idReemplazo,
                        ...documento
                    });
                } else {
                    documentosNuevos.value.push(documento);
                }

                cerrarModalDocumento();
                limpiarError('documentos');
            };

            const quitarDocumentoNuevo = (indice) => {
                documentosNuevos.value.splice(indice, 1);
                limpiarError('documentos');
            };

            const cancelarReemplazo = (id) => {
                documentosReemplazos.value = documentosReemplazos.value.filter((item) => {
                    return Number(item.id) !== Number(id);
                });
                limpiarError('documentos');
            };

            const urlDocumento = (ruta) => {
                const valor = String(ruta || '');

                if (/^https?:\/\//i.test(valor)) {
                    return valor;
                }

                const rutaLimpia = valor.replace(/^\/+/, '');

                return rutaLimpia.startsWith('documentos/') ? rutaLimpia : 'documentos/' + rutaLimpia;
            };

            const nombreArchivo = (ruta) => {
                const partes = String(ruta || '').split(/[\\/]/);
                return partes[partes.length - 1] || 'Archivo';
            };

            const formatearFecha = (valor) => {
                if (!valor) {
                    return '—';
                }

                const fecha = new Date(String(valor).replace(' ', 'T'));

                if (Number.isNaN(fecha.getTime())) {
                    return String(valor);
                }

                return fecha.toLocaleDateString('es-CO', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            };

            const formatearTamano = (bytes) => {
                if (!Number.isFinite(bytes) || bytes <= 0) {
                    return '';
                }

                if (bytes < 1024) {
                    return bytes + ' B';
                }

                if (bytes < 1024 * 1024) {
                    return (bytes / 1024).toFixed(1) + ' KB';
                }

                return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
            };

            const formatearPresupuesto = () => {
                let valor = String(form.presupuesto).replace(',', '.').replace(/[^\d.-]/g, '');
                const partes = valor.split('.');

                valor = partes[0].slice(0, 13);
                if (partes.length > 1) {
                    valor += '.' + partes.slice(1).join('').slice(0, 2);
                }

                form.presupuesto = valor;
                limpiarError('presupuesto');
            };

            const buscarActividades = async (termino) => {
                const peticionActual = ++idPeticion;
                cargandoActividades.value = true;
                errorActividades.value = '';

                try {
                    const respuesta = await fetch(
                        'index.php?accion=obtener_actividades&q=' + encodeURIComponent(termino),
                        {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' },
                            credentials: 'include',
                            cache: 'no-store'
                        }
                    );

                    if (!respuesta.ok) {
                        throw new Error('El servidor respondió con estado ' + respuesta.status + '.');
                    }

                    const data = await respuesta.json();

                    if (!data.success) {
                        throw new Error(data.mensaje || 'No se pudieron obtener las actividades.');
                    }

                    if (peticionActual !== idPeticion) {
                        return;
                    }

                    actividades.value = data.actividades || [];
                } catch (error) {
                    if (peticionActual !== idPeticion) {
                        return;
                    }

                    errorActividades.value = 'Error al buscar actividades: ' + error.message;
                    actividades.value = [];
                } finally {
                    if (peticionActual === idPeticion) {
                        cargandoActividades.value = false;
                    }
                }
            };

            const alEscribirActividad = () => {
                form.actividad = null;
                limpiarError('actividad');
                errorActividades.value = '';

                if (temporizadorBusqueda) {
                    clearTimeout(temporizadorBusqueda);
                }

                const termino = busquedaActividad.value.trim();

                if (termino.length < 2) {
                    actividades.value = [];
                    mostrarResultados.value = false;
                    cargandoActividades.value = false;
                    idPeticion++;
                    return;
                }

                mostrarResultados.value = true;
                cargandoActividades.value = true;

                temporizadorBusqueda = setTimeout(() => {
                    buscarActividades(termino);
                }, 300);
            };

            const seleccionarActividad = (actividad) => {
                form.actividad = actividad.id;
                busquedaActividad.value = actividad.producto ? String(actividad.producto) : '';
                mostrarResultados.value = false;
                actividades.value = [];
                errorActividades.value = '';
                cargandoActividades.value = false;
                idPeticion++;
                limpiarError('actividad');

                if (temporizadorBusqueda) {
                    clearTimeout(temporizadorBusqueda);
                }
            };

            const abrirResultados = () => {
                if (busquedaActividad.value.trim().length >= 2 && actividades.value.length > 0) {
                    mostrarResultados.value = true;
                }
            };

            const cerrarResultados = () => {
                mostrarResultados.value = false;
            };

            const manejarClicFuera = (evento) => {
                if (contenedorActividad.value && !contenedorActividad.value.contains(evento.target)) {
                    mostrarResultados.value = false;
                }
            };

            const validarCronograma = () => {
                if (!form.fecha_inicio || !form.fecha_cierre) {
                    return;
                }

                if (form.fecha_cierre < form.fecha_inicio) {
                    errores.value = {
                        ...errores.value,
                        fecha_cierre: 'La fecha de cierre no puede ser anterior a la fecha de inicio.'
                    };
                    return;
                }

                if (form.fecha_inicio === form.fecha_cierre
                    && form.hora_inicio
                    && form.hora_cierre
                    && form.hora_cierre <= form.hora_inicio) {
                    errores.value = {
                        ...errores.value,
                        hora_cierre: 'La hora de cierre debe ser posterior a la hora de inicio.'
                    };
                }
            };

            const actualizarCronograma = () => {
                const copia = { ...errores.value };
                delete copia.fecha_inicio;
                delete copia.hora_inicio;
                delete copia.fecha_cierre;
                delete copia.hora_cierre;
                errores.value = copia;
                validarCronograma();
            };

            const validarFormulario = () => {
                const nuevosErrores = {};

                form.objeto = form.objeto.trim();
                form.descripcion = form.descripcion.trim();

                if (form.objeto.length < 3 || form.objeto.length > 150) {
                    nuevosErrores.objeto = 'El objeto debe tener entre 3 y 150 caracteres.';
                }

                if (!form.descripcion) {
                    nuevosErrores.descripcion = 'La descripción es obligatoria.';
                } else if (form.descripcion.length > 400) {
                    nuevosErrores.descripcion = 'La descripción no puede superar 400 caracteres.';
                }

                if (!['COP', 'USD', 'EUR'].includes(form.moneda)) {
                    nuevosErrores.moneda = 'Seleccione una moneda válida.';
                }

                const presupuesto = String(form.presupuesto).trim().replace(',', '.');

                if (!/^\d{1,13}(?:\.\d{0,2})?$/.test(presupuesto) || Number(presupuesto) <= 0) {
                    nuevosErrores.presupuesto = 'Ingrese un presupuesto mayor a cero, con máximo 2 decimales.';
                }

                if (!form.actividad) {
                    nuevosErrores.actividad = 'Debe seleccionar una actividad de la lista.';
                }

                if (!form.fecha_inicio) {
                    nuevosErrores.fecha_inicio = 'La fecha de inicio es obligatoria.';
                }

                if (!form.hora_inicio) {
                    nuevosErrores.hora_inicio = 'La hora de inicio es obligatoria.';
                }

                if (!form.fecha_cierre) {
                    nuevosErrores.fecha_cierre = 'La fecha de cierre es obligatoria.';
                }

                if (!form.hora_cierre) {
                    nuevosErrores.hora_cierre = 'La hora de cierre es obligatoria.';
                }

                if (form.fecha_inicio && form.fecha_cierre && form.hora_inicio && form.hora_cierre) {
                    const inicio = `${form.fecha_inicio}T${form.hora_inicio}`;
                    const cierre = `${form.fecha_cierre}T${form.hora_cierre}`;

                    if (cierre <= inicio) {
                        nuevosErrores.fecha_cierre = 'La fecha y hora de cierre debe ser posterior a la fecha y hora de inicio.';
                    }
                }

                // Validación de la sección de documentos (solo en edición)
                if (esEdicion) {
                    if (totalDocumentos.value === 0) {
                        nuevosErrores.documentos = 'Debe registrar al menos un documento PDF o ZIP.';
                    }

                    documentosNuevos.value.forEach((documento, indice) => {
                        if (Object.keys(validarDocumento(documento)).length > 0) {
                            nuevosErrores.documentos = 'Revise la información del documento N.º ' + (indice + 1) + '.';
                        }
                    });

                    documentosReemplazos.value.forEach((documento, indice) => {
                        if (Object.keys(validarDocumento(documento)).length > 0) {
                            nuevosErrores.documentos = 'Revise la información del reemplazo N.º ' + (indice + 1) + '.';
                        }
                    });

                    if (totalBytesSubida.value > LIMITE_TOTAL_ARCHIVOS) {
                        nuevosErrores.documentos = 'El tamaño total de los archivos no puede superar 20 MB.';
                    }
                }

                errores.value = nuevosErrores;
                return Object.keys(nuevosErrores).length === 0;
            };

            const enviarFormulario = async () => {
                if (enviando.value || guardadoExitoso.value) {
                    return;
                }

                alerta.mostrar = false;

                if (!validarFormulario()) {
                    mostrarAlerta('danger', 'Formulario incompleto', 'Revise los campos marcados antes de continuar.');
                    return;
                }

                enviando.value = true;

                try {
                    const formulario = document.getElementById('formOferta');
                    const datos = new FormData(formulario);

                    // Los documentos pendientes se envían junto con la oferta en la misma petición AJAX
                    documentosNuevos.value.forEach((documento) => {
                        datos.append('documentos_nuevos_titulo[]', documento.titulo);
                        datos.append('documentos_nuevos_descripcion[]', documento.descripcion);
                        datos.append('documentos_nuevos_archivo[]', documento.archivo, documento.archivo.name);
                    });

                    // Los reemplazos de documentos existentes viajan en la misma petición
                    documentosReemplazos.value.forEach((documento) => {
                        datos.append('documentos_reemplazo_id[]', documento.id);
                        datos.append('documentos_reemplazo_titulo[]', documento.titulo);
                        datos.append('documentos_reemplazo_descripcion[]', documento.descripcion);
                        datos.append('documentos_reemplazo_archivo[]', documento.archivo, documento.archivo.name);
                    });

                    const respuesta = await fetch(urlGuardar, {
                        method: 'POST',
                        body: datos,
                        headers: { 'Accept': 'application/json' },
                        credentials: 'include',
                        cache: 'no-store'
                    });

                    let data;

                    try {
                        data = await respuesta.json();
                    } catch (error) {
                        throw new Error('El servidor no devolvió una respuesta JSON válida.');
                    }

                    if (!respuesta.ok || !data.success) {
                        if (data.errores && typeof data.errores === 'object') {
                            errores.value = data.errores;
                        }

                        mostrarAlerta(
                            'danger',
                            esEdicion ? 'No se pudo actualizar la oferta' : 'No se pudo guardar la oferta',
                            data.mensaje || 'Ocurrió un error al procesar la solicitud.'
                        );
                        return;
                    }

                    guardadoExitoso.value = true;

                    const detalleConsecutivo = data.consecutivo
                        ? ' Consecutivo: ' + data.consecutivo + '.'
                        : '';

                    mostrarAlerta(
                        'success',
                        esEdicion ? 'Oferta actualizada' : 'Oferta guardada',
                        (data.mensaje || 'Operación realizada correctamente.') + detalleConsecutivo
                            + ' Será redirigido al listado en 4 segundos.'
                    );

                    setTimeout(() => {
                        const mensaje = esEdicion ? 'actualizado' : 'creado';
                        window.location.href = 'index.php?accion=listar&mensaje=' + mensaje;
                    }, 4000);
                } catch (error) {
                    mostrarAlerta(
                        'danger',
                        'Error de conexión',
                        error.message || 'No fue posible comunicarse con el servidor.'
                    );
                } finally {
                    enviando.value = false;
                }
            };

            onMounted(() => {
                document.addEventListener('click', manejarClicFuera);
            });

            onBeforeUnmount(() => {
                document.removeEventListener('click', manejarClicFuera);

                if (temporizadorBusqueda) {
                    clearTimeout(temporizadorBusqueda);
                }
            });

            return {
                form,
                errores,
                alerta,
                enviando,
                guardadoExitoso,
                busquedaActividad,
                actividades,
                mostrarResultados,
                cargandoActividades,
                errorActividades,
                contenedorActividad,
                documentosExistentes,
                documentosExistentesVista,
                documentosNuevos,
                documentosReemplazos,
                totalDocumentos,
                totalBytesSubida,
                modalDocumento,
                modoDocumento,
                documentoEnReemplazo,
                nuevoDocumento,
                erroresDocumento,
                inputArchivo,
                fechaActual,
                simboloMoneda,
                minHoraCierre,
                textoBoton,
                esEdicion,
                urlGuardar,
                ofertaId,
                limpiarError,
                limpiarErrorDocumento,
                abrirModalDocumento,
                abrirModalReemplazo,
                cerrarModalDocumento,
                seleccionarArchivo,
                agregarDocumento,
                quitarDocumentoNuevo,
                cancelarReemplazo,
                urlDocumento,
                nombreArchivo,
                formatearFecha,
                formatearTamano,
                formatearPresupuesto,
                alEscribirActividad,
                seleccionarActividad,
                abrirResultados,
                cerrarResultados,
                actualizarCronograma,
                enviarFormulario
            };
        }
    }).mount('#app-oferta');
})();
