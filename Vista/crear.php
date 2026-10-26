<?php
// Archivo: Vista/crear.php
// Vista de creación de ofertas. El formulario se envía por AJAX y la lógica
// de guardado está en OfertasControlador::guardarAjax().

include __DIR__ . '/partials/encabezado.php';

// Valores por defecto en caso de que la vista se cargue sin el controlador.
$datosIniciales = $datosIniciales ?? array(
    'objeto' => '',
    'descripcion' => '',
    'moneda' => 'COP',
    'presupuesto' => '',
    'actividad' => null,
    'fecha_inicio' => '',
    'hora_inicio' => '',
    'fecha_cierre' => '',
    'hora_cierre' => '',
    'estado' => 'ACTIVO'
);
$actividadInicial = $actividadInicial ?? null;
$esEdicion = false;
$urlGuardar = $urlGuardar ?? 'index.php?accion=guardar_oferta';
$ofertaId = $ofertaId ?? 0;
$consecutivo = $consecutivo ?? null;

include __DIR__ . '/partials/formulario_oferta.php';
include __DIR__ . '/partials/pie.php';
