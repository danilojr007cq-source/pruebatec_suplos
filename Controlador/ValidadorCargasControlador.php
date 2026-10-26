<?php
// Archivo: Controlador/ValidadorCargasControlador.php
// Proposito: Controlador para la carga en la tabla de actividades
// Toma el archivo CSV de la raiz del proyecto (csv_suplos_temp.csv), valida, procesa e inserta los datos en la tabla actividades usando el modelo Actividad.

require_once __DIR__ . '/../Modelo/Actividad.php'; // Carga el modelo Actividad

class ValidadorCargasControlador
{
    // Ruta del archivo CSV
    private $rutaCsv = __DIR__ . '/../csv_suplos_temp.csv';

    // Metodo para mostrar la vista principal del validador de cargas
    public function mostrar()
    {
        // Antes de mostrar la vista, valida si la tabla actividades ya tiene registros
        $cargaPrevia = false;
        $totalActividades = 0;
        $errorValidacion = null;

        try {
            $totalActividades = Actividad::count();
            $cargaPrevia = $totalActividades > 0;
        } catch (Exception $e) {
            $errorValidacion = 'No se pudo consultar la tabla actividades: ' . $e->getMessage();
        }

        // Incluye la vista del validador de cargas y le pasa el estado de la carga
        include __DIR__ . '/../Vista/validador_cargas.php';
    }

    // Método para buscar actividades en formato JSON.
    // Recibe ?q=texto, filtra con LIKE y devuelve como máximo 15 resultados.
    // Se usa para el autocompletado del campo "actividad" de la vista de crear oferta.
    public function listarActividades()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        // Término de búsqueda enviado por el autocompletado
        $q = trim(isset($_GET['q']) ? (string) $_GET['q'] : '');
        $limite = 15; // Máximo de resultados para no saturar la vista

        // Si no hay término de búsqueda, no se consulta la base de datos
        if ($q === '') {
            echo json_encode(array(
                'success' => true,
                'total' => 0,
                'actividades' => array()
            ), JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Escapa los comodines de LIKE para que se busquen como texto literal
            $termino = str_replace(
                array('\\', '%', '_'),
                array('\\\\', '\\%', '\\_'),
                $q
            );

            // Consulta y filtra por nombre de producto y limita los resultados
            $actividades=  Actividad::obtenerParaLista($termino, $limite);

            echo json_encode(array(
                'success' => true,
                'total' => $actividades->count(),
                'actividades' => $actividades
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array(
                'success' => false,
                'mensaje' => 'Error al consultar las actividades: ' . $e->getMessage(),
                'actividades' => array()
            ), JSON_UNESCAPED_UNICODE);
        }
    }

    // Método para procesar el archivo CSV
    // Responde en formato NDJSON (una linea JSON por evento) para reportar el progreso real de la insercion mientras se ejecuta.
    public function procesar()
    {
        // Desactiva la limitación de tiempo de ejecución para archivos grandes
        set_time_limit(0);

        // Desactiva la compresion/salida en buffer para enviar el progreso en tiempo real
        while (ob_get_level()) {
            @ob_end_clean();
        }
        error_reporting(0);
        ini_set('display_errors', 0);
        header('Content-Type: application/x-ndjson; charset=utf-8'); //este header nos ayuda a que se pueda procesar evento por evento
        header('Cache-Control: no-cache, must-revalidate'); // este evita que el navegador reutilicen una respuesta anterior de este proceso
        header('X-Accel-Buffering: no'); // Evita el buffering en Nginx si aplica

        // Verifica nuevamente si ya existen registros para no volver a cargar los datos
        try {
            $existentes = Actividad::count();
        } catch (Exception $e) {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => false,
                'mensaje' => 'Error al consultar la tabla actividades: ' . $e->getMessage()
            ));
            return;
        }
        
        //Respuesta por si ya la tabla de actividades tiene datos
        if ($existentes > 0) {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => true,
                'ya_cargado' => true,
                'mensaje' => 'La carga de los datos ya se había realizado previamente. No se insertaron registros nuevos.',
                'total_registros' => 0,
                'registros_insertados' => 0,
                'total_actividades' => $existentes
            ));
            return;
        }

        // Valida que el archivo exista en la raíz del proyecto y que se pueda leer
        if (!is_file($this->rutaCsv) || !is_readable($this->rutaCsv)) {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => false,
                'mensaje' => 'No se encontró el archivo csv_suplos_temp.csv en la raíz del proyecto o no se puede leer.'
            ));
            return;
        }

        // Lee el archivo CSV separado por ';'
        $lineas = array();
        if (($handle = fopen($this->rutaCsv, 'r')) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                // Omite líneas vacías
                $tieneDatos = false;
                foreach ($data as $valor) {
                    if (trim($valor) !== '') {
                        $tieneDatos = true;
                        break;
                    }
                }
                if (!$tieneDatos) {
                    continue;
                }
                $lineas[] = $data;
            }
            fclose($handle);
        } else {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => false,
                'mensaje' => 'No se pudo abrir el archivo CSV.'
            ));
            return;
        }

        // Si el archivo está vacío
        if (count($lineas) == 0) {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => false,
                'mensaje' => 'El archivo CSV está vacío.'
            ));
            return;
        }

        // Detecta y omite el encabezado del archivo
        $primeraLinea = $lineas[0];
        $esEncabezado = false;
        if (isset($primeraLinea[0]) && !is_numeric(trim($primeraLinea[0]))) {
            $textoPrimera = strtolower(implode(' ', $primeraLinea));
            if (strpos($textoPrimera, 'codigo') !== false ||
                strpos($textoPrimera, 'segmento') !== false ||
                strpos($textoPrimera, 'familia') !== false ||
                strpos($textoPrimera, 'clase') !== false ||
                strpos($textoPrimera, 'producto') !== false) {
                $esEncabezado = true;
            }
        }

        if ($esEncabezado && count($lineas) > 0) {
            array_shift($lineas);
        }

        // Prepara los datos para la insertaros masivamente
        $datosInsertar = array();
        foreach ($lineas as $fila) {
            //valido con 8 que son el numero de filas que debe tener el archivo
            if (count($fila) >= 8) {
                $codigo_segmento = (isset($fila[0]) && trim($fila[0]) !== '') ? intval(trim($fila[0])) : null;
                $codigo_familia = (isset($fila[2]) && trim($fila[2]) !== '') ? intval(trim($fila[2])) : null;
                $codigo_clase = (isset($fila[4]) && trim($fila[4]) !== '') ? intval(trim($fila[4])) : null;
                $codigo_producto = (isset($fila[6]) && trim($fila[6]) !== '') ? intval(trim($fila[6])) : null;

                $datosInsertar[] = array(
                    'codigo_segmento' => $codigo_segmento,
                    'Segmento' => isset($fila[1]) ? trim($fila[1]) : '',
                    'codigo_familia' => $codigo_familia,
                    'familia' => isset($fila[3]) ? trim($fila[3]) : '',
                    'codigo_clase' => $codigo_clase,
                    'clase' => isset($fila[5]) ? trim($fila[5]) : '',
                    'codigo_producto' => $codigo_producto,
                    'producto' => isset($fila[7]) ? trim($fila[7]) : ''
                );
            }
        }

        $totalRegistros = count($datosInsertar);

        if ($totalRegistros == 0) {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => false,
                'mensaje' => 'No se encontraron registros válidos para insertar. Verifique el formato del archivo CSV.'
            ));
            return;
        }

        // Notifico a la vista el inicio del proceso con el total de registros detectados
        $this->emitir(array(
            'tipo' => 'inicio',
            'total_registros' => $totalRegistros
        ));

        try {
            set_time_limit(0);
            // Inserción masiva por chunks, reportando el progreso después de cada uno
            $chunks = array_chunk($datosInsertar, 1000);
            $insertados = 0;

            foreach ($chunks as $chunk) {
                Actividad::insertarMasivo($chunk);
                $insertados += count($chunk);

                $this->emitir(array(
                    'tipo' => 'progreso',
                    'total_registros' => $totalRegistros,
                    'registros_insertados' => $insertados,
                    'porcentaje' => (int) floor(($insertados / $totalRegistros) * 100)
                ));
            }

            $this->emitir(array(
                'tipo' => 'fin',
                'success' => true,
                'mensaje' => 'Archivo procesado correctamente. Los datos fueron cargados en la tabla actividades.',
                'total_registros' => $totalRegistros,
                'registros_insertados' => $insertados,
                'total_actividades' => $insertados
            ));
        } catch (Exception $e) {
            $this->emitir(array(
                'tipo' => 'fin',
                'success' => false,
                'mensaje' => 'Error al insertar los registros en la base de datos: ' . $e->getMessage(),
                'total_registros' => $totalRegistros,
                'registros_insertados' => $insertados
            ));
        }
    }

    // Envía un evento JSON en una línea y fuerza el envío inmediato al navegador
    private function emitir($datos)
    {
        echo json_encode($datos, JSON_UNESCAPED_UNICODE) . "\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }
}
