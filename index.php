<?php
// Archivo: index.php
// Propósito: Punto de entrada principal del proyecto.
// Controla las acciones de las ofertas/licitaciones y del Validador de Cargas.

require_once __DIR__ . '/Controlador/OfertasControlador.php';
require_once __DIR__ . '/Controlador/ValidadorCargasControlador.php';

// Instancia de controladores
$controladorOfertas = new OfertasControlador();
$controladorValidador = new ValidadorCargasControlador();

// Obtiene la acción a ejecutar desde la URL
$accion = $_GET['accion'] ?? 'listar';

// Enruta las acciones del CRUD
switch ($accion) {
    case 'listar':
        $controladorOfertas->listar();
        break;
    case 'crear':
        $controladorOfertas->crear();
        break;
    case 'guardar':
    case 'guardar_oferta':
        // Creación de ofertas por AJAX (respuesta JSON)
        $controladorOfertas->guardarAjax();
        break;
    case 'editar':
        $controladorOfertas->editar();
        break;
    case 'actualizar':
    case 'actualizar_oferta':
        // Actualización de ofertas por AJAX (respuesta JSON)
        $controladorOfertas->actualizarAjax();
        break;
    case 'detalle':
        // Vista de detalle consolidado con pestañas
        $controladorOfertas->detalle();
        break;
    case 'exportar_excel':
        // Descarga el listado filtrado en formato Excel (sin paginación)
        $controladorOfertas->exportarExcel();
        break;
    case 'eliminar':
        $controladorOfertas->eliminar();
        break;
    
    // Acciones del Validador de Cargas
    case 'validador_cargas':
        // Muestra la vista del validador de cargas
        $controladorValidador->mostrar();
        break;
    case 'procesar_carga':
        // Procesa el archivo CSV cargado
        $controladorValidador->procesar();
        break;
    case 'obtener_actividades':
        // Devuelve las actividades en formato JSON para el select de la vista
        $controladorValidador->listarActividades();
        break;
    
    default:
        // Por defecto muestra el listado de ofertas
        $controladorOfertas->listar();
        break;
}
