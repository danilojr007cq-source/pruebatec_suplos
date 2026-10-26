<?php
// Archivo: Vista/editar.php
// Vista de edición de ofertas. El formulario se envía por AJAX y la lógica
// de actualización está en OfertasControlador::actualizarAjax().

if (!isset($datosIniciales, $esEdicion, $urlGuardar, $ofertaId)) {
    header('Location: index.php?accion=listar');
    exit;
}

include __DIR__ . '/partials/encabezado.php';

$actividadInicial = $actividadInicial ?? null;
$consecutivo = $consecutivo ?? '';

include __DIR__ . '/partials/formulario_oferta.php';
include __DIR__ . '/partials/pie.php';
