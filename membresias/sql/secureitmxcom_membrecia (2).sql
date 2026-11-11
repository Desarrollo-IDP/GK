-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 01-09-2025 a las 15:16:58
-- Versión del servidor: 8.0.42-cll-lve
-- Versión de PHP: 8.3.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `secureitmxcom_membrecia`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido_paterno` varchar(50) NOT NULL,
  `apellido_materno` varchar(50) DEFAULT NULL,
  `edad` int DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` enum('M','F','Otro') DEFAULT NULL,
  `curp` varchar(18) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `direccion` text,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `must_set_password` tinyint(1) NOT NULL DEFAULT '1',
  `first_login_token` varchar(64) DEFAULT NULL,
  `first_login_expires_at` datetime DEFAULT NULL,
  `mp_customer_id` varchar(255) DEFAULT NULL,
  `mp_card_id` varchar(255) DEFAULT NULL,
  `mp_card_token` varchar(255) DEFAULT NULL,
  `empresa_id` int DEFAULT '1',
  `identificador_unico` varchar(50) DEFAULT NULL COMMENT 'Identificador único para tracking de pagos',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `apellido_paterno`, `apellido_materno`, `edad`, `fecha_nacimiento`, `sexo`, `curp`, `foto`, `direccion`, `correo`, `telefono`, `password_hash`, `must_set_password`, `first_login_token`, `first_login_expires_at`, `mp_customer_id`, `mp_card_id`, `mp_card_token`, `empresa_id`, `identificador_unico`, `fecha_registro`) VALUES
(64, 'Prueba', 'prueba', 'jardon', 7, '2025-08-20', 'M', 'aojo030310hmcnsaj', NULL, 'hkjhkjhkjh', 'jhg@jhg', '45455456345', '$2y$12$d0og4wbDPdyyVrjE6FC3WO0k3GAk9wOMCXjEy7EZNqo0epPVDBjmS', 0, NULL, NULL, NULL, NULL, 'e017c1e6d4192db91e0313504597e17d', 3, 'EMP3_CLI64_1755803821', '2025-08-21 19:04:04'),
(61, 'prueba', 'prueba', '', 2, '2222-02-02', 'M', 'aojo030310hmcnsaj', NULL, '', 'hjk@ip', '45455456345', '$2y$12$tl62JEGqdrgEAQkN.geLEeNXO6t.49XKW7wuaw00XEpnytn3s.oHi', 0, NULL, NULL, NULL, NULL, '901b4cd6780e1690a2e7f246e0986a8f', 3, 'EMP3_CLI61_1755803821', '2025-08-21 17:34:37'),
(62, 'oscar', 'prueba', 'jardon', 3, '2025-08-19', 'M', 'aojo030310hmcnsaj', 'uploads/fotos/foto_68a76be60ad12.png', 'hkjhkjhkjh', '3@3', '45455456345', '$2y$12$k7XowZGmX5xIqv9EJyqtqe12UfCnPKVZ8wZiV3XGJXlo7gbyyz7ru', 0, NULL, NULL, NULL, NULL, '1786b79d504bdd3118ada32987761b80', 1, 'EMP1_CLI62_1755803821', '2025-08-21 18:56:38'),
(63, 'prueba', 'antonio ', 'jardon', 1, '2025-12-30', 'M', 'aojo030310hmcnsaj', NULL, '', '3@3', '45455456345', '$2y$12$.x.SyjXjX/7UAl5o2vLzselmcRmuU3hKFMClBcXQbV1giEjTrEKbO', 0, NULL, NULL, NULL, NULL, NULL, 3, 'EMP3_CLI63_1755803821', '2025-08-21 18:57:27'),
(60, 'test', 'prueba', '', 1, '2025-08-20', 'M', 'aojo030310hmcnsaj', NULL, 'Atizapan', 'admin@sistema.com', '45455456345', '$2y$12$EWpmEI1TDbbPj/4JrXY7JuXbRt9sUqbPQLzaemzjnKxva/TeMSnlW', 0, NULL, NULL, NULL, NULL, '348770e9b37fd5644ea5255f9c942534', 3, 'EMP3_CLI60_1755803821', '2025-08-21 17:06:44'),
(65, 'jl', 'antonio ', '', 7, '7777-07-07', 'M', 'aojo030310hmcnsaj', NULL, 'Atizapan', '4@4', '45455456345', '$2y$12$oaxa0.ylP8ltdEUQzwVelu0P2FcYzKOiA4ee2V3b9BhbLwC.Zn5vi', 0, NULL, NULL, NULL, NULL, '098b3023a736b76cd7c2dc61a4753590', 3, 'EMP3_CLI_1755814368_2519', '2025-08-21 22:12:48'),
(66, 'test', 'prueba', 'jardon', 9, '2009-09-09', 'M', 'aojo030310hmcnsaj', NULL, '', 'kjdash@wnbsladjkn', '555555555555555', '$2y$12$F09yxvSjZ2o9N9hfnD6/CuC00rMPZ8fNyzKMINTiepTzz3ZM51zk6', 0, NULL, NULL, NULL, NULL, '3e6e352f704c1d71891d65856410c113', 3, 'EMP3_CLI_1755878707_1094', '2025-08-22 16:05:08'),
(67, 'test', 'antonio ', 'jardon', 1, '2025-08-26', 'M', 'aojo030310hmcnsaj', NULL, 'kjl', 'sadsafdfs@sdfsdf', '555555555555555', '$2y$12$p9GUB/HnnYvidBq7ejk3qurs84BH5jwO4a0apYjrlJtJrA6y1fvA.', 0, NULL, NULL, NULL, NULL, 'd59f050e21f4dcdb4f52781e557262b4', 3, 'EMP3_CLI_1756322989_4087', '2025-08-27 19:29:49'),
(68, 'prueba', 'prueba', '', 2, '2025-08-27', 'M', 'aojo030310hmcnsaj', NULL, 'hkjhkjhkjh', 'pru@prueba.pru1', '45455456345', '$2y$12$bAEFEbxxnGW3qHqP7nbdSuyu86QkRoEixZAJfFU7vJ2dDbPcfC2lS', 0, NULL, NULL, NULL, NULL, 'f9ce4b2ffc5fbcf3f861ef27d093e336', 3, 'EMP3_CLI_1756326371_1031', '2025-08-27 20:26:12'),
(69, 'prueba', 'prueba', 'jardon', 15, '2025-08-27', 'M', 'aojo030310hmcnsaj', NULL, '', 'pru@prueba.pru2', '45455456345', '$2y$12$HsEVvippBfmBpJDpcad9k.djfLM0PyjeLy6f5SMkybBRhRbaI2ng6', 0, NULL, NULL, NULL, NULL, '9c2c8edb8b5555dd6efa9f50494c4bb5', 3, 'EMP3_CLI_1756392626_1808', '2025-08-28 14:50:27'),
(70, 'test', 'prueba', 'jardon', 1, '2025-08-27', 'M', 'aojo030310hmcnsaj', NULL, '', 'pru@prueba.pru3', '45455456345', '$2y$12$BnCJn/P12IaoI30bG.jqMu1VyGKVV8Q7xTovutnCfLqA5LG8g7w4K', 0, NULL, NULL, NULL, NULL, '4a80053d80618d4ceaa681a7e7cc3db8', 3, 'EMP3_CLI_1756394607_2103', '2025-08-28 15:23:27'),
(71, 'prueba', 'antonio ', 'jardon', 1, '2025-08-28', 'M', 'aojo030310hmcnsaj', NULL, 'Atizapan', 'pru@prueba.pru4', '45455456345', '$2y$12$O20XMwIC56qHL1v9OrMmz.QCmVEyO2Nz1rX.j..gr4gKCD1Wtnvjq', 0, NULL, NULL, NULL, NULL, '9e39b4398ca13fc133f32dd23abb1246', 3, 'EMP3_CLI_1756407081_9193', '2025-08-28 18:51:21'),
(72, 'prueba', 'prueba', '', 1, '2025-08-27', 'M', 'aojo030310hmcnsaj', NULL, 'hkjhkjhkjh', 'pru@prueba.pru5', '45455456345', '$2y$12$6E2cGcjS3g44dzoufUGRHOvBVTcbFkeQ78O2vrN4CBU7//dWyYGtK', 0, NULL, NULL, NULL, NULL, 'a957e6c6275dbbe4bcb889ec21b72ed9', 3, 'EMP3_CLI_1756407637_9316', '2025-08-28 19:00:37'),
(73, 'prueba', 'prueba', 'jardon', 3, '2025-08-26', 'M', 'aojo030310hmcnsaj', NULL, 'Atizapan', 'pru@prueba.pru6', '45455456345', '$2y$12$ZggAxYppRHqfQ1jFdkaBnOU3jencushyNWSDYt6TG.vjMt2tRlR8i', 0, NULL, NULL, NULL, NULL, '08491f8300f26c0f000d10f64e71a81a', 3, 'EMP3_CLI_1756410103_9470', '2025-08-28 19:41:44'),
(74, 'prueba', 'prueba', 'jardon', 1, '2025-12-31', 'M', 'aojo030310hmcnsaj', NULL, '', 'pru@prueba.pru7', '45455456345', '$2y$12$OQwMbK32.1T5QFAJdvLqOup.jj4D4TFF7OSbYedmPUy1Y5ZKa0sfy', 0, NULL, NULL, NULL, NULL, 'aa7f315a5d6ab6d42034a94c44823a90', 3, 'EMP3_CLI_1756742801_4696', '2025-09-01 16:06:42'),
(75, 'prueba', 'antonio ', 'jardon', 1, '2025-12-31', 'M', 'aojo030310hmcnsaj', NULL, 'Atizapan', 'pru@prueba.pru8', '45455456345', '$2y$12$AJOhOJt.TFTCiBUn3VY67.WRKhLfZHu2Z.Zl58MvEaSDGq5MTHCP6', 0, NULL, NULL, NULL, NULL, 'd7dabcf4f78dfbf467d652e146a4af0c', 3, 'EMP3_CLI_1756743713_8102', '2025-09-01 16:21:53'),
(76, 'test', 'prueba', '', 1, '2024-03-13', 'M', 'aojo030310hmcnsaj', NULL, 'Atizapan', 'pru@prueba.pru', '45455456345', '$2y$10$VqBv5.H1WqIdvCEeMUGQWec3ODAc/zVHoQHdtoNml6dbHO3D6PlX6', 0, NULL, NULL, NULL, NULL, '296bf1482d35ffc490af84616ffb6ad2', 3, 'EMP3_CLI_1756744032_9095', '2025-09-01 16:27:13'),
(77, 'test', 'prueba', '', 1, '2024-09-01', 'M', 'aojo030310hmcnsaj', NULL, 'hkjhkjhkjh', '5@5', '45455456345', '$2y$12$K5kqE5/OMiAaYjR3oqrzTeBYvFwSpQ1jdYcAblBQR6oNq397qQq1G', 0, NULL, NULL, NULL, NULL, '1a7abd1156f6582c15b6666eeecbc549', 3, 'EMP3_CLI_1756744955_4467', '2025-09-01 16:42:36'),
(78, 'test', 'prueba', '', 1, '2025-09-01', 'M', 'aojo030310hmcnsaj', NULL, '', '7@7', '45455456345', '$2y$12$3zwVyCQKqFm566No368qs.tsWThNX9ky8l7nNJN7xMj76G8Ssieaq', 0, NULL, NULL, NULL, NULL, NULL, 3, 'EMP3_CLI_1756746076_8593', '2025-09-01 17:01:16'),
(79, 'test', 'prueba', '', 1, '2025-08-31', 'M', 'aojo030310hmcnsaj', 'uploads/fotos/foto_68b5db7c1ae10.png', '', '8@8', '45455456345', '$2y$12$RnhMRK45Idb1uQFSQA4Llu/jH2.fTNlrtecxHunqwWrJLyHVbw8Ry', 0, NULL, NULL, NULL, NULL, NULL, 3, 'EMP3_CLI_1756748668_9614', '2025-09-01 17:44:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `razon_social` varchar(200) DEFAULT NULL,
  `rfc` varchar(13) DEFAULT NULL,
  `direccion` text,
  `colonia` varchar(100) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `pais` varchar(50) DEFAULT 'México',
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `nombre`, `razon_social`, `rfc`, `direccion`, `colonia`, `ciudad`, `estado`, `codigo_postal`, `pais`, `telefono`, `correo`, `sitio_web`, `logo`, `descripcion`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Empresa Demo', 'Empresa Demo S.A. de C.V.', 'DEMO123456789', 'Av. Principal 123', 'Centro', 'Ciudad de México', 'CDMX', '06000', 'México', '555-123-4567', 'info@empresademo.com', 'https://www.empresademo.com', NULL, 'Empresa de demostración para el sistema de membresías', 1, '2025-07-30 16:45:01', '2025-07-30 16:45:01'),
(2, 'empresa', 's.a. de c.v.', 'AOJO0303103F7', 'Atizapan', '', 'nicolas romero', 'edo mex', '555555', 'México', '45455456345', 'admin@sistema.com', 'https://membresia.secureitmx.com/modules/empresas/add.php', NULL, '', 1, '2025-07-30 18:23:29', '2025-07-30 18:23:29'),
(3, 'prueba', 's.a. de c.v.', 'PRUB678912D34', 'Atizapan', '', 'nicolas romero', 'edo mex', '', 'México', '45455456345', 'admin@sistema.com', '', NULL, '', 1, '2025-08-05 19:38:39', '2025-08-05 19:38:39'),
(4, 'jl', 's.a. de c.v.', 'PRUB6789121F2', 'Atizapan', '', 'nicolas romero', 'edo mex', '555555', 'México', '555555555555555', 'pru@prueba.pru', 'https://membresia.secureitmx.com/modules/empresas/add.php', NULL, '', 1, '2025-08-07 22:34:03', '2025-08-07 22:34:03'),
(5, 'jl 2', 's.a. de c.v.', 'PRUB6789121F2', '', '', 'nicolas romero', 'edo mex', '', 'México', '', '', 'https://membresia.secureitmx.com/modules/empresas/add.php', NULL, '', 1, '2025-08-07 22:36:27', '2025-08-07 22:36:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `membresias`
--

CREATE TABLE `membresias` (
  `id` int NOT NULL,
  `cliente_id` int DEFAULT NULL,
  `empresa_id` int DEFAULT '1',
  `tipo_membresia_id` int DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `firma_digital` varchar(255) DEFAULT NULL,
  `mp_subscription_id` varchar(255) DEFAULT NULL,
  `mp_status` enum('pending','authorized','paused','cancelled') DEFAULT 'pending',
  `mp_payment_method` varchar(50) DEFAULT NULL,
  `mp_last_payment_date` datetime DEFAULT NULL,
  `mp_next_payment_date` datetime DEFAULT NULL,
  `mp_cancellation_fee` decimal(10,2) DEFAULT '0.00',
  `mp_cancelled_at` datetime DEFAULT NULL,
  `mp_cancellation_reason` text,
  `mp_last_sync_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mp_preapproval_id` varchar(255) DEFAULT NULL COMMENT 'ID de la suscripción (preapproval) en Mercado Pago',
  `mp_plan_id` varchar(255) DEFAULT NULL COMMENT 'Plan asociado usado para suscripción',
  `mp_external_reference` varchar(100) DEFAULT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `mp_payment_date` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `membresias`
--

INSERT INTO `membresias` (`id`, `cliente_id`, `empresa_id`, `tipo_membresia_id`, `fecha_inicio`, `fecha_fin`, `metodo_pago`, `firma_digital`, `mp_subscription_id`, `mp_status`, `mp_payment_method`, `mp_last_payment_date`, `mp_next_payment_date`, `mp_cancellation_fee`, `mp_cancelled_at`, `mp_cancellation_reason`, `mp_last_sync_at`, `created_at`, `mp_preapproval_id`, `mp_plan_id`, `mp_external_reference`, `mp_payment_id`, `mp_payment_date`) VALUES
(59, 60, 3, 17, '2025-08-21', '2025-09-19', 'mercadopago', 'uploads/firmas/firma_68a752248a09c.png', NULL, 'cancelled', NULL, NULL, NULL, 0.00, NULL, NULL, '2025-08-21 17:46:09', '2025-08-21 17:06:44', '7643df41b4fd4d43adeaa3534fd67d60', 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(60, 61, 3, 17, '2025-08-21', '2025-09-19', 'mercadopago', 'uploads/firmas/firma_68a758ad9af5a.png', NULL, 'cancelled', NULL, NULL, NULL, 0.00, NULL, NULL, '2025-08-21 17:46:09', '2025-08-21 17:34:37', '50ca87d1229a4f479a28bf2ef2f655f1', 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(61, 62, 1, 17, '2025-08-21', '2025-09-19', 'mercadopago', 'uploads/firmas/firma_68a76be6108e4.png', NULL, 'cancelled', NULL, NULL, NULL, 0.00, NULL, NULL, '2025-08-21 17:46:09', '2025-08-21 18:56:38', '50ca87d1229a4f479a28bf2ef2f655f1', NULL, NULL, NULL, NULL),
(62, 63, 3, 17, '2025-08-21', '2025-09-19', 'mercadopago', 'uploads/firmas/firma_68a76c1723f74.png', NULL, 'cancelled', NULL, NULL, NULL, 0.00, NULL, NULL, '2025-08-21 17:46:09', '2025-08-21 18:57:27', '2459ef35f6ad4f1c8542de68106939d6', 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(63, 64, 3, 17, '2025-08-21', '2025-09-19', 'mercadopago', 'uploads/firmas/firma_68a76da3eaa94.png', NULL, 'cancelled', NULL, NULL, NULL, 0.00, NULL, NULL, '2025-08-21 17:46:08', '2025-08-21 19:04:04', '2459ef35f6ad4f1c8542de68106939d6', 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(64, 65, 3, 17, '2025-08-21', '2025-09-19', 'mercadopago', 'uploads/firmas/firma_68a799e098ab5.png', NULL, 'cancelled', NULL, NULL, '2025-09-21 18:15:03', 500.00, '2025-08-21 18:16:22', 'Error en cobro automático: Error de API de MercadoPago: Api error. Check response for details', '2025-08-21 18:16:36', '2025-08-21 22:12:48', 'f45691d0c7184d3398210d1a948fc8a5', 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(65, 66, 3, 17, '2025-08-22', '2025-09-20', 'mercadopago', 'uploads/firmas/firma_68a89533b6069.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-22 16:05:08', NULL, 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(66, 67, 3, 17, '2025-08-27', '2025-09-25', 'mercadopago', 'uploads/firmas/firma_68af5cad4dbcd.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-27 19:29:49', NULL, 'f4b5fc92e08c40eb88c938780671fc7b', NULL, NULL, NULL),
(67, 68, 3, 17, '2025-08-27', '2025-09-25', 'mercadopago', 'uploads/firmas/firma_68af69e3dfa78.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-27 20:26:12', NULL, 'f4b5fc92e08c40eb88c938780671fc7b', 'MEM_68_67_17_1756392434', NULL, NULL),
(68, 69, 3, 18, '2025-08-28', '2025-09-26', 'mercadopago', 'uploads/firmas/firma_68b06cb2ca207.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-28 14:50:27', NULL, '6a203a85fa3f42308943440008409860', 'MEM_69_68_18_1756410023', NULL, NULL),
(69, 70, 3, 18, '2025-08-28', '2025-09-26', 'mercadopago', 'uploads/firmas/firma_68b0746f24592.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-28 15:23:27', NULL, '6a203a85fa3f42308943440008409860', 'MEM_70_69_18_1756404786', NULL, NULL),
(70, 71, 3, 18, '2025-08-28', '2025-09-26', 'mercadopago', 'uploads/firmas/firma_68b0a5295b30b.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-28 18:51:21', NULL, '6a203a85fa3f42308943440008409860', 'MEM_71_70_18_1756407190', NULL, NULL),
(71, 72, 3, 18, '2025-08-28', '2025-09-26', 'mercadopago', 'uploads/firmas/firma_68b0a75553df3.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-08-28 19:00:37', NULL, '6a203a85fa3f42308943440008409860', 'MEM_72_71_18_1756410018', NULL, NULL),
(72, 73, 3, 18, '2025-08-28', '2025-09-26', 'mercadopago', 'uploads/firmas/firma_68b0b0f7e6d7c.png', '2624624906-f7523538-51f3-4404-8ff9-dbf500fccada', 'cancelled', NULL, NULL, '2026-09-01 11:36:21', 0.00, NULL, NULL, '2025-09-01 12:43:48', '2025-08-28 19:41:44', '0aedc184f8794d1999389cdb7611abb7', '6a203a85fa3f42308943440008409860', 'MEM_72_73_1756740931', NULL, NULL),
(73, 74, 3, 18, '2025-09-01', '2025-09-30', 'mercadopago', 'uploads/firmas/firma_68b5c491c4ca7.png', NULL, 'cancelled', NULL, NULL, '2026-09-01 11:36:21', 500.00, '2025-09-01 12:08:33', 'Cargo pendiente - Cliente sin token de tarjeta', '2025-09-01 12:23:06', '2025-09-01 16:06:42', '0aedc184f8794d1999389cdb7611abb7', '6a203a85fa3f42308943440008409860', NULL, NULL, NULL),
(74, 75, 3, 18, '2025-09-01', '2025-09-30', 'mercadopago', 'uploads/firmas/firma_68b5c8218f6ee.png', '2624624906-e236ef89-ce6d-4e2d-b7bb-7ec492c363f7', 'cancelled', NULL, NULL, NULL, 500.00, '2025-09-01 12:25:40', 'Error en cobro automático: Error de API de MercadoPago: Api error. Check response for details', '2025-09-01 12:22:27', '2025-09-01 16:21:53', '71b1bb6aca8d4da4b6f3a2cfe4915d98', '6a203a85fa3f42308943440008409860', 'MEM_74_75_1756743747', NULL, NULL),
(75, 76, 3, 17, '2025-09-01', '2025-09-30', 'mercadopago', 'uploads/firmas/firma_68b5c960e4c8e.png', '2624624906-e3deaa23-f869-404d-95de-ce65da5a40c4', 'authorized', NULL, NULL, '2025-10-01 12:28:09', 0.00, NULL, NULL, '2025-09-01 12:27:45', '2025-09-01 16:27:13', '0350f053b83d45a58fff6a60da3a5feb', 'f4b5fc92e08c40eb88c938780671fc7b', 'MEM_75_76_1756744065', NULL, NULL),
(76, 77, 3, 18, '2025-09-01', '2025-09-30', 'mercadopago', 'uploads/firmas/firma_68b5ccfb7c6db.png', '2624624906-2ff3a4cc-9106-4448-ba9e-9408ae9a3ac4', 'authorized', NULL, NULL, '2026-09-01 12:43:47', 0.00, NULL, NULL, '2025-09-01 12:43:19', '2025-09-01 16:42:36', '2afb4b0db8434f6fb4badf2d5d4833fe', '6a203a85fa3f42308943440008409860', 'MEM_76_77_1756744999', NULL, NULL),
(77, 78, 3, 18, '2025-09-01', '2025-09-30', 'mercadopago', 'uploads/firmas/firma_68b5d15c29da3.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-09-01 17:01:16', NULL, '6a203a85fa3f42308943440008409860', NULL, NULL, NULL),
(78, 79, 3, 18, '2025-09-01', '2025-09-30', 'mercadopago', 'uploads/firmas/firma_68b5db7c1b14a.png', NULL, 'pending', NULL, NULL, NULL, 0.00, NULL, NULL, NULL, '2025-09-01 17:44:28', NULL, '6a203a85fa3f42308943440008409860', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mp_config`
--

CREATE TABLE `mp_config` (
  `id` int NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `key_value` text NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `mp_config`
--

INSERT INTO `mp_config` (`id`, `key_name`, `key_value`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'public_key', 'TEST-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'Clave pública de MercadoPago', 1, '2025-08-08 20:10:30', '2025-08-08 20:10:30'),
(2, 'access_token', 'TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'Token de acceso de MercadoPago', 1, '2025-08-08 20:10:30', '2025-08-08 20:10:30'),
(3, 'webhook_url', 'https://tu-dominio.com/webhook.php', 'URL del webhook de MercadoPago', 1, '2025-08-08 20:10:30', '2025-08-08 20:10:30'),
(4, 'cancellation_fee_percentage', '10', 'Porcentaje de cargo por cancelación', 1, '2025-08-08 20:10:30', '2025-08-08 20:10:30'),
(5, 'email_from', 'noreply@tu-dominio.com', 'Email de origen para notificaciones', 1, '2025-08-08 20:10:30', '2025-08-08 20:10:30'),
(6, 'email_from_name', 'Sistema de Membresías', 'Nombre de origen para notificaciones', 1, '2025-08-08 20:10:30', '2025-08-08 20:10:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mp_payment_logs`
--

CREATE TABLE `mp_payment_logs` (
  `id` int NOT NULL,
  `membresia_id` int NOT NULL,
  `mp_payment_id` varchar(255) NOT NULL,
  `mp_subscription_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `mp_response` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mp_sync_statistics`
--

CREATE TABLE `mp_sync_statistics` (
  `id` int NOT NULL,
  `active_users_synced` int NOT NULL DEFAULT '0',
  `pending_users_synced` int NOT NULL DEFAULT '0',
  `inconsistencies_fixed` int NOT NULL DEFAULT '0',
  `sync_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mp_sync_statistics`
--

INSERT INTO `mp_sync_statistics` (`id`, `active_users_synced`, `pending_users_synced`, `inconsistencies_fixed`, `sync_date`, `created_at`) VALUES
(1, 0, 0, 0, '2025-08-21 16:11:45', '2025-08-21 20:11:45'),
(2, 0, 0, 0, '2025-08-21 17:36:43', '2025-08-21 21:36:43'),
(3, 0, 0, 0, '2025-08-21 17:41:40', '2025-08-21 21:41:40'),
(4, 0, 0, 0, '2025-08-21 17:41:40', '2025-08-21 21:41:40'),
(5, 0, 5, 0, '2025-08-21 17:46:09', '2025-08-21 21:46:09'),
(6, 0, 0, 0, '2025-08-21 17:50:57', '2025-08-21 21:50:57'),
(7, 0, 0, 1, '2025-08-21 18:16:36', '2025-08-21 22:16:36'),
(8, 0, 0, 0, '2025-09-01 11:36:23', '2025-09-01 15:36:23'),
(9, 0, 0, 1, '2025-09-01 12:23:06', '2025-09-01 16:23:06'),
(10, 0, 0, 0, '2025-09-01 12:24:12', '2025-09-01 16:24:12'),
(11, 0, 0, 0, '2025-09-01 12:25:16', '2025-09-01 16:25:16'),
(12, 0, 0, 0, '2025-09-01 12:25:42', '2025-09-01 16:25:42'),
(13, 1, 0, 0, '2025-09-01 12:43:48', '2025-09-01 16:43:48'),
(14, 1, 0, 0, '2025-09-01 12:43:48', '2025-09-01 16:43:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mp_webhooks`
--

CREATE TABLE `mp_webhooks` (
  `id` int NOT NULL,
  `mp_topic` varchar(100) NOT NULL,
  `mp_resource_id` varchar(255) NOT NULL,
  `mp_user_id` varchar(255) DEFAULT NULL,
  `mp_application_id` varchar(255) DEFAULT NULL,
  `mp_attempts` int DEFAULT '0',
  `mp_sent` datetime DEFAULT NULL,
  `mp_received` datetime DEFAULT NULL,
  `mp_status` varchar(50) DEFAULT 'pending',
  `mp_response` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payment_preferences`
--

CREATE TABLE `payment_preferences` (
  `id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `membresia_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `external_reference` varchar(100) NOT NULL,
  `preference_id` varchar(100) NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `payment_preferences`
--

INSERT INTO `payment_preferences` (`id`, `cliente_id`, `membresia_id`, `plan_id`, `external_reference`, `preference_id`, `payment_id`, `amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 68, 67, 17, 'MEM_68_67_17_1756332814', 'PREF_MEM_68_67_17_1756332814', NULL, 200.00, 'pending', '2025-08-27 22:13:34', NULL),
(2, 68, 67, 17, 'MEM_68_67_17_1756332889', 'PREF_MEM_68_67_17_1756332889', NULL, 200.00, 'pending', '2025-08-27 22:14:49', NULL),
(3, 68, 67, 17, 'MEM_68_67_17_1756333177', 'PREF_MEM_68_67_17_1756333177', NULL, 200.00, 'pending', '2025-08-27 22:19:37', NULL),
(4, 68, 67, 17, 'MEM_68_67_17_1756392315', '2624624906-7131ca2d-4776-4b71-936d-fa48a5029cea', NULL, 200.00, 'pending', '2025-08-28 14:45:15', NULL),
(5, 68, 67, 17, 'MEM_68_67_17_1756392331', '2624624906-172cbb03-1bab-4ee9-9395-02855311119b', NULL, 200.00, 'pending', '2025-08-28 14:45:31', NULL),
(6, 68, 67, 17, 'MEM_68_67_17_1756392434', '2624624906-b728e4dd-1cdb-4e93-829d-1114e87f4852', NULL, 200.00, 'pending', '2025-08-28 14:47:14', NULL),
(7, 69, 68, 18, 'MEM_69_68_18_1756393677', '2624624906-2649f21f-1b01-43a6-a485-4d1714066801', NULL, 100.00, 'pending', '2025-08-28 15:07:57', NULL),
(8, 69, 68, 18, 'MEM_69_68_18_1756394469', '2624624906-db746d3f-68f5-49cf-abb2-f1c352e6cfb0', NULL, 100.00, 'pending', '2025-08-28 15:21:09', NULL),
(9, 69, 68, 18, 'MEM_69_68_18_1756394511', '2624624906-4e320d79-a4a8-4122-afbc-2d018c44f124', NULL, 100.00, 'pending', '2025-08-28 15:21:51', NULL),
(10, 69, 68, 18, 'MEM_69_68_18_1756394538', '2624624906-fe3ff4ef-55fa-4aac-a593-02434162e479', NULL, 100.00, 'pending', '2025-08-28 15:22:19', NULL),
(11, 70, 69, 18, 'MEM_70_69_18_1756394663', '2624624906-83b04073-f00e-485d-bb06-830270b5ea0f', NULL, 100.00, 'pending', '2025-08-28 15:24:23', NULL),
(12, 70, 69, 18, 'MEM_70_69_18_1756394669', '2624624906-5068e6da-d794-481d-b8b4-a69b9b983279', NULL, 100.00, 'pending', '2025-08-28 15:24:29', NULL),
(13, 70, 69, 18, 'MEM_70_69_18_1756404770', '2624624906-2bdcd07a-a15a-4241-9342-c7092f68a2eb', NULL, 100.00, 'pending', '2025-08-28 18:12:50', NULL),
(14, 70, 69, 18, 'MEM_70_69_18_1756404786', '2624624906-08ae6eff-af07-48e1-b431-af9a01657bd9', NULL, 100.00, 'pending', '2025-08-28 18:13:06', NULL),
(15, 71, 70, 18, 'MEM_71_70_18_1756407190', '2624624906-44515c91-41be-461a-8523-f9734af8144e', NULL, 100.00, 'pending', '2025-08-28 18:53:10', NULL),
(16, 72, 71, 18, 'MEM_72_71_18_1756407717', '2624624906-a3f4b45c-4488-4948-91d1-1cf7810d403d', NULL, 100.00, 'pending', '2025-08-28 19:01:57', NULL),
(17, 72, 71, 18, 'MEM_72_71_18_1756407965', '2624624906-5aebeacb-78bf-4847-a7de-827d69dae195', NULL, 100.00, 'pending', '2025-08-28 19:06:05', NULL),
(18, 72, 71, 18, 'MEM_72_71_18_1756408236', '2624624906-ce78f590-97bb-4c4f-be92-ec53d2202607', NULL, 100.00, 'pending', '2025-08-28 19:10:36', NULL),
(19, 72, 71, 18, 'MEM_72_71_18_1756409865', '2624624906-7e68ef9e-faf6-4969-bae9-ba085991cfa2', NULL, 100.00, 'pending', '2025-08-28 19:37:45', NULL),
(20, 72, 71, 18, 'MEM_72_71_18_1756410018', '2624624906-d28aa4b6-aec0-4478-9010-6fc5c5f6458e', NULL, 100.00, 'pending', '2025-08-28 19:40:19', NULL),
(21, 69, 68, 18, 'MEM_69_68_18_1756410023', '2624624906-1e410a8d-5d65-4373-8e58-037756f9cdd4', NULL, 100.00, 'pending', '2025-08-28 19:40:24', NULL),
(22, 73, 72, 18, 'MEM_73_72_18_1756410160', '2624624906-0f21511f-8d1b-4a14-b5cb-675b340b937a', NULL, 100.00, 'pending', '2025-08-28 19:42:41', NULL),
(23, 73, 72, 18, 'MEM_73_72_18_1756410210', '2624624906-6f0c1a64-cebc-4546-97e9-212b5c3154b6', NULL, 100.00, 'pending', '2025-08-28 19:43:31', NULL),
(24, 73, 72, 18, 'MEM_73_72_18_1756410219', '2624624906-2b1cf508-1cc5-41f1-8188-89f7b7889b29', NULL, 100.00, 'pending', '2025-08-28 19:43:39', NULL),
(25, 73, 72, 18, 'MEM_73_72_18_1756410298', '2624624906-3909e582-58b3-4c0d-9ca9-2e3dcd3f8738', NULL, 100.00, 'pending', '2025-08-28 19:44:59', NULL),
(26, 73, 72, 18, 'MEM_73_72_18_1756410371', '2624624906-a707109e-db4b-43ec-a9e0-9bb9abd0cb5d', NULL, 100.00, 'pending', '2025-08-28 19:46:11', NULL),
(27, 73, 72, 18, 'MEM_73_72_18_1756418494', '2624624906-6c544828-8629-4e38-919d-84292084e2d2', NULL, 100.00, 'pending', '2025-08-28 22:01:34', NULL),
(28, 73, 72, 18, 'MEM_73_72_18_1756418618', '2624624906-a4109f29-bd17-46df-a292-7736e7fe7075', NULL, 100.00, 'pending', '2025-08-28 22:03:38', NULL),
(29, 73, 72, 18, 'MEM_73_72_18_1756419897', '2624624906-f756bb3f-a5a7-4eae-b2cc-a61b0cdc9833', NULL, 100.00, 'pending', '2025-08-28 22:24:58', NULL),
(30, 73, 72, 18, 'MEM_73_72_18_1756420111', '2624624906-60e0c902-9e31-4f95-899c-df663e5439ae', NULL, 100.00, 'pending', '2025-08-28 22:28:31', NULL),
(31, 73, 72, 18, 'MEM_73_72_18_1756420171', '2624624906-84fac420-e009-4b6a-b7b2-e49d202088fc', NULL, 100.00, 'pending', '2025-08-28 22:29:31', NULL),
(32, 73, 72, 18, 'MEM_73_72_18_1756420209', '2624624906-447e1784-6eea-4b0d-a129-a80509d2a84d', NULL, 100.00, 'pending', '2025-08-28 22:30:09', NULL),
(33, 73, 72, 18, 'MEM_73_72_18_1756420606', '2624624906-59d3a767-0288-492f-9916-068e4b296e4b', NULL, 100.00, 'pending', '2025-08-28 22:36:46', NULL),
(34, 73, 72, 18, 'MEM_73_72_18_1756479552', '2624624906-c171909f-a514-48b3-acd6-b9b6b096dde6', NULL, 100.00, 'pending', '2025-08-29 14:59:12', NULL),
(35, 73, 72, 18, 'MEM_73_72_18_1756482290', '2624624906-5481c74a-413b-42b2-8e89-a1dbf3cfcea5', NULL, 100.00, 'pending', '2025-08-29 15:44:51', NULL),
(36, 73, 72, 12, 'MEM_73_72_12_1756482386', '2624624906-32d480ec-4702-4f6e-ba69-eeeaba1f790b', NULL, 50.00, 'pending', '2025-08-29 15:46:26', NULL),
(37, 73, 72, 12, 'MEM_73_72_12_1756482713', '2624624906-7783e9f3-e8db-465e-993d-73d5d0b54095', NULL, 50.00, 'pending', '2025-08-29 15:51:53', NULL),
(38, 73, 72, 12, 'MEM_73_72_12_1756486422', '2624624906-22bfee1e-23ad-43f5-8805-40b2734fd99e', NULL, 50.00, 'pending', '2025-08-29 16:53:42', NULL),
(39, 73, 72, 18, 'MEM_73_72_18_1756487309', '2624624906-c95ea0fc-fcd5-4f50-a123-b6a1fcd19517', NULL, 100.00, 'pending', '2025-08-29 17:08:30', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Usuario'),
(3, 'Operador'),
(4, 'Supervisor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_membresia`
--

CREATE TABLE `tipos_membresia` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `empresa_id` int DEFAULT '1',
  `precio` decimal(10,2) DEFAULT NULL,
  `duracion_dias` int DEFAULT '30' COMMENT 'Duración de la membresía en días',
  `interval` enum('day','week','month','year') DEFAULT 'month' COMMENT 'Intervalo de facturación para suscripciones recurrentes',
  `interval_count` int DEFAULT '1' COMMENT 'Número de intervalos entre cada facturación',
  `mp_product_id` varchar(255) DEFAULT NULL,
  `mp_subscription_id` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `mp_plan_id` varchar(255) DEFAULT NULL COMMENT 'ID del plan de preaprobación en Mercado Pago',
  `monto_cancelacion` decimal(10,2) DEFAULT '0.00' COMMENT 'Monto de cargo por cancelación de suscripción'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `tipos_membresia`
--

INSERT INTO `tipos_membresia` (`id`, `nombre`, `descripcion`, `empresa_id`, `precio`, `duracion_dias`, `interval`, `interval_count`, `mp_product_id`, `mp_subscription_id`, `is_active`, `mp_plan_id`, `monto_cancelacion`) VALUES
(12, 'elisa', '', 2, 50.00, 30, 'month', 1, NULL, NULL, 1, 'be5b0db0e5e140eeb2459cdad1ce2efc', 0.00),
(18, 'prueba', '', 3, 100.00, 30, 'year', 1, NULL, NULL, 1, '6a203a85fa3f42308943440008409860', 500.00),
(17, 'test cargo', 'cargo', 3, 200.00, 30, 'month', 1, NULL, NULL, 1, 'f4b5fc92e08c40eb88c938780671fc7b', 500.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `rol_id` int DEFAULT NULL,
  `empresa_id` int DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `password`, `correo`, `rol_id`, `empresa_id`) VALUES
(1, 'Administrador del Sistema', 'admin', '$2y$10$NJDWmXcAB1WaF3LtSobLZe6LC1utb31MfxvQcPB451K3PXDsjiTFu', 'admin@sistema.com', 1, 1),
(3, 'oscar', 'oscar', '$2y$10$4mXac/e2SpoQGf1UrltF0OlZDEAXYGDv0cFfPcYgGy2FfOXDFDGQS', 'jhg@jhg', 1, 2),
(4, 'elisa g', 'elisa', '$2y$10$ffBaEeiySw/zRPuh.FJByuFdGAL9kISKrHY2tFCD8w0vE1XtIzLgy', 'elisa@gmail.com', 1, 2),
(5, 'Prueba', 'Prueba', '$2y$10$Ccgs3fFIu3YY5YE.ah1LCeHt9FSFjvbERaLofRdra6F0fwbrTnn6C', 'pru@prueba.pru', 3, 5),
(6, 'test', 'test', '$2y$10$YyQ8M2FPD86IIsjD0v4iwuLMThAwX3HULsGSOPza1Eu1EYG4fal.6', 'test@prueba.com', 1, 3);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identificador_unico` (`identificador_unico`),
  ADD KEY `idx_clientes_empresa` (`empresa_id`),
  ADD KEY `idx_clientes_identificador` (`identificador_unico`),
  ADD KEY `idx_clientes_correo` (`correo`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_empresas_activo` (`activo`);

--
-- Indices de la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_membresia_id` (`tipo_membresia_id`),
  ADD KEY `idx_membresias_empresa` (`empresa_id`),
  ADD KEY `idx_mp_subscription_id` (`mp_subscription_id`(250)),
  ADD KEY `idx_mp_status` (`mp_status`),
  ADD KEY `idx_mp_payment_method` (`mp_payment_method`),
  ADD KEY `idx_mp_preapproval_id` (`mp_preapproval_id`(250)),
  ADD KEY `idx_mp_plan_id` (`mp_plan_id`(250)),
  ADD KEY `idx_mp_status_sync` (`mp_status`,`mp_last_sync_at`),
  ADD KEY `idx_created_status` (`created_at`,`mp_status`),
  ADD KEY `idx_membresias_cliente` (`cliente_id`),
  ADD KEY `idx_mp_external_reference` (`mp_external_reference`),
  ADD KEY `idx_mp_payment_id` (`mp_payment_id`);

--
-- Indices de la tabla `mp_config`
--
ALTER TABLE `mp_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_key_name` (`key_name`);

--
-- Indices de la tabla `mp_payment_logs`
--
ALTER TABLE `mp_payment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_membresia_id` (`membresia_id`),
  ADD KEY `idx_mp_payment_id` (`mp_payment_id`(250)),
  ADD KEY `idx_mp_subscription_id` (`mp_subscription_id`(250));

--
-- Indices de la tabla `mp_sync_statistics`
--
ALTER TABLE `mp_sync_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_date` (`sync_date`);

--
-- Indices de la tabla `mp_webhooks`
--
ALTER TABLE `mp_webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mp_topic` (`mp_topic`),
  ADD KEY `idx_mp_resource_id` (`mp_resource_id`(250));

--
-- Indices de la tabla `payment_preferences`
--
ALTER TABLE `payment_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `external_reference` (`external_reference`),
  ADD UNIQUE KEY `preference_id` (`preference_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `membresia_id` (`membresia_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tipos_membresia`
--
ALTER TABLE `tipos_membresia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipos_membresia_interval` (`interval`),
  ADD KEY `idx_tipos_membresia_empresa` (`empresa_id`),
  ADD KEY `idx_mp_product_id` (`mp_product_id`(250)),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_mp_plan_id` (`mp_plan_id`(250));

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `rol_id` (`rol_id`),
  ADD KEY `idx_usuarios_empresa` (`empresa_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `membresias`
--
ALTER TABLE `membresias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT de la tabla `mp_config`
--
ALTER TABLE `mp_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `mp_payment_logs`
--
ALTER TABLE `mp_payment_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mp_sync_statistics`
--
ALTER TABLE `mp_sync_statistics`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `mp_webhooks`
--
ALTER TABLE `mp_webhooks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `payment_preferences`
--
ALTER TABLE `payment_preferences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipos_membresia`
--
ALTER TABLE `tipos_membresia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
