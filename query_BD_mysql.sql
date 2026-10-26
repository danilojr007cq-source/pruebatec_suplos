CREATE DATABASE crud_pruebatec;

CREATE TABLE actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_segmento INT,
    Segmento VARCHAR(200),
    codigo_familia INT,
    familia VARCHAR(200),
    codigo_clase INT,
    clase VARCHAR(200),
    codigo_producto INT,
    producto VARCHAR(200)
);

CREATE TABLE ofertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consecutivo VARCHAR(255) UNIQUE NOT NULL,
    objeto VARCHAR(150),
    descripcion VARCHAR(400),
    moneda VARCHAR(3),
    presupuesto DECIMAL(15,2),
    actividad_id INT,
    fecha_inicio DATE,
    hora_inicio TIME,
    fecha_cierre DATE,
    hora_cierre TIME,
    estado VARCHAR(20),
    creado_en DATETIME,
    actualizado_en DATETIME,
    CONSTRAINT fk_oferta_actividad FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
);

CREATE TABLE ofertas_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    licitacion_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NULL,
    archivo VARCHAR(255) NOT NULL, -- O TEXT, ideal para almacenar la ruta del archivo
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_licitacion_ofertas FOREIGN KEY (licitacion_id) REFERENCES ofertas(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;