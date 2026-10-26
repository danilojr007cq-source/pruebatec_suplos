<?php
// Archivo: Vista/detalle.php
// Vista de detalle consolidado de una oferta/licitación.
// Muestra información general, actividad UNSPSC, cronograma, documentos y auditoría
// en pestañas de Bootstrap.

include __DIR__ . '/partials/encabezado.php';

$mensaje = isset($_GET['mensaje']) && is_string($_GET['mensaje']) ? $_GET['mensaje'] : '';

// Escapa un valor para mostrarlo en HTML
$e = function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

// Clase Bootstrap para el badge del estado
$claseEstado = function ($estado) {
    $clases = array(
        'ACTIVO' => 'success',
        'PUBLICADA' => 'primary',
        'EN_EVALUACION' => 'warning text-dark'
    );

    return $clases[$estado] ?? 'light text-dark';
};

// Texto amigable del estado
$etiquetaEstado = function ($estado) {
    $etiquetas = array(
        'ACTIVO' => 'Activo',
        'PUBLICADA' => 'Publicada',
        'EN_EVALUACION' => 'En evaluación'
    );

    return $etiquetas[$estado] ?? (string) $estado;
};

// Une código y nombre de una categoría de la actividad
$valorActividad = function ($codigo, $nombre) {
    $codigo = trim((string) $codigo);
    $nombre = trim((string) $nombre);

    if ($codigo === '' && $nombre === '') {
        return '—';
    }

    if ($codigo === '') {
        return $nombre;
    }

    if ($nombre === '') {
        return $codigo;
    }

    return $codigo . ' - ' . $nombre;
};

$actividad = $oferta->actividad;
$documentos = $oferta->documentos;

// Calcula la duración entre el inicio y el cierre
$duracion = '—';
if ($oferta->fecha_inicio && $oferta->hora_inicio && $oferta->fecha_cierre && $oferta->hora_cierre) {
    try {
        $fechaHoraInicio = new DateTime($oferta->fecha_inicio . ' ' . $oferta->hora_inicio);
        $fechaHoraCierre = new DateTime($oferta->fecha_cierre . ' ' . $oferta->hora_cierre);

        if ($fechaHoraCierre > $fechaHoraInicio) {
            $intervalo = $fechaHoraInicio->diff($fechaHoraCierre);
            $partes = array();

            if ($intervalo->days > 0) {
                $partes[] = $intervalo->days . ' día(s)';
            }
            if ($intervalo->h > 0) {
                $partes[] = $intervalo->h . ' hora(s)';
            }
            if ($intervalo->i > 0) {
                $partes[] = $intervalo->i . ' minuto(s)';
            }

            $duracion = !empty($partes) ? implode(', ', $partes) : 'Menos de un minuto';
        }
    } catch (Exception $errorDuracion) {
        $duracion = '—';
    }
}
?>

<?php if ($mensaje === 'noeditable'): ?>
    <div class="alert alert-warning">
        Esta oferta ya no está en estado <strong>Activo</strong>, así que no se puede editar.
        Solo las ofertas activas admiten cambios.
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <p class="text-muted mb-1">Detalle de la licitación</p>
        <h2 class="mb-1"><?= $e($oferta->objeto) ?></h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="font-monospace text-muted"><?= $e($oferta->consecutivo) ?></span>
            <?php if ($oferta->estado): ?>
                <span class="badge bg-<?= $claseEstado($oferta->estado) ?>"><?= $e($etiquetaEstado($oferta->estado)) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="index.php?accion=listar" class="btn btn-outline-secondary">Volver al listado</a>
        <?php if ($oferta->estado === 'ACTIVO'): ?>
            <a href="index.php?accion=editar&id=<?= (int) $oferta->id ?>" class="btn btn-warning">Editar oferta</a>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-tabs" id="tabsOferta" role="tablist">
    <li class="nav-item" role="presentation">
        <button
            class="nav-link active"
            id="tab-general-tab"
            data-bs-toggle="tab"
            data-bs-target="#tab-general"
            type="button"
            role="tab"
            aria-controls="tab-general"
            aria-selected="true"
        >
            General
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="tab-actividad-tab"
            data-bs-toggle="tab"
            data-bs-target="#tab-actividad"
            type="button"
            role="tab"
            aria-controls="tab-actividad"
            aria-selected="false"
        >
            Actividad
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="tab-cronograma-tab"
            data-bs-toggle="tab"
            data-bs-target="#tab-cronograma"
            type="button"
            role="tab"
            aria-controls="tab-cronograma"
            aria-selected="false"
        >
            Cronograma
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="tab-documentos-tab"
            data-bs-toggle="tab"
            data-bs-target="#tab-documentos"
            type="button"
            role="tab"
            aria-controls="tab-documentos"
            aria-selected="false"
        >
            Documentos
            <span class="badge bg-secondary ms-1"><?= $documentos->count() ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="tab-auditoria-tab"
            data-bs-toggle="tab"
            data-bs-target="#tab-auditoria"
            type="button"
            role="tab"
            aria-controls="tab-auditoria"
            aria-selected="false"
        >
            Auditoría
        </button>
    </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-white shadow-sm p-3" id="tabsOfertaContent">
    <!-- Pestaña General -->
    <div
        class="tab-pane fade show active"
        id="tab-general"
        role="tabpanel"
        aria-labelledby="tab-general-tab"
        tabindex="0"
    >
        <h5 class="mb-3">Información general</h5>
        <dl class="row mb-0">
            <dt class="col-sm-3">ID</dt>
            <dd class="col-sm-9"><?= (int) $oferta->id ?></dd>

            <dt class="col-sm-3">Consecutivo</dt>
            <dd class="col-sm-9"><span class="font-monospace"><?= $e($oferta->consecutivo) ?></span></dd>

            <dt class="col-sm-3">Objeto</dt>
            <dd class="col-sm-9"><?= $e($oferta->objeto) ?></dd>

            <dt class="col-sm-3">Descripción</dt>
            <dd class="col-sm-9"><?= nl2br($e($oferta->descripcion)) ?></dd>

            <dt class="col-sm-3">Moneda</dt>
            <dd class="col-sm-9"><?= $e($oferta->moneda ?: '—') ?></dd>

            <dt class="col-sm-3">Presupuesto</dt>
            <dd class="col-sm-9">
                <?php if ($oferta->presupuesto !== null): ?>
                    <?= $e($oferta->moneda) ?>
                    <?= $e(number_format((float) $oferta->presupuesto, 2, ',', '.')) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>

            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">
                <?php if ($oferta->estado): ?>
                    <span class="badge bg-<?= $claseEstado($oferta->estado) ?>"><?= $e($etiquetaEstado($oferta->estado)) ?></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>
        </dl>
    </div>

    <!-- Pestaña Actividad -->
    <div
        class="tab-pane fade"
        id="tab-actividad"
        role="tabpanel"
        aria-labelledby="tab-actividad-tab"
        tabindex="0"
    >
        <h5 class="mb-3">Actividad asociada (UNSPSC)</h5>

        <?php if (!$actividad): ?>
            <div class="alert alert-info mb-0">Esta oferta no tiene una actividad asociada.</div>
        <?php else: ?>
            <dl class="row mb-0">
                <dt class="col-sm-3">ID actividad</dt>
                <dd class="col-sm-9"><?= (int) $actividad->id ?></dd>

                <dt class="col-sm-3">Segmento</dt>
                <dd class="col-sm-9"><?= $e($valorActividad($actividad->codigo_segmento, $actividad->Segmento)) ?></dd>

                <dt class="col-sm-3">Familia</dt>
                <dd class="col-sm-9"><?= $e($valorActividad($actividad->codigo_familia, $actividad->familia)) ?></dd>

                <dt class="col-sm-3">Clase</dt>
                <dd class="col-sm-9"><?= $e($valorActividad($actividad->codigo_clase, $actividad->clase)) ?></dd>

                <dt class="col-sm-3">Producto</dt>
                <dd class="col-sm-9"><?= $e($valorActividad($actividad->codigo_producto, $actividad->producto)) ?></dd>
            </dl>
        <?php endif; ?>
    </div>

    <!-- Pestaña Cronograma -->
    <div
        class="tab-pane fade"
        id="tab-cronograma"
        role="tabpanel"
        aria-labelledby="tab-cronograma-tab"
        tabindex="0"
    >
        <h5 class="mb-3">Cronograma</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100 border-success">
                    <div class="card-header bg-success-subtle">Inicio</div>
                    <div class="card-body">
                        <p class="mb-1">
                            <strong>Fecha:</strong>
                            <?= $oferta->fecha_inicio ? $e(date('d/m/Y', strtotime($oferta->fecha_inicio))) : '—' ?>
                        </p>
                        <p class="mb-0">
                            <strong>Hora:</strong>
                            <?= $oferta->hora_inicio ? $e(substr($oferta->hora_inicio, 0, 5)) : '—' ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 border-danger">
                    <div class="card-header bg-danger-subtle">Cierre</div>
                    <div class="card-body">
                        <p class="mb-1">
                            <strong>Fecha:</strong>
                            <?= $oferta->fecha_cierre ? $e(date('d/m/Y', strtotime($oferta->fecha_cierre))) : '—' ?>
                        </p>
                        <p class="mb-0">
                            <strong>Hora:</strong>
                            <?= $oferta->hora_cierre ? $e(substr($oferta->hora_cierre, 0, 5)) : '—' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <dl class="row mb-0">
            <dt class="col-sm-3">Duración</dt>
            <dd class="col-sm-9"><?= $e($duracion) ?></dd>
        </dl>
    </div>

    <!-- Pestaña Documentos -->
    <div
        class="tab-pane fade"
        id="tab-documentos"
        role="tabpanel"
        aria-labelledby="tab-documentos-tab"
        tabindex="0"
    >
        <h5 class="mb-3">Documentos de la licitación</h5>

        <?php if ($documentos->isEmpty()): ?>
            <div class="alert alert-info mb-0">Esta oferta todavía no tiene documentos registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Título</th>
                            <th>Descripción</th>
                            <th>Archivo</th>
                            <th style="min-width: 140px;">Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentos as $documento): ?>
                            <tr>
                                <td><?= (int) $documento->id ?></td>
                                <td><?= $e($documento->titulo) ?></td>
                                <td><?= $e($documento->descripcion) ?></td>
                                <td>
                                    <?php if (preg_match('~^https?://~i', (string) $documento->archivo)): ?>
                                        <a href="<?= $e($documento->archivo) ?>" target="_blank" rel="noopener noreferrer">
                                            Abrir archivo
                                        </a>
                                    <?php else: ?>
                                        <span class="font-monospace"><?= $e($documento->archivo) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $documento->creado_en ? $e(date('d/m/Y H:i', strtotime($documento->creado_en))) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pestaña Auditoría -->
    <div
        class="tab-pane fade"
        id="tab-auditoria"
        role="tabpanel"
        aria-labelledby="tab-auditoria-tab"
        tabindex="0"
    >
        <h5 class="mb-3">Auditoría del registro</h5>
        <dl class="row mb-0">
            <dt class="col-sm-3">ID</dt>
            <dd class="col-sm-9"><?= (int) $oferta->id ?></dd>

            <dt class="col-sm-3">Consecutivo</dt>
            <dd class="col-sm-9"><span class="font-monospace"><?= $e($oferta->consecutivo) ?></span></dd>

            <dt class="col-sm-3">Creado en</dt>
            <dd class="col-sm-9">
                <?= $oferta->creado_en ? $e(date('d/m/Y H:i:s', strtotime($oferta->creado_en))) : '—' ?>
            </dd>

            <dt class="col-sm-3">Actualizado en</dt>
            <dd class="col-sm-9">
                <?= $oferta->actualizado_en ? $e(date('d/m/Y H:i:s', strtotime($oferta->actualizado_en))) : '—' ?>
            </dd>

            <dt class="col-sm-3">Estado actual</dt>
            <dd class="col-sm-9">
                <?php if ($oferta->estado): ?>
                    <span class="badge bg-<?= $claseEstado($oferta->estado) ?>"><?= $e($etiquetaEstado($oferta->estado)) ?></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </dd>
        </dl>
    </div>
</div>

<?php include __DIR__ . '/partials/pie.php'; ?>
