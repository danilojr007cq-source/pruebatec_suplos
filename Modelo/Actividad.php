<?php
// Archivo: Modelo/Actividad.php
// Proposito: Modelo Eloquent para la tabla actividades, se encarga de inicializar la conexion mediante Config/Conexion.php e implementar metodos CRUD

require_once __DIR__ . '/../vendor/autoload.php'; // Carga las dependencias de Composer (incluye Eloquent)
require_once __DIR__ . '/../Config/Conexion.php'; // Carga la configuración de conexión a base de datos

Conexion::iniciar(); // Inicializa la conexion global de Eloquent

use Illuminate\Database\Eloquent\Model; // Importa la clase base de Eloquent

class Actividad extends Model
{
    // Especifica el nombre de la tabla a la que se vincula este modelo
    protected $table = 'actividades';
    
    // Especifica la clave primaria de la tabla
    protected $primaryKey = 'id';
    
    // Indica que la tabla no tiene campos timestamps
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
    
    // Metodo para obtener todos los registros ordenados por ID descendente
    public static function obtenerTodos()
    {
        // Retorna todos los registros de actividades ordenados descendentes
        return self::orderBy('id', 'desc')->get();
    }
    
    // Metodo para obtener un registro por su ID
    public static function obtenerPorId($id)
    {
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
    
    // Metodo para crear un nuevo registro
    public static function crear($datos)
    {
        return self::create($datos);
    }
    
    // Metodo para actualizar un registro existente
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
    
    // Metodo para insertar multiples registros de forma masiva para la carga de las actividades
    public static function insertarMasivo($arrayDatos)
    {
        // Usa insert de Eloquent para insertar multiples registros a la vez
        return self::insert($arrayDatos);
    }
}
