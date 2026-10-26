<?php
// Archivo: Modelo/Actividad.php
// Propósito: Modelo Eloquent para la tabla 'actividades' usando el mismo patrón que Producto.php
// Se encarga de inicializar la conexión mediante Config/Conexion.php e implementar métodos CRUD básicos

require_once __DIR__ . '/../vendor/autoload.php'; // Carga las dependencias de Composer (incluye Eloquent)
require_once __DIR__ . '/../Config/Conexion.php'; // Carga la configuración de conexión a base de datos

Conexion::iniciar(); // Inicializa la conexión global de Eloquent

use Illuminate\Database\Eloquent\Model; // Importa la clase base de Eloquent

class Actividad extends Model
{
    // Especifica el nombre de la tabla a la que se vincula este modelo
    protected $table = 'actividades';
    
    // Especifica la clave primaria de la tabla
    protected $primaryKey = 'id';
    
    // Indica que la tabla no tiene campos timestamps (created_at/updated_at)
    public $timestamps = false;
    
    // Campos que se pueden asignar masivamente al crear/actualizar registros
    protected $fillable = [
        'codigo_segmento',
        'Segmento',
        'codigo_familia',
        'familia',
        'codigo_clase',
        'clase',
        'codigo_producto',
        'producto'
    ];
    
    // Método para obtener todos los registros ordenados por ID descendente
    public static function obtenerTodos()
    {
        // Retorna todos los registros de actividades ordenados por id desc
        return self::orderBy('id', 'desc')->get();
    }
    
    // Método para obtener un registro por su ID
    public static function obtenerPorId($id)
    {
        // Busca y retorna el registro con el ID especificado
        return self::find($id);
    }
    
    //Metodo para obtener las actividades para la busqueda de la lista desplegable en el formulario
    public static function obtenerParaLista($termino, $limite){
        return self::query()
        ->select('id', 'producto', 'codigo_producto', 'clase')
        ->where('producto', 'LIKE', '%' . $termino . '%')
        ->orderBy('producto', 'asc')
        ->limit($limite)
        ->get();
    }
    
    // Método para crear un nuevo registro
    public static function crear($datos)
    {
        // Crea y retorna un nuevo registro con los datos proporcionados
        return self::create($datos);
    }
    
    // Método para actualizar un registro existente
    public static function actualizarRegistro($id, $datos)
    {
        // Busca el registro por ID
        $actividad = self::find($id);
        // Si el registro existe, lo actualiza con los datos proporcionados
        if ($actividad) {
            $actividad->update($datos);
            return $actividad;
        }
        // Si no existe, retorna false
        return false;
    }
    
    // Método para eliminar un registro existente
    public static function eliminarRegistro($id)
    {
        // Busca el registro por ID
        $actividad = self::find($id);
        // Si el registro existe, lo elimina
        if ($actividad) {
            return $actividad->delete();
        }
        // Si no existe, retorna false
        return false;
    }
    
    // Método para insertar múltiples registros de forma masiva (útil para el cargue CSV)
    public static function insertarMasivo($arrayDatos)
    {
        // Usa insert de Eloquent para insertar múltiples registros a la vez
        // Nota: Este método no activa eventos de Eloquent ni timestamps automáticos
        return self::insert($arrayDatos);
    }
}
