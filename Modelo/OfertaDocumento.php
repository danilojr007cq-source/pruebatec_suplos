<?php
// Archivo: Modelo/OfertaDocumento.php
// Proposito: Modelo Eloquent para los documentos asociados a una oferta

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

    // Relacion: un documento pertenece a una oferta
    public function oferta()
    {
        return $this->belongsTo(Ofertas::class, 'licitacion_id', 'id');
    }
}
