-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 03-07-2025 a las 12:53:31
-- Versión del servidor: 8.0.42-cll-lve
-- Versión de PHP: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `grupo_gk`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `rol` enum('administrador','visualizador') NOT NULL,
  `google_auth_secret` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_usuario`, `contraseña`, `rol`, `google_auth_secret`, `fecha_creacion`, `email`) VALUES
(1, 'admin', '$2y$10$DimGtbXWQzpvv6KOZjNnTuBFV2pJIWGCjKlHfSdGW8GJrpWF.nxsS', 'administrador', 'ZUUHECNVWZMBRR3F', '2025-02-04 18:27:48', NULL),
(43, 'Monse', '$2y$10$YkF7qLvVjxlzDmNM8ymMvOSMEmpm0581rexGg5.Vq//aHPCgta2jW', 'administrador', 'XX2VKCBRLAZSJ74N', '2025-03-20 17:32:57', 'mgonzalez@seciriti.info'),
(44, 'Alan', '$2y$10$2Ol3nvu220M3YaQrkxEN8OBMY2l01ddpwryQtKsJlKR7dIiPNOzVW', 'administrador', 'TLBT2VNZD6WWTICF', '2025-03-24 23:05:10', 'aramirez@securiti.info'),
(45, 'Alejandro', '$2y$10$VP4cNVnHOD5EwEG1aeGMSO5mbfhOHaF6QxHIHSV/qws93JnuaWGjS', 'administrador', 'MTDMNFIADJB2SEIW', '2025-06-03 16:47:45', 'abasilio@securiti.com.mx'),
(46, 'Juan', '$2y$10$9dygiT5wxGunBvZURD1j2uih5CHNzcworHYLBiGsYaVHQFvMSPE/q', 'administrador', 'KKKX2B7MGZUDPKLH', '2025-06-03 17:09:38', 'jjimenez@securiti.com.mx'),
(47, 'Cesar', '$2y$10$o0TFHb6e04pnO52bIx40DO5cdGTy5nWPE7xD2PN9yHnjXQPS7cBdC', 'administrador', NULL, '2025-06-03 17:12:16', 'cguerrero@securiti.com.mx');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
