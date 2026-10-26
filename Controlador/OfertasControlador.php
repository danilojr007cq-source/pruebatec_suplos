<?php
// Archivo: Controlador/OfertasControlador.php
// Propósito: Controlador de ofertas/licitaciones.
// - Listado con filtros y paginación del lado del servidor.
// - Creación y edición mediante AJAX (respuestas JSON).
// - Vista de detalle consolidada con pestañas.

require_once __DIR__ . '/../Modelo/Ofertas.php';
require_once __DIR__ . '/../Modelo/Actividad.php';

class OfertasControlador
{
    // Valores permitidos para los campos cerrados
    private $monedasPermitidas = array('COP', 'USD', 'EUR');
    private $estadosPermitidos = array('BORRADOR', 'ACTIVA', 'CERRADA');

    // Tamaño máximo total (en bytes) de todos los archivos enviados en una actualización
    private $limiteTotalDocumentos = 20971520; // 20 MB

    // Muestra el listado de ofertas con filtros y paginación
    public function listar()
    {
        // Filtros enviados por la URL (se conservan al paginar)
        $filtros = array(
            'id' => $this->texto($_GET['id'] ?? ''),
            'consecutivo' => $this->texto($_GET['consecutivo'] ?? ''),
            'objeto' => $this->texto($_GET['objeto'] ?? ''),
            'descripcion' => $this->texto($_GET['descripcion'] ?? ''),
            'moneda' => $this->texto($_GET['moneda'] ?? ''),
            'actividad' => $this->texto($_GET['actividad'] ?? ''),
            'presupuesto_min' => $this->texto($_GET['presupuesto_min'] ?? ''),
            'presupuesto_max' => $this->texto($_GET['presupuesto_max'] ?? ''),
            'fecha_desde' => $this->texto($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => $this->texto($_GET['fecha_hasta'] ?? '')
        );

        // Tamaño de página permitido
        $porPagina = $this->entero($_GET['por_pagina'] ?? 10);
        if (!in_array($porPagina, array(10, 25, 50), true)) {
            $porPagina = 10;
        }

        $pagina = $this->entero($_GET['pagina'] ?? 1);

        // Consulta paginada en el modelo
        $resultado = Ofertas::listarPaginado($filtros, $pagina, $porPagina);

        $ofertas = $resultado['datos'];
        $total = $resultado['total'];
        $pagina = $resultado['pagina'];
        $totalPaginas = $resultado['total_paginas'];

        $desde = $total > 0 ? (($pagina - 1) * $porPagina) + 1 : 0;
        $hasta = min($total, $pagina * $porPagina);

        // Parámetros para conservar los filtros en los enlaces de paginación
        $parametros = array_merge(
            array('accion' => 'listar'),
            array_filter($filtros, function ($valor) {
                return $valor !== '';
            }),
            array('por_pagina' => $porPagina)
        );
        $urlBase = 'index.php?' . http_build_query($parametros);

        include __DIR__ . '/../Vista/listar.php';
    }

    // Genera y descarga en Excel el listado completo (sin paginación),
    // conservando los mismos filtros que trae la URL del listado.
    public function exportarExcel()
    {
        $filtros = array(
            'id' => $this->texto($_GET['id'] ?? ''),
            'consecutivo' => $this->texto($_GET['consecutivo'] ?? ''),
            'objeto' => $this->texto($_GET['objeto'] ?? ''),
            'descripcion' => $this->texto($_GET['descripcion'] ?? ''),
            'moneda' => $this->texto($_GET['moneda'] ?? ''),
            'actividad' => $this->texto($_GET['actividad'] ?? ''),
            'presupuesto_min' => $this->texto($_GET['presupuesto_min'] ?? ''),
            'presupuesto_max' => $this->texto($_GET['presupuesto_max'] ?? ''),
            'fecha_desde' => $this->texto($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => $this->texto($_GET['fecha_hasta'] ?? '')
        );

        $ofertas = Ofertas::listarTodos($filtros);

        require_once __DIR__ . '/ReporteOfertasExcel.php';

        try {
            ReporteOfertasExcel::descargar($ofertas);
        } catch (Throwable $e) {
            error_log('Error al generar el reporte Excel: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'No fue posible generar el reporte en Excel.';
        }
    }

    // Muestra el formulario de creación
    public function crear()
    {
        // Valores iniciales del formulario
        $datosIniciales = array(
            'objeto' => '',
            'descripcion' => '',
            'moneda' => 'COP',
            'presupuesto' => '',
            'actividad' => null,
            'fecha_inicio' => '',
            'hora_inicio' => '',
            'fecha_cierre' => '',
            'hora_cierre' => ''
        );

        $actividadInicial = null;
        $esEdicion = false;
        $urlGuardar = 'index.php?accion=guardar_oferta';
        $ofertaId = 0;
        $consecutivo = null;

        include __DIR__ . '/../Vista/crear.php';
    }

    // Guarda una oferta nueva por AJAX y responde en JSON
    public function guardarAjax()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'Método no permitido. Use POST.'
            ), 405);
            return;
        }

        $validacion = $this->validarOferta($_POST);
        $datos = $validacion['datos'];
        $errores = $validacion['errores'];

        if (!empty($errores)) {
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'Revise los campos marcados en el formulario.',
                'errores' => $errores
            ), 422);
            return;
        }

        try {
            // Datos generados por el servidor
            $datos['creado_en'] = date('Y-m-d H:i:s');
            $datos['actualizado_en'] = $datos['creado_en'];

            $oferta = Ofertas::crear($datos);

            $this->responderJson(array(
                'success' => true,
                'mensaje' => 'La oferta se guardó correctamente.',
                'id' => $oferta->id,
                'consecutivo' => $oferta->consecutivo
            ), 201);
        } catch (Throwable $e) {
            error_log('Error al crear oferta: ' . $e->getMessage());
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'No fue posible guardar la oferta. Intente nuevamente.'
            ), 500);
        }
    }

    // Muestra el formulario de edición con los datos de la oferta
    public function editar()
    {
        $id = $this->entero($_GET['id'] ?? 0);
        $oferta = Ofertas::obtenerDetalle($id);

        if (!$oferta) {
            header('Location: index.php?accion=listar&mensaje=noencontrado');
            exit;
        }

        $datosIniciales = array(
            'objeto' => $oferta->objeto,
            'descripcion' => $oferta->descripcion,
            'moneda' => $oferta->moneda ?: 'COP',
            'presupuesto' => $oferta->presupuesto !== null ? number_format((float) $oferta->presupuesto, 2, '.', '') : '',
            'actividad' => $oferta->actividad_id,
            'fecha_inicio' => $oferta->fecha_inicio,
            'hora_inicio' => $this->formatearHora($oferta->hora_inicio),
            'fecha_cierre' => $oferta->fecha_cierre,
            'hora_cierre' => $this->formatearHora($oferta->hora_cierre),
            'estado' => $oferta->estado ?: 'ACTIVA'
        );

        $actividadInicial = $oferta->actividad ? array(
            'id' => $oferta->actividad->id,
            'producto' => $oferta->actividad->producto
        ) : null;

        // Documentos ya registrados para mostrarlos en la sección de edición
        $documentosIniciales = array();
        foreach ($oferta->documentos as $documento) {
            $documentosIniciales[] = array(
                'id' => (int) $documento->id,
                'titulo' => $documento->titulo,
                'descripcion' => $documento->descripcion,
                'archivo' => $documento->archivo,
                'creado_en' => $documento->creado_en
            );
        }

        $esEdicion = true;
        $urlGuardar = 'index.php?accion=actualizar_oferta';
        $ofertaId = $oferta->id;
        $consecutivo = $oferta->consecutivo;

        include __DIR__ . '/../Vista/editar.php';
    }

    // Actualiza una oferta existente por AJAX y responde en JSON
    public function actualizarAjax()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'Método no permitido. Use POST.'
            ), 405);
            return;
        }

        $id = $this->entero($_POST['id'] ?? 0);
        $oferta = Ofertas::obtenerPorId($id);

        if (!$oferta) {
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'La oferta que intenta actualizar no existe.'
            ), 404);
            return;
        }

        $validacion = $this->validarOferta($_POST);
        $datos = $validacion['datos'];
        $errores = $validacion['errores'];

        // Validación estricta de los documentos enviados desde la sección de edición
        $validacionDocumentos = $this->validarDocumentos($id, $_POST, $_FILES);
        $documentosNuevos = $validacionDocumentos['documentos'];
        $documentosReemplazos = $validacionDocumentos['reemplazos'];
        $errores = array_merge($errores, $validacionDocumentos['errores']);

        if (!empty($errores)) {
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'Revise los campos marcados en el formulario.',
                'errores' => $errores
            ), 422);
            return;
        }

        $documentosGuardados = array();
        $reemplazosGuardados = array();

        try {
            // Los archivos se mueven antes de la transacción. Si algo falla, se eliminan.
            $documentosGuardados = $this->moverDocumentos($documentosNuevos, $id);
            $reemplazosGuardados = $this->moverDocumentos($documentosReemplazos, $id);
            $datos['actualizado_en'] = date('Y-m-d H:i:s');

            \Illuminate\Database\Capsule\Manager::connection()->transaction(function () use ($id, $datos, $documentosGuardados, $reemplazosGuardados) {
                $actualizada = Ofertas::actualizarRegistro($id, $datos);

                if (!$actualizada) {
                    throw new RuntimeException('La oferta que intenta actualizar no existe.');
                }

                foreach ($documentosGuardados as $documento) {
                    OfertaDocumento::create(array(
                        'licitacion_id' => $id,
                        'titulo' => $documento['titulo'],
                        'descripcion' => $documento['descripcion'],
                        'archivo' => $documento['archivo'],
                        'creado_en' => date('Y-m-d H:i:s')
                    ));
                }

                foreach ($reemplazosGuardados as $reemplazo) {
                    $filasActualizadas = OfertaDocumento::where('id', $reemplazo['id'])
                        ->where('licitacion_id', $id)
                        ->update(array(
                            'titulo' => $reemplazo['titulo'],
                            'descripcion' => $reemplazo['descripcion'],
                            'archivo' => $reemplazo['archivo']
                        ));

                    if (!$filasActualizadas) {
                        throw new RuntimeException('El documento que intenta reemplazar no existe.');
                    }
                }
            });

            // Los archivos anteriores se eliminan solo después de confirmar la transacción
            foreach ($reemplazosGuardados as $reemplazo) {
                try {
                    $this->eliminarArchivoDocumento($reemplazo['archivo_anterior']);
                } catch (Throwable $errorArchivo) {
                    error_log('No fue posible eliminar el archivo reemplazado: ' . $errorArchivo->getMessage());
                }
            }

            $this->responderJson(array(
                'success' => true,
                'mensaje' => 'La oferta se actualizó correctamente.',
                'id' => $id,
                'consecutivo' => $oferta->consecutivo,
                'documentos' => count($documentosGuardados),
                'reemplazos' => count($reemplazosGuardados)
            ), 200);
        } catch (Throwable $e) {
            // Revierte los archivos físicos nuevos si la transacción no se completó
            $archivosMovidos = array_merge($documentosGuardados, $reemplazosGuardados);

            foreach ($archivosMovidos as $documento) {
                if (!empty($documento['ruta_fisica']) && is_file($documento['ruta_fisica'])) {
                    @unlink($documento['ruta_fisica']);
                }
            }

            error_log('Error al actualizar oferta: ' . $e->getMessage());
            $this->responderJson(array(
                'success' => false,
                'mensaje' => 'No fue posible actualizar la oferta. Intente nuevamente.'
            ), 500);
        }
    }

    // Muestra la vista de detalle consolidada de una oferta
    public function detalle()
    {
        $id = $this->entero($_GET['id'] ?? 0);
        $oferta = Ofertas::obtenerDetalle($id);

        if (!$oferta) {
            header('Location: index.php?accion=listar&mensaje=noencontrado');
            exit;
        }

        include __DIR__ . '/../Vista/detalle.php';
    }

    // Elimina una oferta. No se expone en la tabla actual; por seguridad solo
    // acepta POST para evitar borrados accidentales mediante enlaces GET.
    public function eliminar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido. Use POST.');
        }

        $id = $this->entero($_POST['id'] ?? 0);
        Ofertas::eliminarRegistro($id);

        header('Location: index.php?accion=listar&mensaje=eliminado');
        exit;
    }

    // Valida y normaliza los datos recibidos del formulario
    private function validarOferta($entrada)
    {
        $errores = array();
        $entrada = is_array($entrada) ? $entrada : array();

        $objeto = $this->texto($entrada['objeto'] ?? '');
        $descripcion = $this->texto($entrada['descripcion'] ?? '');
        $moneda = strtoupper($this->texto($entrada['moneda'] ?? ''));
        $presupuestoRaw = $this->texto($entrada['presupuesto'] ?? '');
        $actividadRaw = $entrada['actividad'] ?? 0;
        $actividadId = is_scalar($actividadRaw) ? (int) $actividadRaw : 0;
        $fechaInicio = $this->texto($entrada['fecha_inicio'] ?? '');
        $horaInicio = $this->texto($entrada['hora_inicio'] ?? '');
        $fechaCierre = $this->texto($entrada['fecha_cierre'] ?? '');
        $horaCierre = $this->texto($entrada['hora_cierre'] ?? '');
        $estado = strtoupper($this->texto($entrada['estado'] ?? 'ACTIVA'));

        // Objeto
        if ($objeto === '') {
            $errores['objeto'] = 'El objeto es obligatorio.';
        } elseif (mb_strlen($objeto, 'UTF-8') < 3 || mb_strlen($objeto, 'UTF-8') > 150) {
            $errores['objeto'] = 'El objeto debe tener entre 3 y 150 caracteres.';
        }

        // Descripción
        if ($descripcion === '') {
            $errores['descripcion'] = 'La descripción es obligatoria.';
        } elseif (mb_strlen($descripcion, 'UTF-8') > 400) {
            $errores['descripcion'] = 'La descripción no puede superar 400 caracteres.';
        }

        // Moneda
        if (!in_array($moneda, $this->monedasPermitidas, true)) {
            $errores['moneda'] = 'Seleccione una moneda válida (COP, USD o EUR).';
        }

        // Presupuesto
        $presupuesto = null;
        if ($presupuestoRaw === '') {
            $errores['presupuesto'] = 'El presupuesto es obligatorio.';
        } else {
            $presupuestoNormalizado = str_replace(',', '.', $presupuestoRaw);

            if (!preg_match('/^\d{1,13}(?:\.\d{0,2})?$/', $presupuestoNormalizado)) {
                $errores['presupuesto'] = 'El presupuesto debe ser un número con máximo 2 decimales.';
            } else {
                $presupuesto = round((float) $presupuestoNormalizado, 2);

                if ($presupuesto <= 0) {
                    $errores['presupuesto'] = 'El presupuesto debe ser mayor a cero.';
                }
            }
        }

        // Actividad
        if ($actividadId <= 0) {
            $errores['actividad'] = 'Debe seleccionar una actividad de la lista.';
        } elseif (!Actividad::where('id', $actividadId)->exists()) {
            $errores['actividad'] = 'La actividad seleccionada no existe.';
        }

        // Fechas y horas
        if (!$this->esFechaValida($fechaInicio)) {
            $errores['fecha_inicio'] = 'La fecha de inicio es obligatoria y debe ser válida.';
        }

        if (!$this->esHoraValida($horaInicio)) {
            $errores['hora_inicio'] = 'La hora de inicio es obligatoria y debe ser válida.';
        }

        if (!$this->esFechaValida($fechaCierre)) {
            $errores['fecha_cierre'] = 'La fecha de cierre es obligatoria y debe ser válida.';
        }

        if (!$this->esHoraValida($horaCierre)) {
            $errores['hora_cierre'] = 'La hora de cierre es obligatoria y debe ser válida.';
        }

        if ($this->esFechaValida($fechaInicio) && $this->esFechaValida($fechaCierre)
            && $this->esHoraValida($horaInicio) && $this->esHoraValida($horaCierre)) {
            $inicio = strtotime($fechaInicio . ' ' . $horaInicio);
            $cierre = strtotime($fechaCierre . ' ' . $horaCierre);

            if ($cierre <= $inicio) {
                $errores['fecha_cierre'] = 'La fecha y hora de cierre debe ser posterior a la fecha y hora de inicio.';
            }
        }

        // Estado
        if (!in_array($estado, $this->estadosPermitidos, true)) {
            $errores['estado'] = 'Seleccione un estado válido.';
        }

        // Normaliza las horas a HH:MM:SS para la base de datos
        $horaInicioDb = $this->esHoraValida($horaInicio) ? date('H:i:s', strtotime($horaInicio)) : null;
        $horaCierreDb = $this->esHoraValida($horaCierre) ? date('H:i:s', strtotime($horaCierre)) : null;

        $datos = array(
            'objeto' => $objeto,
            'descripcion' => $descripcion,
            'moneda' => $moneda,
            'presupuesto' => $presupuesto,
            'actividad_id' => $actividadId > 0 ? $actividadId : null,
            'fecha_inicio' => $this->esFechaValida($fechaInicio) ? $fechaInicio : null,
            'hora_inicio' => $horaInicioDb,
            'fecha_cierre' => $this->esFechaValida($fechaCierre) ? $fechaCierre : null,
            'hora_cierre' => $horaCierreDb,
            'estado' => $estado
        );

        return array('datos' => $datos, 'errores' => $errores);
    }

    // Valida los documentos nuevos y los reemplazos enviados desde la sección de edición.
    // Exige al menos un documento asociado, que cada archivo sea PDF o ZIP y que
    // la suma de todos los archivos no supere el límite permitido.
    private function validarDocumentos($ofertaId, $entrada, $archivos)
    {
        $entrada = is_array($entrada) ? $entrada : array();
        $archivos = is_array($archivos) ? $archivos : array();

        $mensajes = array();
        $nuevos = array();
        $reemplazos = array();

        $titulos = isset($entrada['documentos_nuevos_titulo'])
            ? array_values((array) $entrada['documentos_nuevos_titulo'])
            : array();
        $descripciones = isset($entrada['documentos_nuevos_descripcion'])
            ? array_values((array) $entrada['documentos_nuevos_descripcion'])
            : array();

        $idsReemplazo = isset($entrada['documentos_reemplazo_id'])
            ? array_values((array) $entrada['documentos_reemplazo_id'])
            : array();
        $titulosReemplazo = isset($entrada['documentos_reemplazo_titulo'])
            ? array_values((array) $entrada['documentos_reemplazo_titulo'])
            : array();
        $descripcionesReemplazo = isset($entrada['documentos_reemplazo_descripcion'])
            ? array_values((array) $entrada['documentos_reemplazo_descripcion'])
            : array();

        $listaArchivos = $this->normalizarArchivosSubidos($archivos['documentos_nuevos_archivo'] ?? null);
        $listaArchivosReemplazo = $this->normalizarArchivosSubidos($archivos['documentos_reemplazo_archivo'] ?? null);

        // Los metadatos y los archivos deben llegar en el mismo orden y cantidad
        $estructuraNuevosValida = count($listaArchivos) === count($titulos)
            && count($titulos) === count($descripciones);
        $estructuraReemplazosValida = count($listaArchivosReemplazo) === count($idsReemplazo)
            && count($idsReemplazo) === count($titulosReemplazo)
            && count($titulosReemplazo) === count($descripcionesReemplazo);

        if (!$estructuraNuevosValida || !$estructuraReemplazosValida) {
            $mensajes[] = 'La información de los documentos no es válida. Intente nuevamente.';
        }

        // Límite total de todos los archivos (nuevos y reemplazos)
        $tamanoTotal = 0;
        foreach (array_merge($listaArchivos, $listaArchivosReemplazo) as $archivo) {
            $tamanoTotal += max(0, (int) ($archivo['size'] ?? 0));
        }

        if ($tamanoTotal > $this->limiteTotalDocumentos) {
            $mensajes[] = 'El tamaño total de los archivos no puede superar 20 MB.';
        }

        if ($estructuraNuevosValida) {
            foreach ($listaArchivos as $indice => $archivo) {
                $documento = $this->validarDocumentoIndividual(
                    $titulos[$indice] ?? '',
                    $descripciones[$indice] ?? '',
                    $archivo,
                    $indice,
                    $mensajes
                );

                if ($documento !== null) {
                    $nuevos[] = $documento;
                }
            }
        }

        if ($estructuraReemplazosValida) {
            $idsVistos = array();

            foreach ($listaArchivosReemplazo as $indice => $archivo) {
                $idDocumento = (int) $this->texto($idsReemplazo[$indice] ?? 0);

                if ($idDocumento <= 0) {
                    $mensajes[] = 'El documento que intenta reemplazar no es válido.';
                    continue;
                }

                if (isset($idsVistos[$idDocumento])) {
                    $mensajes[] = 'No se puede reemplazar el mismo documento más de una vez.';
                    continue;
                }

                $idsVistos[$idDocumento] = true;

                $documentoExistente = OfertaDocumento::where('licitacion_id', $ofertaId)
                    ->where('id', $idDocumento)
                    ->first();

                if (!$documentoExistente) {
                    $mensajes[] = 'El documento que intenta reemplazar no existe o no pertenece a la oferta.';
                    continue;
                }

                $documento = $this->validarDocumentoIndividual(
                    $titulosReemplazo[$indice] ?? '',
                    $descripcionesReemplazo[$indice] ?? '',
                    $archivo,
                    $indice,
                    $mensajes
                );

                if ($documento !== null) {
                    $documento['id'] = $idDocumento;
                    $documento['archivo_anterior'] = $documentoExistente->archivo;
                    $reemplazos[] = $documento;
                }
            }
        }

        // La oferta debe conservar o registrar al menos un documento
        $existentes = OfertaDocumento::where('licitacion_id', $ofertaId)->count();
        $hayIntentoDeRegistro = !empty($listaArchivos)
            || !empty($listaArchivosReemplazo)
            || !empty($titulos)
            || !empty($idsReemplazo);

        if ($existentes < 1 && !$hayIntentoDeRegistro) {
            $mensajes[] = 'Debe registrar al menos un documento en formato PDF o ZIP.';
        }

        $errores = array();
        if (!empty($mensajes)) {
            $errores['documentos'] = implode(' ', array_values(array_unique($mensajes)));
        }

        return array(
            'documentos' => $nuevos,
            'reemplazos' => $reemplazos,
            'errores' => $errores
        );
    }

    // Valida los campos y el archivo de un documento individual
    private function validarDocumentoIndividual($tituloRaw, $descripcionRaw, $archivo, $indice, &$mensajes)
    {
        $titulo = $this->texto($tituloRaw);
        $descripcion = $this->texto($descripcionRaw);
        $nombreOriginal = $this->texto($archivo['name'] ?? '');
        $nombreOriginal = $nombreOriginal !== '' ? basename($nombreOriginal) : '';
        $tmpName = (string) ($archivo['tmp_name'] ?? '');
        $errorSubida = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $etiqueta = $titulo !== ''
            ? '“' . mb_substr($titulo, 0, 50, 'UTF-8') . '”'
            : 'N.º ' . ($indice + 1);

        $valido = true;

        if ($titulo === '') {
            $mensajes[] = 'El título del documento ' . $etiqueta . ' es obligatorio.';
            $valido = false;
        } elseif (mb_strlen($titulo, 'UTF-8') > 100) {
            $mensajes[] = 'El título del documento ' . $etiqueta . ' no puede superar 100 caracteres.';
            $valido = false;
        }

        if (mb_strlen($descripcion, 'UTF-8') > 200) {
            $mensajes[] = 'La descripción del documento ' . $etiqueta . ' no puede superar 200 caracteres.';
            $valido = false;
        }

        if ($errorSubida !== UPLOAD_ERR_OK || $nombreOriginal === '') {
            $mensajes[] = 'No fue posible cargar el archivo del documento ' . $etiqueta . '.';
            $valido = false;
        } elseif (!in_array($extension, array('pdf', 'zip'), true)) {
            $mensajes[] = 'El archivo del documento ' . $etiqueta . ' debe tener extensión .pdf o .zip.';
            $valido = false;
        } elseif (!is_uploaded_file($tmpName)) {
            $mensajes[] = 'El archivo del documento ' . $etiqueta . ' no es una carga válida.';
            $valido = false;
        }

        if (!$valido) {
            return null;
        }

        return array(
            'titulo' => $titulo,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'nombre_original' => $nombreOriginal,
            'tmp_name' => $tmpName
        );
    }

    // Normaliza la estructura de $_FILES para una o varias cargas
    private function normalizarArchivosSubidos($archivo)
    {
        if (!is_array($archivo) || !array_key_exists('name', $archivo)) {
            return array();
        }

        if (!is_array($archivo['name'])) {
            return array(array(
                'name' => $archivo['name'],
                'type' => $archivo['type'] ?? '',
                'tmp_name' => $archivo['tmp_name'] ?? '',
                'error' => $archivo['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $archivo['size'] ?? 0
            ));
        }

        $lista = array();
        foreach ($archivo['name'] as $indice => $nombre) {
            $lista[] = array(
                'name' => $nombre,
                'type' => is_array($archivo['type'] ?? null) ? ($archivo['type'][$indice] ?? '') : '',
                'tmp_name' => is_array($archivo['tmp_name'] ?? null) ? ($archivo['tmp_name'][$indice] ?? '') : '',
                'error' => is_array($archivo['error'] ?? null) ? ($archivo['error'][$indice] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
                'size' => is_array($archivo['size'] ?? null) ? ($archivo['size'][$indice] ?? 0) : 0
            );
        }

        return $lista;
    }

    // Mueve los archivos válidos a la carpeta "documentos" de la raíz del proyecto.
    // Sirve tanto para documentos nuevos como para reemplazos.
    private function moverDocumentos($documentos, $ofertaId)
    {
        if (empty($documentos)) {
            return array();
        }

        $directorio = __DIR__ . '/../documentos';

        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new RuntimeException('No fue posible crear la carpeta de documentos.');
        }

        $guardados = array();

        try {
            foreach ($documentos as $documento) {
                $extension = strtolower(pathinfo($documento['nombre_original'], PATHINFO_EXTENSION));
                $base = pathinfo($documento['nombre_original'], PATHINFO_FILENAME);
                $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $base);
                $base = trim((string) $base, '_');
                $base = $base !== '' ? substr($base, 0, 60) : 'documento';

                $nombreArchivo = 'oferta_' . $ofertaId . '_' . date('YmdHis') . '_'
                    . $base . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $destino = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

                if (!move_uploaded_file($documento['tmp_name'], $destino)) {
                    throw new RuntimeException('No fue posible almacenar el archivo ' . $documento['nombre_original'] . '.');
                }

                $guardado = array(
                    'titulo' => $documento['titulo'],
                    'descripcion' => $documento['descripcion'],
                    'archivo' => 'documentos/' . $nombreArchivo,
                    'ruta_fisica' => $destino
                );

                if (isset($documento['id'])) {
                    $guardado['id'] = (int) $documento['id'];
                }

                if (array_key_exists('archivo_anterior', $documento)) {
                    $guardado['archivo_anterior'] = $documento['archivo_anterior'];
                }

                $guardados[] = $guardado;
            }
        } catch (Throwable $e) {
            // Elimina los archivos que alcanzaron a moverse antes del error
            foreach ($guardados as $documento) {
                if (is_file($documento['ruta_fisica'])) {
                    @unlink($documento['ruta_fisica']);
                }
            }

            throw $e;
        }

        return $guardados;
    }

    // Elimina el archivo físico anterior de un documento reemplazado.
    // Solo actúa sobre archivos almacenados en la carpeta "documentos".
    private function eliminarArchivoDocumento($ruta)
    {
        $ruta = trim((string) $ruta);

        if ($ruta === '' || preg_match('~^https?://~i', $ruta)) {
            return;
        }

        $nombre = basename(str_replace('\\', '/', $ruta));

        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            return;
        }

        $destino = __DIR__ . '/../documentos/' . $nombre;

        if (is_file($destino)) {
            @unlink($destino);
        }
    }

    // Convierte un valor escalar a texto recortado; los arreglos u objetos se rechazan
    private function texto($valor)
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    // Convierte un valor escalar a entero; los arreglos u objetos se rechazan
    private function entero($valor)
    {
        return is_scalar($valor) ? (int) $valor : 0;
    }

    // Valida una fecha con formato YYYY-MM-DD
    private function esFechaValida($fecha)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
            return false;
        }

        $partes = explode('-', $fecha);
        return checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]);
    }

    // Valida una hora con formato HH:MM o HH:MM:SS
    private function esHoraValida($hora)
    {
        foreach (array('H:i', 'H:i:s') as $formato) {
            $fechaHora = DateTime::createFromFormat($formato, (string) $hora);
            if ($fechaHora && $fechaHora->format($formato) === (string) $hora) {
                return true;
            }
        }

        return false;
    }

    // Deja la hora en formato HH:MM para los inputs type="time"
    private function formatearHora($hora)
    {
        if (!$hora) {
            return '';
        }

        return substr($hora, 0, 5);
    }

    // Envía una respuesta JSON con el código HTTP indicado
    private function responderJson($datos, $codigo = 200)
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
