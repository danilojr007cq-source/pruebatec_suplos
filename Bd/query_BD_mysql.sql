-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 26-10-2026 a las 21:51:48
-- Versión del servidor: 10.4.24-MariaDB
-- Versión de PHP: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `crud_pruebatec`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades`
--

CREATE TABLE `actividades` (
  `id` int(11) NOT NULL,
  `codigo_segmento` int(11) DEFAULT NULL,
  `Segmento` varchar(200) DEFAULT NULL,
  `codigo_familia` int(11) DEFAULT NULL,
  `familia` varchar(200) DEFAULT NULL,
  `codigo_clase` int(11) DEFAULT NULL,
  `clase` varchar(200) DEFAULT NULL,
  `codigo_producto` int(11) DEFAULT NULL,
  `producto` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--

--
-- Estructura de tabla para la tabla `ofertas`
--

CREATE TABLE `ofertas` (
  `id` int(11) NOT NULL,
  `consecutivo` varchar(255) NOT NULL,
  `objeto` varchar(150) DEFAULT NULL,
  `descripcion` varchar(400) DEFAULT NULL,
  `moneda` varchar(3) DEFAULT NULL,
  `presupuesto` decimal(15,2) DEFAULT NULL,
  `actividad_id` int(11) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `fecha_cierre` date DEFAULT NULL,
  `hora_cierre` time DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `creado_en` datetime DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `ofertas`
--

INSERT INTO `ofertas` (`id`, `consecutivo`, `objeto`, `descripcion`, `moneda`, `presupuesto`, `actividad_id`, `fecha_inicio`, `hora_inicio`, `fecha_cierre`, `hora_cierre`, `estado`, `creado_en`, `actualizado_en`) VALUES
(14, 'O-014-26', 'licitación Danilo', 'Esto es una prueba', 'COP', '15000000.00', 289335, '2026-10-26', '07:13:00', '2026-10-26', '10:13:00', 'EN_EVALUACION', '2026-10-26 07:15:02', '2026-10-26 19:26:42'),
(28, 'O-028-26', 'Prueba estado temporal', 'Oferta temporal para validar el campo estado', 'COP', '1000000.00', 245112, '2026-10-25', '08:00:00', '2026-10-27', '18:00:00', 'PUBLICADA', '2026-10-26 19:27:22', '2026-10-26 19:30:38'),
(29, 'O-029-26', 'prueba', 'prueba', 'COP', '170000.00', 289335, '2026-10-26', '13:36:00', '2026-10-26', '17:39:00', 'EN_EVALUACION', '2026-10-26 19:35:51', '2026-10-26 19:35:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ofertas_documentos`
--

CREATE TABLE `ofertas_documentos` (
  `id` int(11) NOT NULL,
  `licitacion_id` int(11) NOT NULL,
  `titulo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ofertas_documentos`
--

INSERT INTO `ofertas_documentos` (`id`, `licitacion_id`, `titulo`, `descripcion`, `archivo`, `creado_en`) VALUES
(4, 14, 'prueba_danilo', 'esto es una prueba', 'documentos/oferta_14_20261026082458_Hoja_de_vida_Danilo_60a059d7.pdf', '2026-10-26 08:24:58'),
(5, 28, 'Documento temporal', 'Prueba', 'documentos/dummy.pdf', '2026-10-26 13:27:29');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `consecutivo` (`consecutivo`),
  ADD KEY `fk_oferta_actividad` (`actividad_id`);

--
-- Indices de la tabla `ofertas_documentos`
--
ALTER TABLE `ofertas_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_licitacion_ofertas` (`licitacion_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=294136;

--
-- AUTO_INCREMENT de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `ofertas_documentos`
--
ALTER TABLE `ofertas_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ofertas`
--
ALTER TABLE `ofertas`
  ADD CONSTRAINT `fk_oferta_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `ofertas_documentos`
--
ALTER TABLE `ofertas_documentos`
  ADD CONSTRAINT `fk_licitacion_ofertas` FOREIGN KEY (`licitacion_id`) REFERENCES `ofertas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
