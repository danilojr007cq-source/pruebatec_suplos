<?php
// Archivo: Vista/listar.php
// Listado de ofertas/licitaciones con filtros por columna y paginación.
// Los datos y los filtros son procesados en OfertasControlador::listar()
// y Ofertas::listarPaginado().

include __DIR__ . '/partials/encabezado.php';

// Escapa un valor para mostrarlo en HTML
$e = function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

// Devuelve la clase Bootstrap del badge según el estado
$claseEstado = function ($estado) {
    $clases = array(
        'ACTIVA' => 'success',
        'CERRADA' => 'secondary',
        'BORRADOR' => 'warning text-dark'
    );

    return $clases[$estado] ?? 'light text-dark';
};

$mensaje = $_GET['mensaje'] ?? '';

// Enlace al reporte Excel: conserva los filtros activos y no incluye la página
$parametrosExportar = array_merge(
    array('accion' => 'exportar_excel'),
    array_filter($filtros, function ($valor) {
        return $valor !== '';
    })
);
$urlExportar = 'index.php?' . http_build_query($parametrosExportar);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Listado de licitaciones</h2>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="index.php?accion=validador_cargas" class="btn btn-info text-white">Carga de actividades</a>
        <a href="index.php?accion=crear" class="btn btn-primary">+ Nueva oferta</a>
    </div>
</div>
<!-- Apartado de alertas dependiendo de la accion que se tome -->
<?php if ($mensaje === 'creado'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        La oferta se creó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php elseif ($mensaje === 'actualizado'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        La oferta se actualizó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php elseif ($mensaje === 'eliminado'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        La oferta se eliminó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php elseif ($mensaje === 'noencontrado'): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        La oferta solicitada no existe.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>

<form method="GET" action="index.php" id="formFiltros" class="card shadow-sm mb-3">
    <input type="hidden" name="accion" value="listar">
    <input type="hidden" name="pagina" value="1">

    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <label for="por_pagina" class="form-label mb-0">Registros por página</label>
                <select id="por_pagina" name="por_pagina" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <?php foreach (array(10, 25, 50) as $opcion): ?>
                        <option value="<?= $opcion ?>" <?= $porPagina === $opcion ? 'selected' : '' ?>>
                            <?= $opcion ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex gap-2">
                <a href="index.php?accion=listar" class="btn btn-outline-secondary btn-sm">Limpiar filtros</a>
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 150px;">Consecutivo</th>
                        <th style="min-width: 170px;">Objeto</th>
                        <th style="min-width: 180px;">Descripción</th>
                        <th style="min-width: 150px;">Presupuesto</th>
                        <th style="min-width: 130px;">Fecha inicio</th>
                        <th style="min-width: 130px;">Fecha cierre</th>
<!--                        <th style="min-width: 110px;">Estado</th>-->
                        <th style="min-width: 200px;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span>Acciones</span>
                                <a
                                    href="<?= $e($urlExportar) ?>"
                                    class="btn btn-success btn-sm"
                                    title="Generar un reporte en Excel con todos los registros filtrados, sin paginación"
                                >
                                    Exportar Excel
                                </a>
                            </div>
                        </th>
                    </tr>

                    <tr class="table-light">
                        <th>
                            <input
                                type="text"
                                name="consecutivo"
                                class="form-control form-control-sm"
                                value="<?= $e($filtros['consecutivo']) ?>"
                                placeholder="Buscar..."
                            >
                        </th>
                        <th>
                            <input
                                type="text"
                                name="objeto"
                                class="form-control form-control-sm"
                                value="<?= $e($filtros['objeto']) ?>"
                                placeholder="Buscar..."
                            >
                        </th>
                        <th>
                            <input
                                type="text"
                                name="descripcion"
                                class="form-control form-control-sm"
                                value="<?= $e($filtros['descripcion']) ?>"
                                placeholder="Buscar..."
                            >
                        </th>
                        <th>
                            <div class="d-flex gap-1">
                                <input
                                    type="number"
                                    name="presupuesto_min"
                                    step="0.01"
                                    min="0"
                                    class="form-control form-control-sm"
                                    value="<?= $e($filtros['presupuesto_min']) ?>"
                                    placeholder="Mín."
                                >
                                <input
                                    type="number"
                                    name="presupuesto_max"
                                    step="0.01"
                                    min="0"
                                    class="form-control form-control-sm"
                                    value="<?= $e($filtros['presupuesto_max']) ?>"
                                    placeholder="Máx."
                                >
                            </div>
                        </th>
                        <th>
                            <div class="d-flex gap-1">
                                <input
                                    type="date"
                                    name="fecha_desde"
                                    class="form-control form-control-sm"
                                    value="<?= $e($filtros['fecha_desde']) ?>"
                                    title="Desde"
                                >
                            </div>
                        </th>
<!--
                        <th>
                            <select name="estado" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach (array('BORRADOR', 'ACTIVA', 'CERRADA') as $estado): ?>
                                    <option value="<?= $estado ?>" <?= $filtros['estado'] === $estado ? 'selected' : '' ?>>
                                        <?= $estado ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </th>
-->
                        <th class="text-center text-muted">
                            <div class="d-flex gap-1">
                                <input
                                    type="date"
                                    name="fecha_hasta"
                                    class="form-control form-control-sm"
                                    value="<?= $e($filtros['fecha_hasta']) ?>"
                                    title="Hasta"
                                >
                            </div>
                        </th>
                        <th class="text-center text-muted"></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($ofertas->isEmpty()): ?>
                        <tr>
                            <td colspan="15" class="text-center text-muted py-4">
                                No se encontraron ofertas con los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ofertas as $oferta): ?>
                            <tr>
                                <td>
                                    <span class="font-monospace"><?= $e($oferta->consecutivo) ?></span>
                                </td>
                                <td><?= $e($oferta->objeto) ?></td>
                                <td title="<?= $e($oferta->descripcion) ?>">
                                    <?= $e(mb_strimwidth((string) $oferta->descripcion, 0, 70, '...', 'UTF-8')) ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($oferta->presupuesto !== null): ?>
                                        <?= $e($oferta->moneda) ?>
                                        <?= $e(number_format((float) $oferta->presupuesto, 2, ',', '.')) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $oferta->fecha_inicio ? $e(date('d/m/Y', strtotime($oferta->fecha_inicio))) : '—' ?>
                                </td>
                                <td>
                                    <?= $oferta->fecha_cierre ? $e(date('d/m/Y', strtotime($oferta->fecha_cierre))) : '—' ?>
                                </td>
<!--
                                <td>
                                    <?php if ($oferta->estado): ?>
                                        <span class="badge bg-<?= $claseEstado($oferta->estado) ?>">
                                            <?= $e($oferta->estado) ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
-->
                                <td>
                                    <div class="d-flex gap-1">
                                        <a
                                            href="index.php?accion=editar&id=<?= (int) $oferta->id ?>"
                                            class="btn btn-warning btn-sm"
                                            title="Editar oferta"
                                        >
                                            Editar
                                        </a>
                                        <a
                                            href="index.php?accion=detalle&id=<?= (int) $oferta->id ?>"
                                            class="btn btn-info btn-sm text-white"
                                            title="Ver detalle de la oferta"
                                        >
                                            Detalle
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 mb-5">
    <small class="text-muted">
        Mostrando <?= (int) $desde ?>–<?= (int) $hasta ?> de <?= (int) $total ?> ofertas
    </small>

    <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Paginación de ofertas">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= $pagina > 1 ? $e($urlBase . '&pagina=' . ($pagina - 1)) : '#' ?>"
                        aria-label="Página anterior"
                    >
                        Anterior
                    </a>
                </li>

                <?php
                $inicio = max(1, $pagina - 2);
                $fin = min($totalPaginas, $pagina + 2);
                ?>

                <?php if ($inicio > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $e($urlBase . '&pagina=1') ?>">1</a>
                    </li>
                    <?php if ($inicio > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($numeroPagina = $inicio; $numeroPagina <= $fin; $numeroPagina++): ?>
                    <li class="page-item <?= $numeroPagina === $pagina ? 'active' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= $e($urlBase . '&pagina=' . $numeroPagina) ?>"
                            <?= $numeroPagina === $pagina ? 'aria-current="page"' : '' ?>
                        >
                            <?= $numeroPagina ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($fin < $totalPaginas): ?>
                    <?php if ($fin < $totalPaginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $e($urlBase . '&pagina=' . $totalPaginas) ?>">
                            <?= $totalPaginas ?>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= $pagina < $totalPaginas ? $e($urlBase . '&pagina=' . ($pagina + 1)) : '#' ?>"
                        aria-label="Página siguiente"
                    >
                        Siguiente
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/pie.php'; ?>
