-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 10-03-2025 a las 17:48:32
-- Versión del servidor: 8.0.41-cll-lve
-- Versión de PHP: 8.3.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `secureitmxcom_prueba`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `componentes_equipo`
--

CREATE TABLE `componentes_equipo` (
  `id_componente` int NOT NULL,
  `id_equipo` int NOT NULL,
  `tipo_componente` varchar(100) NOT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos_empresa`
--

CREATE TABLE `contactos_empresa` (
  `id_contacto` int NOT NULL,
  `id_empresa` int NOT NULL,
  `contacto` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `contactos_empresa`
--

INSERT INTO `contactos_empresa` (`id_contacto`, `id_empresa`, `contacto`, `telefono`, `correo`) VALUES
(5, 6, 'oscar', '4654654646', 'sadsafdfs@sdfsdf'),
(13, 14, 'contacto', '5512345678', 'correo@correo.com'),
(14, 15, 'sdfsd', 'sdfsdf', 'sdfsdf@xf'),
(16, 17, 'dgf', 'dgf', 'fgdgf@bvnb');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id_empresa` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id_empresa`, `nombre`, `direccion`, `fecha_registro`) VALUES
(6, 'Transportes Jema', 'Atizapan', '2025-03-04 15:16:33'),
(14, 'Nombre empresa', 'direciion', '2025-03-05 18:11:31'),
(15, 'Elisa Company', 'sdfsdf', '2025-03-05 19:03:21'),
(17, 'Mega CDMX', 'dgfdg', '2025-03-06 15:59:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `id_equipo` int NOT NULL,
  `nombre_cliente` varchar(45) NOT NULL,
  `nombre_equipo` varchar(100) NOT NULL,
  `responsable` varchar(100) NOT NULL,
  `sistema_operativo` varchar(100) DEFAULT NULL,
  `direccion_mac` varchar(100) NOT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `ram` varchar(100) DEFAULT NULL,
  `disco_duro` varchar(100) DEFAULT NULL,
  `procesador` varchar(100) DEFAULT NULL,
  `observaciones` text,
  `id_empresa` int DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `equipos`
--

INSERT INTO `equipos` (`id_equipo`, `nombre_cliente`, `nombre_equipo`, `responsable`, `sistema_operativo`, `direccion_mac`, `tipo`, `fabricante`, `modelo`, `numero_serie`, `ram`, `disco_duro`, `procesador`, `observaciones`, `id_empresa`, `fecha_registro`) VALUES
(12, 'Transportes Jema', 'AUXCONTA-MEX', 'Octavio Martinez', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  : 	B0:35:9F:76:11:7 Ethernet  : 00:23:81:28:A1:39', 'Computadora', 'Acer', '	Aspire C22-760', '	DQB7DAL004728004EB6B01', '4 GB', '	TOSHIBA MQ01ABD100  953GB', '	Intel(R) Core(TM) i3-6100U CPU @ 2.30GHz', 'sfdsf', 6, '2025-03-04 15:18:56'),
(13, 'Transportes Jema', '	DESKTOP-3J5H13L', 'Janet Espinoza', '	Microsoft Windows 10 Pro 64-Bit ', 'Wifi  :  44:AF:28:97:C2:B9 Ethernet  :  B0:7B:25:59:7A:87', 'Laptop', '	Dell Inc.', '	Latitude 3510', 'GRCNB63', '8 GB', 'KINGSTON SA400S37240G', '	Intel(R) Core(TM) i5-10210U CPU @ 1.60GHz', '', 6, '2025-03-04 15:18:56'),
(14, 'Transportes Jema', 'DESKTOP-4CV0FHK', '	Janet Ezpinoza', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  74:C6:3B:30:21:40 Ethernet  : D0:17:C2:5D:35:E9', 'Computadora', '	ASUSTeK COMPUTER INC.', 'V220IC', 'G5PTTS023696', '8 GB', 'TOSHIBA DT01ACA100  953GB', 'Intel(R) Core(TM) i3-6100U CPU @ 2.30GHz', '', 6, '2025-03-04 15:18:56'),
(15, 'Transportes Jema', '	DESKTOP-751687S', 'Paulina Vega', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :     60:6D:C7:C8:A6:C7 Ethernet  : 94:57:A5:D4:3E:EC', 'Computadora', 'HP', '23-q143la', '8CC5340DX0', '8 GB', 'KINGSTON SA400S37240G', 'Intel(R) Core(TM) i5-4590T CPU @ 2.00GHz', '', 6, '2025-03-04 15:18:56'),
(16, 'Transportes Jema', 'DESKTOP-MD88RLS', 'Carolina', 'Microsoft Windows 11 Pro 64-Bit', 'Wifi  : 00:42:38:0D:A6:42 Ethernet  : B0:7B:25:48:3C:7E', 'Laptop', '	Dell Inc.', 'Latitude 3510', 'JT0Y863', '8 GB', '	TOSHIBA MQ04ABF100  953GB', 'Intel(R) Core(TM) i5-10210U CPU @ 1.60GHz', '', 6, '2025-03-04 15:18:56'),
(17, 'Transportes Jema', 'MANTENIMIENTO', '	ISMAEL', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :	54:35:30:16:74: Ethernet  : E0:DB:55:C2:60:D4', 'Laptop', 'Dell Inc.', '	Inspiron 3421', '	JDS33Z1', '8 GB', 'KINGSTON SA400S37120G', '	Intel(R) Core(TM) i3-3217U CPU @ 1.80GHz', '', 6, '2025-03-04 15:18:56'),
(18, 'Transportes Jema', 'TRAFICO', '	Ivonne', '	Microsoft Windows 11 Pro 64-Bit', 'Wifi  :  44:AF:28:95:55:A6 Ethernet  :  B0:7B:25:59:80:E9', 'Laptop', 'Dell Inc.', 'Latitude 3510', '	9QCNB63', '8 GB', '	TOSHIBA MQ04ABF100  953GB', 'Intel(R) Core(TM) i5-10210U CPU @ 1.60GHz', '', 6, '2025-03-04 15:18:56'),
(19, 'Transportes Jema', '	VENTASMTMMOVERS', 'Paola', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  	90:32:4B:62:B0:D Ethernet  :', 'Laptop', 'HP', 'HP ENVY x360 Convertible 13-ag0xxx', '8CG82481S1', '4 GB', '	KXG50ZNV256G TOSHIBA  244GB ', '	AMD Ryzen 3 2300U with Radeon Vega Mobile Gfx, 1996MHz', '', 6, '2025-03-04 15:18:56'),
(21, 'Transportes Jema', 'MEX-06*', '	CONTA MEX Sin usuario', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  : 	CE:84:0E:E0:B5:3 Ethernet  : 00:23:81:28:A1:39', 'Computadora', '	Acer', '	Aspire C22-760', 'DQB7DAL005732006BA6B01', '8 GB ', 'WDC WD5000LPVX-22V0TT0   953GB', 'Intel(R) Core(TM) i3-6100U CPU @ 2.30GHz', 'No tiene usuario asignado y no aparece en el Av el equipo era de un contador pero ya no trabaja ', 6, '2025-03-04 15:18:56'),
(39, 'Nombre empresa', '	DESKTOP-3J5H13L', 'Octavio Martinez', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  	60:6D:C7:E:004:EEthernet  : B0:5A:DA:D2:6B00	', 'Computadora', 'HP', '23-q143la', '	8CC5390GDM', '8 GB', 'KINGSTON SUV400S37120G', '	Intel(R) Core(TM) i5-4590T CPU @ 2.00GHz', '', 14, '2025-03-05 18:11:48'),
(40, 'Nombre empresa', '	DESKTOP-3J5H13L', 'Octavio Martinez', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  B0:35:9F:76001:7 Ethernet  : 00:23:81008:A19', 'Computadora', 'Acer', '	Aspire C22-760', '	DQB7DAL004728004EB6B01', '4 GB', '	TOSHIBA MQ01ABD100  953GB', '	Intel(R) Core(TM) i3-6100U CPU @ 2.30GHz', '', 14, '2025-03-05 18:11:48'),
(44, 'Elisa Company', 'Becarios1', 'Elisa', 'Microsoft Windows 10 Pro 64-Bit', 'Wi-Fi : D4-1B-81-64-62-5D    Ethernet: 54-B2-03-F4-45-BA', 'Escritorio', 'HP', 'HP 200 G8 21 All-in-One PC', '1CZ124002H', ' 8GB', '119GB SSD', 'Intel(R) Celeron(R) J4025 CPU @ 2.00GHz   2.00 GHz', 'Prueba', 15, '2025-03-05 19:04:31'),
(45, 'Elisa Company', 'Becarios1', 'Elisa', 'Microsoft Windows 10 Pro 64-Bit', 'Wi-Fi : D4-1B-81-64-62-6D    Ethernet: 54-B2-03-F4-45-BA', 'Escritorio', 'HP', 'HP 200 G8 21 All-in-One PC', '1CZ124002H', ' 8GB', '119GB SSD', 'Intel(R) Celeron(R) J4025 CPU @ 2.00GHz   2.00 GHz', 'Prueba', 15, '2025-03-05 19:04:31'),
(46, 'Elisa Company', 'Becarios2', 'Elisa', 'Microsoft Windows 10 Pro 64-Bit', 'Wi-Fi : D4-1B-81-64-62-7D    Ethernet: 54-B2-03-F4-45-BA', 'Escritorio', 'HP', 'HP 200 G8 21 All-in-One PC', '1CZ124002H', ' 8GB', '119GB SSD', 'Intel(R) Celeron(R) J4025 CPU @ 2.00GHz   2.00 GHz', 'Prueba', 15, '2025-03-05 19:04:31'),
(47, 'Elisa Company', 'Becarios3', 'Elisa', 'Microsoft Windows 10 Pro 64-Bit', 'Wi-Fi : D4-1B-81-64-62-8D    Ethernet: 54-B2-03-F4-45-BA', 'Escritorio', 'HP', 'HP 200 G8 21 All-in-One PC', '1CZ124002H', ' 8GB', '119GB SSD', 'Intel(R) Celeron(R) J4025 CPU @ 2.00GHz   2.00 GHz', 'Prueba', 15, '2025-03-05 19:04:31'),
(48, 'Elisa Company', 'Becarios4', 'Elisa', 'Microsoft Windows 10 Pro 64-Bit', 'Wi-Fi : D4-1B-81-64-62-4D    Ethernet: 54-B2-03-F4-45-BA', 'Escritorio', 'HP', 'HP 200 G8 21 All-in-One PC', '1CZ124002H', ' 8GB', '119GB SSD', 'Intel(R) Celeron(R) J4025 CPU @ 2.00GHz   2.00 GHz', 'Prueba', 15, '2025-03-05 19:04:31'),
(51, 'Mega CDMX', 'SEGMENAR-OPE', 'Euler Ortega', 'Microsoft Windows 11 Home, un solo idioma, 64 bits', 'Wifi  : 60:45:2E:22:FB:32 E6Ethernet:20:88:10:AF:14:4E	', 'All In One', 'DELL', 'Inspiron 24 5430', '5KQVWT3', '8 GB', '512 GB NVMe', 'Intel', '', 17, '2025-03-06 16:00:40'),
(52, 'Mega CDMX', 'SEGMENAR-LAURA', 'Claudia Balandra', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  : 3C:95:09:9A:B2:31 Ethernet  :3C:52:82:D3:2E:87', 'All-in-One', 'HP', '24-g200la', '8CC7180ZMH', '8 GB', '480G SSD', 'APU AMD A6-7310 con gr', '', 17, '2025-03-06 16:00:40'),
(53, 'Mega CDMX', 'SEGMENAR-CC', 'Capitan Granados', 'Microsoft Windows 10 Pro 64-Bit', 'Wifi  :  N/A Ethernet  :F8:B1:56:C4:F4:0C', 'CPU', 'HP', 'OptiPlex 9020', '45PLFZ1', '16 GB', ' 1 TB HDD', 'Intel(R) Core(TM) i7-4770 CPU @ 3.40GHz', '', 17, '2025-03-06 16:00:40'),
(54, 'Mega CDMX', 'SEGMENAR-08', 'Cap. Nery', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  B8:EE:65:53:20:FE Ethernet  : 00:25:AB:53:51:83', 'All-in-One', 'HP', '23-g001la', '5CM4170F99', '8 GB', '240 GB SSD', 'AMD E2-3800 APU with Radeon(TM) HD Graphics, 1297MHz', '', 17, '2025-03-06 16:00:40'),
(55, 'Mega CDMX', 'SEGMENAR-05', 'Recursos Humanos', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  3C:95:09:9B:94:7F Ethernet  :3C:52:82:D3:2D:1C', 'ALL-in-One', 'HP', '24-g200la', '8CC7180ZVL', '8 GB', '240 GB SSD', 'AMD A6-7310 APU with AMD Radeon R4 Graphics, 1996MHz', '', 17, '2025-03-06 16:00:40'),
(56, 'Mega CDMX', 'SEGMEGA-SECUR1', '	Analista RH', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  B0:C0:90:9F:E5:CC Ethernet  : F4:8E:38:95:41:86	', 'ALL -In-  One', 'DELL', 'Inspiron 20-3052', 'Inspiron 20-3052', '8 GB', '240 GB SSD', 'Intel(R) Pentium(R) CPU N3700 @ 1.60GHz', '', 17, '2025-03-06 16:00:40'),
(57, 'Mega CDMX', '	RADIOOPERADOR', '	RADIOPERADOR', '	Microsoft Windows 10 Pro 64-Bit', 'Ethernet :10:FE:ED:03:E1:2B Ethernet : 00:25:64:D8:A8:2B	', 'CPU', 'DELL', '	Vostro 220 Series', '3DL35L1', '4 GB', '500 GB HDD', '	Intel(R) Core(TM)2 Duo CPU E7500 @ 2.93GHz', '', 17, '2025-03-06 16:00:40'),
(58, 'Mega CDMX', '	DESKTOP-N6UHV00', 'contador antonio', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  : 28:E3:47:F0:C6:23 Ethernet  : 00:25:AB:52:43:57	', 'All-in-One', 'HP', '	23-g001la', '	5CM41409LG', '8 GB', '240 GB SSD', '	AMD E2-3800 APU with Radeon(TM) HD Graphics, 1297MHz', '', 17, '2025-03-06 16:00:40'),
(59, 'Mega CDMX', '	DESKTOP-LVACK9P', '	Eduardo Menendez', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  28:39:26:38:EE:5D Ethernet  : 	9C:5A:44:87:7B:F7', 'All-in-One', '	LENOVO', 'F0DN006DLD', 'MP1FQHA8', '8 GB ', '1 TB HDD', '	AMD Ryzen 3 2200GE with Radeon Vega Graphics, 3193MHz', '', 17, '2025-03-06 16:00:40'),
(60, 'Mega CDMX', 'DESKTOP-DMGUDRH', 'Sin usuario', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  : 3C:95:09:9B:46:3B Ethernet  : 3C:52:82:D3:2E:DF	', 'All-in-One', 'HP ', '24-g200la', '	8CC7180ZWC', '4 GB', '1 TB HDD', '	AMD A6-7310 APU with AMD Radeon R4 Graphics, 1996MHz', '', 17, '2025-03-06 16:00:40'),
(61, 'Mega CDMX', '	DESKTOP-215VTLA', '	SEGURIDAD MEGA', '	Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :  2C:D0:5A:CD:37:71 Ethernet  : 30:65:EC:05:5C:68	', 'LAPTOP', 'Acer', '	Aspire V5-431', '	NXM18AL0123250CE266600', '4 GB ', '500 GB HDD ', '	Intel(R) Celeron(R) CPU 1007U @ 1.50GHz', '', 17, '2025-03-06 16:00:40'),
(62, 'Mega CDMX', '	DESKTOP-90NVQUF', 'Sin usuario', '	Microsoft Windows 10 Pro 64-Bit', 'Wifi  :    N/A Ethernet  :00:22:4D:A3:DF:05', 'All-in-One', 'HP ', 'HP W2071d', 'N/A', '6 GB ', '1 TB HDD', '	Intel(R) Atom(TM) CPU D2500 @ 1.86GHz', '', 17, '2025-03-06 16:00:40'),
(63, 'Mega CDMX', 'DESKTOP-4H7GQOQ', 'Juridico', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  : 74:40:BB:E1:87:41 Ethernet  :C8:D9:D2:83:B5:FB', 'ALL-in-One', 'HP', '24-f0xx', '8CC8310QJY', '8 GB', '240G SSD', 'AMD A9-9425 RADEON R5, 5 COMPUTE CORES 2C+3G, 3094MHz', '', 17, '2025-03-06 16:00:40'),
(64, 'Mega CDMX', 'DESKTOP-0UMAVBF', 'Contador Antonio', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :   FC:01:7C:0C:82:E3 Ethernet  :10:E7:C6:F4:ED:C6	', 'ALL-in-One', 'HP', '24-f0xx', '8CC81616PQ', '8 GB', '240G SSD', 'AMD A9-9425 RADEON R5, 5 COMPUTE CORES 2C+3G, 3094MHz', '', 17, '2025-03-06 16:00:40'),
(65, 'Mega CDMX', 'DESKTOP-BS7FS5U', 'Carlos Perez', 'Microsoft Windows 10 Home Single Language 64-Bit', 'Wifi  :   3C:95:09:9B:94:7D Ethernet  :3C:52:82:D3:30:7D	', 'ALL-in-One', 'HP', '24-g200la', '8CC7180ZVS', '4 GB', '480 GB SSD', 'AMD A6-7310 APU with AMD Radeon R4 Graphics, 1996MHz', '', 17, '2025-03-06 16:00:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_actividades`
--

CREATE TABLE `historial_actividades` (
  `id` int NOT NULL,
  `id_usuario` int NOT NULL,
  `nombre_usuario` varchar(100) NOT NULL,
  `accion` varchar(255) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `id_registro` int DEFAULT NULL,
  `detalles` text,
  `fecha_hora` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `historial_actividades`
--

INSERT INTO `historial_actividades` (`id`, `id_usuario`, `nombre_usuario`, `accion`, `tabla_afectada`, `id_registro`, `detalles`, `fecha_hora`) VALUES
(76, 1, 'admin', 'Eliminación de usuario', 'usuarios', 20, 'Se eliminó el usuario: prueba (ID: 20)', '2025-03-10 12:02:42'),
(83, 1, 'admin', 'Actualización de usuario', 'usuarios', 35, 'Se actualizó el usuario ID: 35', '2025-03-10 12:27:45'),
(82, 1, 'admin', 'Creación de usuario', 'usuarios', 35, 'Se creó el usuario: prueba (ID: 35)', '2025-03-10 12:27:35'),
(81, 1, 'admin', 'Eliminación de usuario', 'usuarios', 34, 'Se eliminó el usuario: prueba (ID: 34)', '2025-03-10 12:27:21'),
(80, 34, 'prueba', 'Actualización de usuario', 'usuarios', 34, 'Se actualizó el usuario ID: 34', '2025-03-10 12:07:29'),
(79, 34, 'prueba', 'Actualización de usuario', 'usuarios', 34, 'Se actualizó el usuario ID: 34', '2025-03-10 12:06:54'),
(78, 34, 'prueba', 'Actualización de usuario', 'usuarios', 34, 'Se actualizó el usuario ID: 34', '2025-03-10 12:05:27'),
(77, 1, 'admin', 'Actualización de usuario', 'usuarios', 34, 'Se actualizó el usuario ID: 34', '2025-03-10 12:02:58'),
(75, 1, 'admin', 'Actualización de usuario', 'usuarios', 34, 'Se actualizó el usuario ID: 34', '2025-03-10 12:01:42'),
(74, 1, 'admin', 'Creación de usuario', 'usuarios', 34, 'Se creó el usuario: prueba 2 (ID: 34)', '2025-03-10 12:01:34'),
(73, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario ID: 22', '2025-03-10 11:56:52'),
(72, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario: coronel (ID: 22)', '2025-03-10 11:29:45'),
(71, 1, 'admin', 'Actualización de usuario', 'usuarios', 20, 'Se actualizó el usuario: prueba (ID: 20)\nContraseña actualizada', '2025-03-08 23:09:31'),
(70, 1, 'admin', 'Actualización de usuario', 'usuarios', 20, 'Se actualizó el usuario: prueba (ID: 20)\nNombre: Elisa → prueba\nContraseña actualizada', '2025-03-08 23:07:30'),
(69, 1, 'admin', 'Actualización de usuario', 'usuarios', 20, 'Se actualizó el usuario: Elisa (ID: 20)\nContraseña actualizada', '2025-03-08 15:29:24'),
(68, 1, 'admin', 'Eliminación de usuario', 'usuarios', 25, 'Se eliminó el usuario: oscar (ID: 25)', '2025-03-08 15:21:59'),
(67, 1, 'admin', 'Actualización de usuario', 'usuarios', 25, 'Se actualizó el usuario: oscar (ID: 25)\n', '2025-03-08 15:21:53'),
(66, 1, 'admin', 'Actualización de equipo', 'equipos', 40, 'Se actualizó el equipo ID: 40\n', '2025-03-07 13:33:52'),
(65, 1, 'admin', 'Actualización de equipo', 'equipos', 40, 'Se actualizó el equipo ID: 40\n', '2025-03-07 13:33:41'),
(63, 1, 'admin', 'Actualización de equipo', 'equipos', 39, 'Se actualizó el equipo ID: 39\n', '2025-03-07 13:33:03'),
(64, 1, 'admin', 'Actualización de equipo', 'equipos', 40, 'Se actualizó el equipo ID: 40\n', '2025-03-07 13:33:22'),
(62, 1, 'admin', 'Actualización de usuario', 'usuarios', 25, 'Se actualizó el usuario: oscar (ID: 25)\n', '2025-03-07 12:02:34'),
(61, 1, 'admin', 'Actualización de equipo', 'equipos', 12, 'Se actualizó el equipo ID: 12\n', '2025-03-07 11:20:15'),
(60, 1, 'admin', 'Eliminación de equipo', 'equipos', 11, 'Se eliminó el equipo: 	AUXCONTA-JEMA', '2025-03-07 11:19:33'),
(59, 1, 'admin', 'Actualización de usuario', 'usuarios', 25, 'Se actualizó el usuario: oscar (ID: 25)\n', '2025-03-07 10:48:42'),
(58, 1, 'admin', 'Actualización de usuario', 'usuarios', 25, 'Se actualizó el usuario: oscar (ID: 25)\n', '2025-03-07 10:48:02'),
(57, 1, 'admin', 'Actualización de usuario', 'usuarios', 25, 'Se actualizó el usuario: oscar (ID: 25)\nRol: visualizador → administrador', '2025-03-07 10:29:04'),
(56, 1, 'admin', 'Actualización de usuario', 'usuarios', 25, 'Se actualizó el usuario: oscar (ID: 25)\nRol: administrador → visualizador', '2025-03-07 10:26:23'),
(54, 1, 'admin', 'Eliminación de usuario', 'usuarios', 33, 'Se eliminó el usuario: prueba 2 (ID: 33)', '2025-03-07 10:24:52'),
(55, 1, 'admin', 'Actualización de empresa', 'empresas', 25, 'Se actualizó la empresa: prueba\n', '2025-03-07 10:25:14'),
(84, 1, 'admin', 'Actualización de usuario', 'usuarios', 35, 'Se actualizó el usuario ID: 35', '2025-03-10 12:29:18'),
(85, 1, 'admin', 'Actualización de usuario', 'usuarios', 35, 'Se actualizó el usuario ID: 35', '2025-03-10 12:30:35'),
(86, 1, 'admin', 'Eliminación de usuario', 'usuarios', 35, 'Se eliminó el usuario: prueba (ID: 35)', '2025-03-10 12:44:57'),
(87, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario ID: 22', '2025-03-10 12:51:18'),
(88, 1, 'admin', 'Eliminación de empresa', 'empresas', 25, 'Se eliminó la empresa: prueba', '2025-03-10 12:52:00'),
(89, 1, 'admin', 'Creación de empresa', 'empresas', 26, 'Se creó una nueva empresa: pruebaprueba', '2025-03-10 12:53:34'),
(90, 1, 'admin', 'Actualización de empresa', 'empresas', 26, 'Se actualizó la empresa: pruebaprueba\n', '2025-03-10 12:53:49'),
(91, 1, 'admin', 'Eliminación de empresa', 'empresas', 26, 'Se eliminó la empresa: pruebaprueba', '2025-03-10 13:01:55'),
(92, 1, 'admin', 'Creación de empresa', 'empresas', 27, 'Se creó una nueva empresa: prueba', '2025-03-10 13:06:59'),
(93, 1, 'admin', 'Actualización de empresa', 'empresas', 27, 'Se actualizó la empresa: prueba\n', '2025-03-10 13:13:20'),
(94, 1, 'admin', 'Creación de usuario', 'usuarios', 36, 'Se creó el usuario: Elisa (ID: 36)', '2025-03-10 13:23:30'),
(95, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario ID: 22', '2025-03-10 13:23:43'),
(96, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario ID: 22Nombre: oscar 2', '2025-03-10 13:30:48'),
(97, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario ID: oscar ', '2025-03-10 13:31:35'),
(98, 1, 'admin', 'Creación de usuario', 'usuarios', 37, 'Se creó el usuario: prueba (ID: 37)', '2025-03-10 13:32:20'),
(99, 1, 'admin', 'Eliminación de usuario', 'usuarios', 37, 'Se eliminó el usuario: prueba (ID: 37)', '2025-03-10 13:33:07'),
(100, 1, 'admin', 'Actualización de usuario', 'usuarios', 36, 'Se actualizó el usuario: Elisa', '2025-03-10 13:43:06'),
(101, 1, 'admin', 'Creación de equipo', 'equipos', 77, 'Se creó un nuevo equipo: jhjkk\nResponsable: jhkjh\nTipo: jkh\nModelo: kjh', '2025-03-10 14:30:52'),
(102, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario: oscar ', '2025-03-10 14:31:16'),
(103, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario: oscar 2', '2025-03-10 14:33:40'),
(104, 22, 'oscar 2', 'Actualización de equipo', 'equipos', 77, 'Se actualizó el equipo ID: 77\n', '2025-03-10 14:34:14'),
(105, 22, 'oscar 2', 'Eliminación de equipo', 'equipos', 77, 'Se eliminó el equipo: jhjkk', '2025-03-10 14:34:24'),
(106, 22, 'oscar 2', 'Creación de empresa', 'empresas', 28, 'Se creó una nueva empresa: prueba 2', '2025-03-10 14:34:42'),
(107, 22, 'oscar 2', 'Eliminación de empresa', 'empresas', 28, 'Se eliminó la empresa: prueba 2', '2025-03-10 14:34:48'),
(108, 22, 'oscar 2', 'Actualización de empresa', 'empresas', 27, 'Se actualizó la empresa: prueba2\nNombre: prueba → prueba2\nContacto: prueba → prueba 2', '2025-03-10 14:35:25'),
(109, 22, 'oscar 2', 'Creación de equipo', 'equipos', 78, 'Se creó un nuevo equipo: hg\nResponsable: jhg\nTipo: hgj\nModelo: gjh', '2025-03-10 14:35:53'),
(110, 22, 'oscar 2', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario: oscar', '2025-03-10 14:36:16'),
(111, 22, 'oscar', 'Eliminación de equipo', 'equipos', 78, 'Se eliminó el equipo: hg', '2025-03-10 14:36:46'),
(112, 22, 'oscar', 'Eliminación de empresa', 'empresas', 27, 'Se eliminó la empresa: prueba2', '2025-03-10 14:36:56'),
(113, 1, 'admin', 'Actualización de contraseña', 'usuarios', 1, 'Se actualizó la contraseña del usuario admin', '2025-03-10 14:45:17'),
(114, 1, 'admin', 'Actualización de usuario', 'usuarios', 36, 'Se actualizó el usuario: Elisa', '2025-03-10 14:45:37'),
(115, 36, 'Elisa', 'Actualización de contraseña', 'usuarios', 1, 'Se actualizó la contraseña del usuario admin', '2025-03-10 14:45:53'),
(116, 36, 'Elisa', 'Actualización de contraseña', 'usuarios', 1, 'Se actualizó la contraseña del usuario admin', '2025-03-10 14:46:04'),
(117, 36, 'Elisa', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario: oscar', '2025-03-10 14:47:22'),
(118, 1, 'admin', 'Actualización de usuario', 'usuarios', 22, 'Se actualizó el usuario: oscar', '2025-03-10 15:02:08'),
(119, 22, 'oscar', 'Actualización de contraseña', 'usuarios', 1, 'Se actualizó la contraseña del usuario admin', '2025-03-10 15:02:46'),
(120, 22, 'oscar', 'Actualización de contraseña', 'usuarios', 1, 'Se actualizó la contraseña del usuario admin', '2025-03-10 15:08:55'),
(121, 1, 'admin', 'Actualización de usuario', 'usuarios', 36, 'Se actualizó el usuario: Elisa', '2025-03-10 15:16:02'),
(122, 1, 'admin', 'Creación de usuario', 'usuarios', 38, 'Se creó el usuario: prueba (ID: 38)', '2025-03-10 17:35:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `rol` enum('administrador','visualizador') NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_usuario`, `contraseña`, `rol`, `fecha_creacion`) VALUES
(1, 'admin', '$2y$10$gAzseX0NfJMP7DmKKNob..uXn3qOhkEnSXW2SWUiy8RmIK5F6kDku', 'administrador', '2025-02-04 18:27:48'),
(22, 'oscar', '$2y$10$jLuBKtEKGg4R9AjW3fNH/Oa0PPDuwFbMh69dLtEFiBB4aHIiEQxZ.', 'administrador', '2025-02-10 18:18:04'),
(36, 'Elisa', '$2y$10$My5c4OGtdkMW7byQ1vnPB.LQvT8OWmQcNn4IIaADWRcdPM8qYfcom', 'visualizador', '2025-03-10 17:23:30'),
(38, 'prueba', '$2y$10$QPLnyefqXM11f1gM7o1s1eK/OdR9EmwZM6nwQQsGOGihGHOzCApKy', 'visualizador', '2025-03-10 21:35:44');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `componentes_equipo`
--
ALTER TABLE `componentes_equipo`
  ADD PRIMARY KEY (`id_componente`),
  ADD KEY `id_equipo` (`id_equipo`);

--
-- Indices de la tabla `contactos_empresa`
--
ALTER TABLE `contactos_empresa`
  ADD PRIMARY KEY (`id_contacto`),
  ADD KEY `id_empresa` (`id_empresa`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id_empresa`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`),
  ADD UNIQUE KEY `direccion_mac` (`direccion_mac`),
  ADD KEY `id_empresa` (`id_empresa`);

--
-- Indices de la tabla `historial_actividades`
--
ALTER TABLE `historial_actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

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
-- AUTO_INCREMENT de la tabla `componentes_equipo`
--
ALTER TABLE `componentes_equipo`
  MODIFY `id_componente` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contactos_empresa`
--
ALTER TABLE `contactos_empresa`
  MODIFY `id_contacto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id_empresa` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT de la tabla `historial_actividades`
--
ALTER TABLE `historial_actividades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `componentes_equipo`
--
ALTER TABLE `componentes_equipo`
  ADD CONSTRAINT `componentes_equipo_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`);

--
-- Filtros para la tabla `contactos_empresa`
--
ALTER TABLE `contactos_empresa`
  ADD CONSTRAINT `contactos_empresa_ibfk_1` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id_empresa`);

--
-- Filtros para la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id_empresa`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
