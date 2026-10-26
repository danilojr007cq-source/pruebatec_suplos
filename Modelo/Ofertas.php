<?php
// Archivo: Modelo/Ofertas.php
// Proposito: Modelo Eloquent para la tabla ofertas
// Incluye relacion con la actividad, documentos y consultas para el listado con filtros y paginacion.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/Actividad.php';
require_once __DIR__ . '/OfertaDocumento.php';

Conexion::iniciar(); // Inicializa la conexion de Eloquent

use Illuminate\Database\Eloquent\Model;

class Ofertas extends Model
{
    // Nombre de la tabla
    protected $table = 'ofertas';

    // Clave primaria
    protected $primaryKey = 'id';

    // La tabla fechas operadas manualmente, no timestamps de Eloquent
    public $timestamps = false;

    // Campos asignables de forma masiva
    protected $fillable = [
        'consecutivo',
        'objeto',
        'descripcion',
        'moneda',
        'presupuesto',
        'actividad_id',
        'fecha_inicio',
        'hora_inicio',
        'fecha_cierre',
        'hora_cierre',
        'estado',
        'creado_en',
        'actualizado_en'
    ];

    // Estados disponibles del ciclo de vida de una oferta
    const ESTADO_ACTIVO = 'ACTIVO';
    const ESTADO_PUBLICADA = 'PUBLICADA';
    const ESTADO_EN_EVALUACION = 'EN_EVALUACION';

    // Relacion: una oferta pertenece a una actividad
    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'actividad_id', 'id');
    }

    // Relacion: una oferta puede tener varios documentos
    public function documentos()
    {
        return $this->hasMany(OfertaDocumento::class, 'licitacion_id', 'id');
    }

    // Obtiene todas las ofertas ordenadas por ID descendente
    public static function obtenerTodos()
    {
        return self::orderBy('id', 'desc')->get();
    }

    // Obtiene una oferta por su ID
    public static function obtenerPorId($id)
    {
        return self::find($id);
    }

    // Obtiene una oferta con su actividad y documentos para la vista de detalle
    public static function obtenerDetalle($id)
    {
        return self::with(array('actividad', 'documentos'))->find($id);
    }

    // Crea una oferta nueva
    public static function crear($datos)
    {
        $oferta = self::create($datos);

        // Extraemos el ID y el año actual en 2 dígitos
        $incremental = $oferta->id;
        $yy = date('y');

        // Formateamos para asegurar los 3 ceros
        $consecutivo = 'O-' . str_pad($incremental, 3, '0', STR_PAD_LEFT) . '-' . $yy;

        // Guardamos el consecutivo oficial en el mismo registro
        $oferta->consecutivo = $consecutivo;
        $oferta->save();

        return $oferta;
        //return self::create($datos);
    }

    // Actualiza una oferta existente
    public static function actualizarRegistro($id, $datos)
    {
        $oferta = self::find($id);
        if ($oferta) {
            $oferta->update($datos);
            return $oferta;
        }
        return false;
    }

    // Elimina una oferta
    public static function eliminarRegistro($id)
    {
        $oferta = self::find($id);
        if ($oferta) {
            return $oferta->delete();
        }
        return false;
    }

    // Avanza automaticamente el estado de las ofertas segun sus fechas.
    // - Activo -> Publicada cuando ya llego la fecha y hora de inicio.
    // - Activo/Publicada -> En evaluacion cuando ya llego la fecha y hora de cierre.
    // Tambien convierte los estados antiguos (BORRADOR, ACTIVA y CERRADA) al nuevo esquema.
    public static function actualizarEstadosAutomaticos()
    {
        $ahora = date('Y-m-d H:i:s');

        // Compatibilidad con los estados usados antes de este cambio
        self::whereNull('estado')->update(array('estado' => self::ESTADO_ACTIVO));
        self::where('estado', 'BORRADOR')->update(array('estado' => self::ESTADO_ACTIVO));
        self::where('estado', 'ACTIVA')->update(array('estado' => self::ESTADO_ACTIVO));
        self::where('estado', 'CERRADA')->update(array('estado' => self::ESTADO_EN_EVALUACION));

        // Una oferta activa pasa a publicada al cumplirse la fecha de inicio
        self::where('estado', self::ESTADO_ACTIVO)
            ->whereRaw("CONCAT(fecha_inicio, ' ', hora_inicio) <= ?", array($ahora))
            ->whereRaw("CONCAT(fecha_cierre, ' ', hora_cierre) > ?", array($ahora))
            ->update(array(
                'estado' => self::ESTADO_PUBLICADA,
                'actualizado_en' => $ahora
            ));

        // Al llegar la fecha de cierre la oferta queda en evaluacion
        self::whereIn('estado', array(self::ESTADO_ACTIVO, self::ESTADO_PUBLICADA))
            ->whereRaw("CONCAT(fecha_cierre, ' ', hora_cierre) <= ?", array($ahora))
            ->update(array(
                'estado' => self::ESTADO_EN_EVALUACION,
                'actualizado_en' => $ahora
            ));
    }

    // Genera un consecutivo único para la oferta, por ejemplo: OF-20261026-A1B2C3
    public static function generarConsecutivo()
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $consecutivo = 'OF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            if (!self::where('consecutivo', $consecutivo)->exists()) {
                return $consecutivo;
            }
        }

        throw new RuntimeException('No fue posible generar un consecutivo único para la oferta.');
    }

    // Lista ofertas con filtros y paginación.
    // Devuelve un arreglo con datos, total, página actual, por página y total de páginas.
    public static function listarPaginado($filtros, $pagina = 1, $porPagina = 10)
    {
        $consulta = self::query()
            ->leftJoin('actividades', 'actividades.id', '=', 'ofertas.actividad_id')
            ->select(array(
                'ofertas.*',
                'actividades.producto as actividad_producto',
                'actividades.codigo_producto as actividad_codigo_producto'
            ));

        // Filtro por ID exacto
        if (isset($filtros['id']) && $filtros['id'] !== '') {
            $consulta->where('ofertas.id', (int) $filtros['id']);
        }

        // Filtros de texto con LIKE
        if (!empty($filtros['consecutivo'])) {
            $consulta->where('ofertas.consecutivo', 'LIKE', self::patronLike($filtros['consecutivo']));
        }

        if (!empty($filtros['objeto'])) {
            $consulta->where('ofertas.objeto', 'LIKE', self::patronLike($filtros['objeto']));
        }

        if (!empty($filtros['descripcion'])) {
            $consulta->where('ofertas.descripcion', 'LIKE', self::patronLike($filtros['descripcion']));
        }

        if (!empty($filtros['actividad'])) {
            $consulta->where('actividades.producto', 'LIKE', self::patronLike($filtros['actividad']));
        }

        // Filtros exactos
        if (!empty($filtros['moneda'])) {
            $consulta->where('ofertas.moneda', $filtros['moneda']);
        }

        // Rango de presupuesto
        if (isset($filtros['presupuesto_min']) && $filtros['presupuesto_min'] !== '' && is_numeric($filtros['presupuesto_min'])) {
            $consulta->where('ofertas.presupuesto', '>=', (float) $filtros['presupuesto_min']);
        }

        if (isset($filtros['presupuesto_max']) && $filtros['presupuesto_max'] !== '' && is_numeric($filtros['presupuesto_max'])) {
            $consulta->where('ofertas.presupuesto', '<=', (float) $filtros['presupuesto_max']);
        }

        // Rango de fecha de inicio
        if (!empty($filtros['fecha_desde']) && self::esFechaValida($filtros['fecha_desde'])) {
            $consulta->where('ofertas.fecha_inicio', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta']) && self::esFechaValida($filtros['fecha_hasta'])) {
            $consulta->where('ofertas.fecha_inicio', '<=', $filtros['fecha_hasta']);
        }

        // Total de registros con los filtros aplicados
        $total = (clone $consulta)->count();

        // Normaliza la página y calcula el total de páginas
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, (int) $pagina), $totalPaginas);

        // Obtiene únicamente los registros de la página solicitada
        $datos = $consulta
            ->orderBy('ofertas.id', 'desc')
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return array(
            'datos' => $datos,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas
        );
    }

    // Devuelve todas las ofertas que cumplen los filtros, sin paginar.
    // Se usa para el reporte en Excel. Reutiliza la consulta del listado para
    // que los filtros se comporten igual, pero pide una sola página con el total.
    public static function listarTodos($filtros)
    {
        $total = self::listarPaginado($filtros, 1, 1)['total'];
        $resultado = self::listarPaginado($filtros, 1, max(1, $total));

        return $resultado['datos'];
    }

    // Construye un patrón LIKE escapando los comodines
    private static function patronLike($valor)
    {
        $valor = str_replace(
            array('\\', '%', '_'),
            array('\\\\', '\\%', '\\_'),
            (string) $valor
        );

        return '%' . $valor . '%';
    }

    // Valida que una cadena tenga formato de fecha YYYY-MM-DD
    private static function esFechaValida($fecha)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
            return false;
        }

        $partes = explode('-', $fecha);
        return checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]);
    }
}
