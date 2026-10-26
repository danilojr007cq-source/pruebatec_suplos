<?php
// Archivo: Modelo/OfertaDocumento.php
// Propósito: Modelo Eloquent para los documentos asociados a una oferta/licitación.
// Se utiliza en la vista de detalle para consolidar la información relacionada.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Conexion.php';

Conexion::iniciar();

use Illuminate\Database\Eloquent\Model;

class OfertaDocumento extends Model
{
    protected $table = 'ofertas_documentos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'licitacion_id',
        'titulo',
        'descripcion',
        'archivo',
        'creado_en'
    ];

    // Relación: un documento pertenece a una oferta/licitación
    public function oferta()
    {
        return $this->belongsTo(Ofertas::class, 'licitacion_id', 'id');
    }
}
