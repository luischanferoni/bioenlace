-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 01-06-2026 a las 16:40:14
-- Versión del servidor: 11.8.6-MariaDB-log
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
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id_turnos` int(10) UNSIGNED NOT NULL COMMENT 'Codigo de turnos',
  `id_persona` int(11) NOT NULL COMMENT 'Codigo de persona',
  `fecha` date DEFAULT NULL COMMENT 'Fecha del turno.',
  `hora` time DEFAULT NULL COMMENT 'Hora del turno.',
  `id_profesional_efector_servicio` int(11) DEFAULT NULL,
  `confirmado` enum('SI','NO') DEFAULT NULL COMMENT 'Confirmado el turno. SI o NO',
  `referenciado` enum('SI','NO') DEFAULT NULL COMMENT 'referenciado: si viene referenciado de otro centro de salud.  SI o NO',
  `id_efector_referencia` int(11) DEFAULT 0,
  `id_consulta_referencia` int(11) NOT NULL DEFAULT 0 COMMENT 'Codigo de la consulta donde viene referenciado.',
  `id_servicio_asignado` int(10) UNSIGNED DEFAULT NULL COMMENT 'Codigo de servicio',
  `usuario_alta` varchar(40) DEFAULT NULL COMMENT 'Usuario que dio alta al turno.',
  `fecha_alta` date DEFAULT NULL COMMENT 'Fecha que se dio de alta al turno.',
  `usuario_mod` varchar(40) DEFAULT NULL COMMENT 'Usuario que modifico el turno.',
  `fecha_mod` date DEFAULT NULL COMMENT 'Fecha que se modifico el turno.',
  `id_efector` int(6) DEFAULT NULL,
  `atendido` enum('SI','NO','EN ATENCION') DEFAULT NULL,
  `programado` tinyint(1) NOT NULL DEFAULT 0,
  `id_servicio` int(11) NOT NULL DEFAULT 0,
  `estado` enum('PENDIENTE','CANCELADO','EN_ATENCION','ATENDIDO','SIN_ATENDER') NOT NULL DEFAULT 'PENDIENTE',
  `estado_motivo` enum('ERROR_CARGA','CANCELADO_X_PACIENTE','CANCELADO_X_MEDICO','SIN_ATENDER_X_PACIENTE','SIN_ATENDER_X_MEDICO') DEFAULT NULL,
  `parent_class` varchar(256) DEFAULT NULL,
  `parent_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) DEFAULT NULL,
  `deleted_by` bigint(20) DEFAULT NULL,
  `migrado` tinyint(4) DEFAULT NULL,
  `tipo_atencion` varchar(20) DEFAULT 'presencial' COMMENT 'presencial|teleconsulta',
  `es_sobreturno` tinyint(1) NOT NULL DEFAULT 0,
  `orden_atencion` int(11) DEFAULT NULL,
  `minutos_desplazamiento_estimado` int(11) DEFAULT NULL,
  `confirmado_en` datetime DEFAULT NULL,
  `confirmacion_token` varchar(64) DEFAULT NULL,
  `id_agenda_version` int(11) DEFAULT NULL,
  `intervalo_minutos_reserva` smallint(6) DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `fhir_status` varchar(32) DEFAULT NULL COMMENT 'FHIR AppointmentStatus',
  `appointment_type` varchar(64) DEFAULT NULL COMMENT 'Tipo de cita (código interno o FHIR)'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id_turnos`, `id_persona`, `fecha`, `hora`, `id_profesional_efector_servicio`, `confirmado`, `referenciado`, `id_efector_referencia`, `id_consulta_referencia`, `id_servicio_asignado`, `usuario_alta`, `fecha_alta`, `usuario_mod`, `fecha_mod`, `id_efector`, `atendido`, `programado`, `id_servicio`, `estado`, `estado_motivo`, `parent_class`, `parent_id`, `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by`, `migrado`, `tipo_atencion`, `es_sobreturno`, `orden_atencion`, `minutos_desplazamiento_estimado`, `confirmado_en`, `confirmacion_token`, `id_agenda_version`, `intervalo_minutos_reserva`, `hora_fin`, `fhir_status`, `appointment_type`) VALUES
(3461274, 920779, '2026-02-11', '10:45:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 23, 'PENDIENTE', NULL, NULL, NULL, '2026-02-11 13:40:31', '2026-05-09 14:43:49', NULL, 5748, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3461275, 920779, '2026-04-06', '17:15:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 23, 'PENDIENTE', NULL, NULL, NULL, '2026-03-06 19:00:21', '2026-05-09 14:43:49', NULL, 5748, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3461276, 920779, '2026-05-06', '08:00:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-05-05 22:26:33', '2026-05-09 14:43:49', NULL, 5749, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, '717ced1934cdcc96143441c040716728', NULL, NULL, NULL, NULL, NULL),
(3461277, 920779, '2026-05-13', '07:12:00', 4, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-05-12 15:02:47', '2026-05-12 15:02:47', NULL, 5749, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, '263c4407c92208dd3c2f983da53e12ee', NULL, NULL, NULL, NULL, NULL),
(3461278, 920779, '2026-05-18', '08:42:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'CANCELADO', 'CANCELADO_X_PACIENTE', NULL, NULL, '2026-05-13 11:04:16', '2026-05-13 14:41:48', '2026-05-13 14:41:48', 5749, 5749, 5749, NULL, 'presencial', 0, NULL, NULL, NULL, '6307faa269faab6ada434d63a972ab76', NULL, NULL, NULL, NULL, NULL),
(3461279, 920779, '2026-05-19', '08:28:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'CANCELADO', 'CANCELADO_X_PACIENTE', NULL, NULL, '2026-05-13 17:05:40', '2026-05-15 12:20:18', '2026-05-15 12:20:18', 5749, 5749, 5749, NULL, 'presencial', 0, NULL, NULL, NULL, '46749d0e24400d575cb6c7f17344ecf3', NULL, NULL, NULL, NULL, NULL),
(3461289, 920779, '2026-05-20', '14:15:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-05-18 19:04:17', '2026-05-18 19:04:17', NULL, 5749, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, 'bd42497bfcc3f1f40583d3eb2f540f94', 1, 15, '14:30:00', NULL, NULL),
(3461290, 920779, '2026-05-28', '13:30:00', 4, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-05-19 19:14:38', '2026-05-19 19:14:38', NULL, 5749, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, '254ff3c9fbba5f5fdf1f3bf511752156', 2, 15, '13:45:00', NULL, NULL),
(3461291, 920779, '2026-05-22', '08:30:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'CANCELADO', 'CANCELADO_X_PACIENTE', NULL, NULL, '2026-05-19 19:20:49', '2026-05-21 01:03:20', '2026-05-21 01:03:20', 5749, 5749, 5749, NULL, 'presencial', 0, NULL, NULL, NULL, '6ad8bceb8314d014a04b40f4221c9431', 1, 15, '08:45:00', NULL, NULL),
(3461292, 920779, '2026-05-21', '17:45:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-05-21 01:02:55', '2026-05-21 17:09:27', NULL, 5749, 5749, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, '851e6d540546f124ed654cd592f1cb7b', 1, 15, '18:00:00', NULL, NULL),
(3461293, 920778, '2026-06-03', '09:30:00', 4, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'CANCELADO', 'CANCELADO_X_PACIENTE', NULL, NULL, '2026-05-29 18:46:57', '2026-06-01 12:53:05', '2026-06-01 12:53:05', 5748, 5748, 5748, NULL, 'presencial', 0, NULL, NULL, NULL, 'bd628a419481d63a322a340b72d62c57', 2, 15, '09:45:00', NULL, NULL),
(3461294, 920778, '2026-06-08', '14:30:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-06-01 12:53:41', '2026-06-01 12:53:41', NULL, 5748, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, 'cebdfefbece0549e3ac3236764384126', 1, 15, '14:45:00', NULL, NULL),
(3461295, 920779, '2026-06-01', '11:00:00', 2, NULL, NULL, 0, 0, 23, NULL, NULL, NULL, NULL, 863, NULL, 0, 0, 'PENDIENTE', NULL, NULL, NULL, '2026-06-01 12:56:16', '2026-06-01 12:56:16', NULL, 5749, NULL, NULL, NULL, 'presencial', 0, NULL, NULL, NULL, '8107b17f3c0f49b0c6ac4258679a9043', 1, 15, '11:15:00', NULL, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id_turnos`),
  ADD UNIQUE KEY `ux_turnos_confirmacion_token` (`confirmacion_token`),
  ADD KEY `id_persona_idx` (`id_persona`),
  ADD KEY `id_efector_turnos` (`id_consulta_referencia`),
  ADD KEY `id_efector_t` (`id_consulta_referencia`),
  ADD KEY `id_servicio_idx` (`id_servicio_asignado`),
  ADD KEY `id_servicio_t` (`id_servicio_asignado`),
  ADD KEY `turnos_created_at_IDX` (`created_at`) USING BTREE,
  ADD KEY `idx_turnos_id_profesional_efector_servicio` (`id_profesional_efector_servicio`),
  ADD KEY `idx_turnos_fecha_persona_id_profesional_efector_servicio` (`fecha`,`id_persona`,`id_profesional_efector_servicio`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id_turnos` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Codigo de turnos', AUTO_INCREMENT=3461296;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
