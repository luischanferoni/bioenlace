-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 31-07-2026 a las 15:51:46
-- Versión del servidor: 11.8.8-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u257309594_bioenlace`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id_servicio` int(10) UNSIGNED NOT NULL COMMENT 'Codigo de servicio',
  `nombre` varchar(40) DEFAULT NULL COMMENT 'Nombre del serivicio',
  `parametros` text NOT NULL,
  `item_name` varchar(256) NOT NULL,
  `acepta_turnos` enum('SI','NO') NOT NULL,
  `acepta_practicas` enum('SI','NO') NOT NULL,
  `hallazgos_ecl` text DEFAULT NULL,
  `medicamentos_ecl` text DEFAULT NULL,
  `procedimientos_ecl` text DEFAULT NULL,
  `verificacion_sisa` enum('SI','NO') NOT NULL DEFAULT 'NO',
  `profesion_snomed` char(25) DEFAULT NULL,
  `teleconsulta_politica` enum('NINGUNA','TODAS','ALGUNAS','') NOT NULL DEFAULT 'NINGUNA',
  `reserva_autogestion_paciente` enum('SI','NO','','') NOT NULL DEFAULT 'NO' COMMENT 'SI = paciente puede reservar turno directo (hub clínica)',
  `tipo` enum('consulta','diagnostico','laboratorio','procedimiento','soporte') NOT NULL DEFAULT 'consulta',
  `specialty_code` varchar(64) DEFAULT NULL,
  `specialty_system` varchar(128) DEFAULT NULL,
  `oferta_modelo` varchar(32) NOT NULL DEFAULT 'institucional'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci COMMENT='Oferta asistencial del establecimiento (HealthcareService). No es la especialidad del titulo del profesional ni un acto/practica SNOMED. PES (profesional_efector_servicio) asigna un profesional a este servicio en un efector. Ver docs/producto/glosario-servicio-pes-acto.md';

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id_servicio`, `nombre`, `parametros`, `item_name`, `acepta_turnos`, `acepta_practicas`, `hallazgos_ecl`, `medicamentos_ecl`, `procedimientos_ecl`, `verificacion_sisa`, `profesion_snomed`, `teleconsulta_politica`, `reserva_autogestion_paciente`, `tipo`, `specialty_code`, `specialty_system`, `oferta_modelo`) VALUES
(1, 'PSICOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394587001', 'http://snomed.info/sct', 'institucional'),
(2, 'ODONTOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#2bb585\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394812008', 'http://snomed.info/sct', 'institucional'),
(3, 'PEDIATRIA', 'a:1:{s:5:\"color\";s:7:\"#ff8040\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394537008', 'http://snomed.info/sct', 'institucional'),
(4, 'GINECOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#00a65a\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394585009', 'http://snomed.info/sct', 'institucional'),
(5, 'OBSTETRICIA', 'a:1:{s:5:\"color\";s:7:\"#f39c12\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394585009', 'http://snomed.info/sct', 'institucional'),
(6, 'MED FAMILIAR', 'a:1:{s:5:\"color\";s:7:\"#dd4b39\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'SI', 'consulta', '419772000', 'http://snomed.info/sct', 'institucional'),
(7, 'MED GENERAL', 'a:1:{s:5:\"color\";s:7:\"#f47578\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'TODAS', 'SI', 'consulta', '394814009', 'http://snomed.info/sct', 'institucional'),
(8, 'MED CLINICA', 'a:1:{s:5:\"color\";s:7:\"#bd4729\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'SI', 'consulta', '394807007', 'http://snomed.info/sct', 'institucional'),
(10, 'KINESIOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#74b368\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394602003', 'http://snomed.info/sct', 'institucional'),
(11, 'RADIOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#2e08fe\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'diagnostico', '394914008', 'http://snomed.info/sct', 'institucional'),
(12, 'SIA(SERVICIO DE INTERNACION ABREVIADO)', 'a:1:{s:5:\"color\";s:7:\"#c98204\";}', '', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(13, 'NEUROLOGIA', 'a:1:{s:5:\"color\";s:7:\"#5aa35f\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394591006', 'http://snomed.info/sct', 'institucional'),
(14, 'LABORATORIO', 'a:1:{s:5:\"color\";s:7:\"#a87b97\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'laboratorio', '261904005', 'http://snomed.info/sct', 'institucional'),
(15, 'CARDIOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#600103\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394579002', 'http://snomed.info/sct', 'institucional'),
(16, 'NUTRICION', 'a:1:{s:5:\"color\";s:7:\"#b85d9e\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '722164000', 'http://snomed.info/sct', 'institucional'),
(17, 'ECOGRAFIA', 'a:1:{s:5:\"color\";s:7:\"#b85d9e\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'diagnostico', '394914008', 'http://snomed.info/sct', 'legacy_acto'),
(18, 'INMUNOLOGIA CLINICA Y ALERGOLOGIA', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(19, 'MAMOGRAFIA', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'diagnostico', '394914008', 'http://snomed.info/sct', 'legacy_acto'),
(20, 'ALERGISTA', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(21, 'PSICOPEDAGOGA', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(22, 'GASTROENTEROLOGIA', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(23, 'OFTALMOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#8904b1\";}', 'Medico', 'SI', 'SI', '< 404684003 | hallazgo clínico (hallazgo) |: 363698007 | sitio del hallazgo (atributo) | = 81745001 | estructura del ojo (estructura corporal) |', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394594003', 'http://snomed.info/sct', 'institucional'),
(24, 'ENDOCRINOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#5aa35f\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(25, 'APS', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', 'enfermeria', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394814009', 'http://snomed.info/sct', 'institucional'),
(26, 'ADMINISTRACION', '', 'Administrativo', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'soporte', NULL, NULL, 'institucional'),
(27, 'ENFERMERIA', 'a:1:{s:5:\"color\";s:7:\"#ede212\";}', 'enfermeria', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '722142008', 'http://snomed.info/sct', 'institucional'),
(28, 'EDUCACION SANITARIA', 'a:1:{s:5:\"color\";s:7:\"#b88d9e\";}', 'enfermeria', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'soporte', NULL, NULL, 'institucional'),
(29, 'FARMACIA', '', 'enfermeria', 'SI', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(30, 'LIMPIEZA Y MANTENIMIENTO', '', '', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'soporte', NULL, NULL, 'institucional'),
(31, 'NO SE ESPECIFICA', '', '', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'soporte', NULL, NULL, 'institucional'),
(32, 'TRABAJO SOCIAL', 'a:1:{s:5:\"color\";s:7:\"#19a6c2\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(33, 'TRAUMATOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#2bb585\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', '394801008', 'http://snomed.info/sct', 'institucional'),
(34, 'NEUMONOLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#c98204\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(35, 'CIRUGIA GENERAL', 'a:1:{s:5:\"color\";s:7:\"#a87b97\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'procedimiento', NULL, NULL, 'institucional'),
(36, 'PODOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#c98204\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(37, 'DIABETES', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(38, 'FISIOTERAPIA', 'a:1:{s:5:\"color\";s:7:\"#2bb585\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(39, 'DIAGNOSTICO POR IMAGENES', 'a:1:{s:5:\"color\";s:7:\"#74b368\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'diagnostico', '394914008', 'http://snomed.info/sct', 'institucional'),
(40, 'MEDICAMENTOS', '', '', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(41, 'FISIOTERAPIA', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(42, 'ELECTROCARDIOGRAMA', 'a:1:{s:5:\"color\";s:7:\"#ff0000\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(43, 'PLAZA SALUDABLE', '', 'enfermeria', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(44, 'GUARDIA DE ENFERMERÍA', '', 'enfermeria', 'SI', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(45, 'RAYOS X', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(46, 'PAPANICOLAU', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(47, 'VIH SIDA', 'a:1:{s:5:\"color\";s:7:\"#a87b97\";}', '', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(48, 'Conserjeria', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', '', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(49, 'GERIATRIA', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(50, 'FONOAUDIOLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#564267\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(51, 'TERAPIA INTENSIVA', 'a:1:{s:5:\"color\";s:7:\"#5487d9\";}', 'Medico', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(52, 'PSIQUIATRÍA', 'a:1:{s:5:\"color\";s:7:\"#7a49ab\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(53, 'NEFROLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#db83fb\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(54, 'UROLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#26c7fd\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(55, 'HEMATOLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#f25240\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(56, 'ACOMPAÑAMIENTO TERAPEUTICO', 'a:1:{s:5:\"color\";s:7:\"#1fd6ca\";}', '', 'SI', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(57, 'SOCIOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#ec1894\";}', '', 'SI', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(58, 'OTORRINOLARINGOLOGÍA', '', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(59, 'BIOIMAGEN', 'a:1:{s:5:\"color\";s:7:\"#ff8929\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(60, 'TRASLADO', '', '', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(61, 'GUARDIA', '', 'Medico', 'SI', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(62, 'ADMINISTRAR EFECTOR', '', 'AdminEfector', 'NO', 'NO', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'soporte', NULL, NULL, 'institucional'),
(63, 'FACTURACIÓN', 'a:1:{s:5:\"color\";s:7:\"#410075\";}', 'facturista', 'NO', 'NO', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(64, 'ONCOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#f52996\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(65, 'AUDITORÍA', 'a:1:{s:5:\"color\";s:7:\"#000000\";}', '_x_efector_aditoria', 'NO', 'NO', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(66, 'DERMATOLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#eecda0\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(67, 'ANESTESIA', 'a:1:{s:5:\"color\";s:7:\"#85f1ff\";}', 'Medico', 'NO', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(68, 'FLEBOLOGÍA', 'a:1:{s:5:\"color\";s:7:\"#b70606\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(69, 'DERIVACION APS', 'a:1:{s:5:\"color\";s:7:\"#fa0000\";}', 'enfermeria', 'SI', 'NO', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(70, 'RETINA', 'a:1:{s:5:\"color\";s:7:\"#74d9e7\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(71, 'CORNEA', 'a:1:{s:5:\"color\";s:7:\"#41904e\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(72, 'YAG LASER', 'a:1:{s:5:\"color\";s:7:\"#b91d4c\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(73, 'LASER', 'a:1:{s:5:\"color\";s:7:\"#9d0606\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(74, 'OCULOPLASTIA', 'a:1:{s:5:\"color\";s:7:\"#ccbb00\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(75, 'NEUROOFTALMOLOGIA', 'a:1:{s:5:\"color\";s:7:\"#0041c2\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(76, 'SEGMENTO ANTERIOR', 'a:1:{s:5:\"color\";s:7:\"#5c4b9b\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(77, 'BAJA VISION', 'a:1:{s:5:\"color\";s:7:\"#606b6c\";}', 'Medico', 'SI', 'SI', '', '', '', 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(78, 'HEMODINAMIA', 'a:1:{s:5:\"color\";s:7:\"#bd0000\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(79, 'TELEOBSTETRICIA', 'a:1:{s:5:\"color\";s:7:\"#732db9\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional'),
(80, 'SALUD COMUNITARIA - MATERNIDAD', 'a:1:{s:5:\"color\";s:7:\"#00c0ef\";}', 'Medico', 'SI', 'SI', NULL, NULL, NULL, 'NO', NULL, 'NINGUNA', 'NO', 'consulta', NULL, NULL, 'institucional');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id_servicio`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id_servicio` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Codigo de servicio', AUTO_INCREMENT=81;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
