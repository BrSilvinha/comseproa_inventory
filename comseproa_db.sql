-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-03-2025 a las 19:47:05
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `comseproa_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--

CREATE TABLE `almacenes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ubicacion` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `almacenes`
--

INSERT INTO `almacenes` (`id`, `nombre`, `ubicacion`) VALUES
(1, 'Grupo Sael - Lambayeque', 'Lambayeque - Chiclayo'),
(2, 'Grupo Sael - Olmos', 'Olmos - Lambayeque');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`) VALUES
(2, 'Accesorios de Seguridad'),
(3, 'Kebras y Fundas Nuevas'),
(1, 'Ropa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrega_uniformes`
--

CREATE TABLE `entrega_uniformes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre_destinatario` varchar(100) DEFAULT NULL,
  `dni_destinatario` varchar(8) DEFAULT NULL,
  `almacen_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `fecha_entrega` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `almacen_origen` int(11) DEFAULT NULL,
  `almacen_destino` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo` enum('entrada','salida','transferencia') NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','completado','rechazado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `talla_dimensiones` varchar(50) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `unidad_medida` varchar(50) DEFAULT NULL,
  `estado` enum('Nuevo','Usado','Dañado') NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `categoria_id`, `almacen_id`, `nombre`, `descripcion`, `modelo`, `color`, `talla_dimensiones`, `cantidad`, `unidad_medida`, `estado`, `observaciones`) VALUES
(51, 1, 1, 'Camisa Polipima', NULL, 'Manga larga', 'Blanco', 'XXL', 1, 'Unidad', 'Nuevo', ''),
(52, 1, 1, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'M', 1, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(53, 1, 1, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'L', 6, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(54, 1, 1, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'S', 2, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(55, 1, 1, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'XL', 1, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(56, 1, 1, 'Pantalon drill', NULL, 'Varon', 'Azul', '34', 1, 'Unidad', 'Nuevo', ''),
(57, 1, 1, 'Pantalon drill', NULL, 'Varon', 'Azul', '36', 3, 'Unidad', 'Nuevo', ''),
(58, 1, 1, 'Pantalon drill', NULL, 'Varon', 'Azul', '38', 3, 'Unidad', 'Nuevo', ''),
(59, 1, 1, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '32', 2, 'Unidad', 'Nuevo', ''),
(60, 1, 1, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '36', 1, 'Unidad', 'Nuevo', ''),
(61, 1, 1, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '34', 2, 'Unidad', 'Nuevo', ''),
(62, 1, 1, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', 'L', 119, 'Unidad', 'Nuevo', 'SIN BOTON'),
(63, 1, 1, 'Pantalon azul c/r', NULL, 'Varon', 'Azul', 'M', 20, 'Unidad', 'Nuevo', ''),
(64, 1, 1, 'Pantalon azul c/r', NULL, 'Varon', 'Azul', 'L', 59, 'Unidad', 'Nuevo', ''),
(65, 1, 1, 'Pantalon azul c/r', NULL, 'Varon', 'Azul', 'XL', 15, 'Unidad', 'Nuevo', ''),
(66, 1, 1, 'Polo camisero TACTICO M/L', NULL, 'Varon', 'Azul', 'L', 7, 'Unidad', 'Nuevo', ''),
(67, 1, 1, 'Chaleco supervisor', NULL, 'Varon', 'Plomo', 'L', 1, 'Unidad', 'Nuevo', ''),
(68, 1, 1, 'Casaca Drill', NULL, 'Varon', 'Azul', 'L', 10, 'Unidad', 'Nuevo', ''),
(69, 1, 1, 'Gorras', NULL, 'Varon', 'Azul', NULL, 13, 'Unidad', 'Nuevo', ''),
(70, 1, 1, 'Corbatas', NULL, 'Varon', 'Guinda', NULL, 19, 'Unidad', 'Nuevo', ''),
(71, 1, 1, 'Corbatas', NULL, 'Varon', 'Marrones', NULL, 21, 'Unidad', 'Nuevo', ''),
(72, 1, 1, 'Borseguis', NULL, 'Varon', 'Negro', '41', 2, 'Pares', 'Nuevo', ''),
(73, 1, 1, 'Borseguis', NULL, 'Varon', 'Negro', '43', 1, 'Pares', 'Nuevo', 'Ingreso el 17/03'),
(74, 2, 1, 'Portapistola', NULL, NULL, 'Negro', NULL, 2, 'Unidad', 'Nuevo', ''),
(75, 2, 1, 'Correajes', NULL, NULL, 'Negro', NULL, 14, 'Unidad', 'Nuevo', ''),
(76, 2, 1, 'Cascos de Seguridad', NULL, NULL, 'Blanco', NULL, 6, 'Unidad', 'Nuevo', 'INGRESO EL 17/03'),
(77, 2, 1, 'Lentes', NULL, NULL, NULL, NULL, 6, 'Unidad', 'Nuevo', 'INGRESO EL 17/03'),
(78, 2, 1, 'Guantes', NULL, NULL, NULL, NULL, 2, 'Pares', 'Nuevo', 'INGRESO EL 17/03'),
(79, 3, 1, 'Fundas', NULL, NULL, 'Azul', 'L', 16, 'Unidad', 'Nuevo', ''),
(80, 1, 2, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'M', 2, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(81, 1, 2, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'XL', 2, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(82, 1, 2, 'Camisa Polipima', NULL, 'Manga larga', 'Blanco', 'XXL', 1, 'Unidad', 'Nuevo', ''),
(83, 1, 2, 'Pantalon drill', NULL, 'Varon', 'Azul', '34', 1, 'Unidad', 'Nuevo', ''),
(84, 1, 2, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', 'L', 2, 'Unidad', 'Nuevo', 'SIN BOTON');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_transferencia`
--

CREATE TABLE `solicitudes_transferencia` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `almacen_origen` int(11) NOT NULL,
  `almacen_destino` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `usuario_aprobador_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(64) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `celular` varchar(15) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `almacen_id` int(11) DEFAULT NULL,
  `rol` enum('admin','almacenero') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `dni`, `celular`, `direccion`, `correo`, `contrasena`, `almacen_id`, `rol`, `estado`, `fecha_registro`) VALUES
(2, 'Jhamir Alexander', 'Silva Baldera', '71749437', '982566142', 'jhamirsilva@gmail.com', 'jhamirsilva@gmail.com', '$2y$10$1yejJGgM2shtBXIq9WfuaO8E1hF7ksm6h.LoDvoCvkI2nLcXqir0S', 1, 'admin', 'activo', '2025-03-19 14:47:41'),
(4, 'Javier Agustin', 'Silva De La Cruz', '17577855', '987654321', 'Garcilazo de la vega 673', 'javier@gmail.com', '$2y$10$Rfyl9ZC1ZFn.E0kKA812gOMvlAXF2bApclsA/wC7fBMoeQHold4PS', 2, 'admin', 'activo', '2025-03-22 14:56:42'),
(5, 'almacenero', 'chicalyo', '12343234', '123443234', 'prueba01', 'almacenerocix@gmail.com', '$2y$10$jqBJ8SejYjG1TCKjitkp/uekhDC5c9OhJf6gTW2xsDpvZWD1daEHO', 1, 'almacenero', 'activo', '2025-03-22 16:17:37'),
(6, 'almacenero', 'olmos', '12312323', '543234321', 'prueba02', 'almaceneroolmos@gmail.com', '$2y$10$rOQK2skh5H/Iqtgeas.qIeC90k9dyXFCb2J19z0r/Y/eA6GXeYQBy', 2, 'almacenero', 'activo', '2025-03-22 16:19:28');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `almacen_id` (`almacen_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_origen` (`almacen_origen`),
  ADD KEY `almacen_destino` (`almacen_destino`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_producto_almacen` (`nombre`,`color`,`talla_dimensiones`,`almacen_id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- Indices de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_origen` (`almacen_origen`),
  ADD KEY `almacen_destino` (`almacen_destino`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_usuario_aprobador` (`usuario_aprobador_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  ADD CONSTRAINT `entrega_uniformes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entrega_uniformes_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entrega_uniformes_ibfk_3` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  ADD CONSTRAINT `fk_usuario_aprobador` FOREIGN KEY (`usuario_aprobador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_2` FOREIGN KEY (`almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
