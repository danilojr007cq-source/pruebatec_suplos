<?php
// Archivo: Controlador/ReporteOfertasExcel.php
// Proposito: armar y descargar el reporte en Excel del listado de ofertas
// Se genera con PHP nativo y la extensión ZipArchive, sin librerias externas

class ReporteOfertasExcel
{
    // Genera el archivo y lo envia al navegador como descarga.
    public static function descargar($ofertas)
    {
        $ruta = self::crearArchivo($ofertas);
        $nombre = 'reporte_ofertas_' . date('Ymd_His') . '.xlsx';

        // Evita que cualquier salida previa dañe el archivo binario
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: public');

        readfile($ruta);
        @unlink($ruta);
        exit;
    }

    // Crea el XLSX en un archivo temporal y devuelve su ruta.
    private static function crearArchivo($ofertas)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('La extensión ZipArchive de PHP no está disponible.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'reporte_ofertas_');

        if ($ruta === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal del reporte.');
        }

        $zip = new ZipArchive();

        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($ruta);
            throw new RuntimeException('No fue posible crear el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', self::contenidoTipos());
        $zip->addFromString('_rels/.rels', self::relacionesRaiz());
        $zip->addFromString('xl/workbook.xml', self::libroTrabajo());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relacionesLibro());
        $zip->addFromString('xl/styles.xml', self::estilos());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::hoja($ofertas));
        $zip->close();

        return $ruta;
    }

    // Arma la hoja con el encabezado y una fila por oferta.
    private static function hoja($ofertas)
    {
        $encabezados = array(
            'Consecutivo',
            'Objeto',
            'Descripción',
            'Presupuesto',
            'Fecha inicio',
            'Fecha cierre'
        );

        $anchos = array(18, 45, 70, 24, 14, 14);
        $columnas = array('A', 'B', 'C', 'D', 'E', 'F');

        $filas = array();

        foreach ($ofertas as $oferta) {
            $filas[] = array(
                (string) $oferta->consecutivo,
                (string) $oferta->objeto,
                (string) $oferta->descripcion,
                self::formatearPresupuesto($oferta),
                self::formatearFecha($oferta->fecha_inicio),
                self::formatearFecha($oferta->fecha_cierre)
            );
        }

        $ultimaFila = count($filas) + 1;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $xml .= '<dimension ref="A1:F' . $ultimaFila . '"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0">';
        $xml .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
        $xml .= '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>';
        $xml .= '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<cols>';

        foreach ($anchos as $indice => $ancho) {
            $numero = $indice + 1;
            $xml .= '<col min="' . $numero . '" max="' . $numero . '" width="' . $ancho . '" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData>';

        // Fila de encabezados
        $xml .= '<row r="1" ht="24" customHeight="1">';

        foreach ($encabezados as $indice => $texto) {
            $xml .= self::celda($columnas[$indice] . '1', $texto, 1);
        }

        $xml .= '</row>';

        // Filas de datos
        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 2;
            $xml .= '<row r="' . $numeroFila . '">';

            foreach ($fila as $indiceColumna => $valor) {
                $xml .= self::celda($columnas[$indiceColumna] . $numeroFila, $valor, 2);
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $xml .= '<autoFilter ref="A1:F' . $ultimaFila . '"/>';
        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    // Escribe una celda con texto en linea
    private static function celda($referencia, $valor, $estilo)
    {
        return '<c r="' . $referencia . '" s="' . $estilo . '" t="inlineStr">'
            . '<is><t xml:space="preserve">' . self::escapar($valor) . '</t></is>'
            . '</c>';
    }

    // Escapa el texto para XML y quita caracteres de control no permitidos
    private static function escapar($valor)
    {
        $texto = (string) $valor;
        $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto);

        if ($limpio === null) {
            $limpio = $texto;
        }

        return htmlspecialchars($limpio, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // Muestra el presupuesto tal como aparece en el listado
    private static function formatearPresupuesto($oferta)
    {
        if ($oferta->presupuesto === null || $oferta->presupuesto === '') {
            return '—';
        }

        $moneda = trim((string) $oferta->moneda);
        $valor = number_format((float) $oferta->presupuesto, 2, ',', '.');

        return trim($moneda . ' ' . $valor);
    }

    // Fecha en el mismo formato del listado: dd/mm/aaaa
    private static function formatearFecha($fecha)
    {
        if (empty($fecha)) {
            return '—';
        }

        $timestamp = strtotime((string) $fecha);

        return $timestamp ? date('d/m/Y', $timestamp) : (string) $fecha;
    }

    // Partes fijas del paquete XLSX
    private static function contenidoTipos()
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private static function relacionesRaiz()
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function libroTrabajo()
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <bookViews>
    <workbookView xWindow="0" yWindow="0" windowWidth="16384" windowHeight="8192"/>
  </bookViews>
  <sheets>
    <sheet name="Ofertas" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private static function relacionesLibro()
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private static function estilos()
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/><family val="2"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFBFBFBF"/></left>
      <right style="thin"><color rgb="FFBFBFBF"/></right>
      <top style="thin"><color rgb="FFBFBFBF"/></top>
      <bottom style="thin"><color rgb="FFBFBFBF"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">
      <alignment vertical="top" wrapText="1"/>
    </xf>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
</styleSheet>
XML;
    }
}
