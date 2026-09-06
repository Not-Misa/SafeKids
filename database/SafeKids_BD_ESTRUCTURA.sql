-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: safekids_v2
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `safekids_v2`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `safekids_v2` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `safekids_v2`;

--
-- Table structure for table `asistencias`
--

DROP TABLE IF EXISTS `asistencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asistencias` (
  `id_asistencia` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned NOT NULL,
  `id_nino` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` datetime DEFAULT NULL,
  `hora_salida` datetime DEFAULT NULL,
  `id_tutor_entrega` bigint unsigned DEFAULT NULL,
  `id_contacto_entrega` bigint unsigned DEFAULT NULL,
  `nombre_otro_entrega` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_tutor_recoge` bigint unsigned DEFAULT NULL,
  `id_contacto_recoge` bigint unsigned DEFAULT NULL,
  `nombre_otro_recoge` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_empleado_entrada` bigint unsigned DEFAULT NULL,
  `id_empleado_salida` bigint unsigned DEFAULT NULL,
  `observaciones` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_asistencia`),
  UNIQUE KEY `uq_asistencia_nino_fecha` (`id_nino`,`fecha`),
  KEY `fk_asistencias_tutor_entrega` (`id_tutor_entrega`),
  KEY `fk_asistencias_contacto_entrega` (`id_contacto_entrega`),
  KEY `fk_asistencias_tutor_recoge` (`id_tutor_recoge`),
  KEY `fk_asistencias_contacto_recoge` (`id_contacto_recoge`),
  KEY `fk_asistencias_empleado_entrada` (`id_empleado_entrada`),
  KEY `fk_asistencias_empleado_salida` (`id_empleado_salida`),
  KEY `idx_asistencias_guarderia_fecha` (`id_guarderia`,`fecha`),
  KEY `idx_asistencias_nino_fecha` (`id_nino`,`fecha`),
  CONSTRAINT `fk_asistencias_contacto_entrega` FOREIGN KEY (`id_contacto_entrega`) REFERENCES `contactos_autorizados` (`id_contacto`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_contacto_recoge` FOREIGN KEY (`id_contacto_recoge`) REFERENCES `contactos_autorizados` (`id_contacto`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_empleado_entrada` FOREIGN KEY (`id_empleado_entrada`) REFERENCES `empleados` (`id_empleado`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_empleado_salida` FOREIGN KEY (`id_empleado_salida`) REFERENCES `empleados` (`id_empleado`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_nino` FOREIGN KEY (`id_nino`) REFERENCES `ninos` (`id_nino`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_tutor_entrega` FOREIGN KEY (`id_tutor_entrega`) REFERENCES `tutores` (`id_tutor`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_tutor_recoge` FOREIGN KEY (`id_tutor_recoge`) REFERENCES `tutores` (`id_tutor`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora` (
  `id_bitacora` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned DEFAULT NULL,
  `id_usuario` bigint unsigned DEFAULT NULL,
  `accion` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_entidad` bigint unsigned DEFAULT NULL,
  `detalles_json` json DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_bitacora`),
  KEY `idx_bitacora_guarderia_fecha` (`id_guarderia`,`creado_en`),
  KEY `idx_bitacora_usuario_fecha` (`id_usuario`,`creado_en`),
  KEY `idx_bitacora_entidad` (`entidad`,`id_entidad`),
  CONSTRAINT `fk_bitacora_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contactos_autorizados`
--

DROP TABLE IF EXISTS `contactos_autorizados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactos_autorizados` (
  `id_contacto` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parentesco` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_public_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identificacion_referencia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_contacto`),
  KEY `idx_contactos_guarderia_nombre` (`id_guarderia`,`apellidos`,`nombres`),
  CONSTRAINT `fk_contactos_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dispositivos_fcm`
--

DROP TABLE IF EXISTS `dispositivos_fcm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispositivos_fcm` (
  `id_dispositivo` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint unsigned NOT NULL,
  `token_fcm` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plataforma` enum('ANDROID','WEB') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ANDROID',
  `nombre_dispositivo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_uso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_dispositivo`),
  UNIQUE KEY `uq_dispositivos_token` (`token_fcm`),
  KEY `idx_dispositivos_usuario_activo` (`id_usuario`,`activo`),
  CONSTRAINT `fk_dispositivos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `empleados`
--

DROP TABLE IF EXISTS `empleados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleados` (
  `id_empleado` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint unsigned NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puesto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `foto_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_public_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `uq_empleados_usuario` (`id_usuario`),
  KEY `idx_empleados_nombre` (`apellidos`,`nombres`),
  CONSTRAINT `fk_empleados_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eventos_nino`
--

DROP TABLE IF EXISTS `eventos_nino`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eventos_nino` (
  `id_evento` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned NOT NULL,
  `id_nino` bigint unsigned NOT NULL,
  `id_tipo_evento` smallint unsigned NOT NULL,
  `id_empleado` bigint unsigned NOT NULL,
  `fecha_hora_evento` datetime NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `detalle_json` json DEFAULT NULL,
  `nivel` enum('INFORMATIVO','IMPORTANTE','URGENTE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INFORMATIVO',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_evento`),
  KEY `fk_eventos_empleado` (`id_empleado`),
  KEY `idx_eventos_nino_fecha` (`id_nino`,`fecha_hora_evento`),
  KEY `idx_eventos_guarderia_fecha` (`id_guarderia`,`fecha_hora_evento`),
  KEY `idx_eventos_tipo_fecha` (`id_tipo_evento`,`fecha_hora_evento`),
  CONSTRAINT `fk_eventos_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_eventos_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_eventos_nino` FOREIGN KEY (`id_nino`) REFERENCES `ninos` (`id_nino`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_eventos_tipo` FOREIGN KEY (`id_tipo_evento`) REFERENCES `tipos_evento` (`id_tipo_evento`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expedientes_medicos`
--

DROP TABLE IF EXISTS `expedientes_medicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expedientes_medicos` (
  `id_expediente` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_nino` bigint unsigned NOT NULL,
  `tipo_sangre` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alergias` text COLLATE utf8mb4_unicode_ci,
  `padecimientos` text COLLATE utf8mb4_unicode_ci,
  `medicamentos_habituales` text COLLATE utf8mb4_unicode_ci,
  `restricciones_alimentarias` text COLLATE utf8mb4_unicode_ci,
  `medico_nombre` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medico_telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institucion_medica` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_seguro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indicaciones_emergencia` text COLLATE utf8mb4_unicode_ci,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_expediente`),
  UNIQUE KEY `uq_expediente_nino` (`id_nino`),
  CONSTRAINT `fk_expediente_nino` FOREIGN KEY (`id_nino`) REFERENCES `ninos` (`id_nino`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guarderias`
--

DROP TABLE IF EXISTS `guarderias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guarderias` (
  `id_guarderia` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_postal` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zona_horaria` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'America/Monterrey',
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_public_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA','SUSPENDIDA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_guarderia`),
  KEY `idx_guarderias_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `imagenes_evento`
--

DROP TABLE IF EXISTS `imagenes_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `imagenes_evento` (
  `id_imagen` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_evento` bigint unsigned NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` tinyint unsigned NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_imagen`),
  KEY `idx_imagenes_evento` (`id_evento`,`orden`),
  CONSTRAINT `fk_imagenes_evento` FOREIGN KEY (`id_evento`) REFERENCES `eventos_nino` (`id_evento`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nino_contacto`
--

DROP TABLE IF EXISTS `nino_contacto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nino_contacto` (
  `id_nino_contacto` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_nino` bigint unsigned NOT NULL,
  `id_contacto` bigint unsigned NOT NULL,
  `es_contacto_emergencia` tinyint(1) NOT NULL DEFAULT '1',
  `autorizado_recoger` tinyint(1) NOT NULL DEFAULT '0',
  `prioridad_emergencia` tinyint unsigned DEFAULT NULL,
  `observaciones` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_nino_contacto`),
  UNIQUE KEY `uq_nino_contacto` (`id_nino`,`id_contacto`),
  KEY `idx_nino_contacto_nino` (`id_nino`),
  KEY `idx_nino_contacto_contacto` (`id_contacto`),
  CONSTRAINT `fk_nino_contacto_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos_autorizados` (`id_contacto`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_nino_contacto_nino` FOREIGN KEY (`id_nino`) REFERENCES `ninos` (`id_nino`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_nino_contacto_prioridad` CHECK (((`prioridad_emergencia` is null) or (`prioridad_emergencia` between 1 and 9)))
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nino_tutor`
--

DROP TABLE IF EXISTS `nino_tutor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nino_tutor` (
  `id_nino_tutor` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_nino` bigint unsigned NOT NULL,
  `id_tutor` bigint unsigned NOT NULL,
  `parentesco` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `recibe_notificaciones` tinyint(1) NOT NULL DEFAULT '1',
  `autorizado_recoger` tinyint(1) NOT NULL DEFAULT '1',
  `prioridad_contacto` tinyint unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_nino_tutor`),
  UNIQUE KEY `uq_nino_tutor` (`id_nino`,`id_tutor`),
  KEY `idx_nino_tutor_tutor` (`id_tutor`),
  KEY `idx_nino_tutor_nino` (`id_nino`),
  CONSTRAINT `fk_nino_tutor_nino` FOREIGN KEY (`id_nino`) REFERENCES `ninos` (`id_nino`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_nino_tutor_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tutores` (`id_tutor`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_nino_tutor_prioridad` CHECK (((`prioridad_contacto` is null) or (`prioridad_contacto` between 1 and 9)))
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ninos`
--

DROP TABLE IF EXISTS `ninos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ninos` (
  `id_nino` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `genero` enum('FEMENINO','MASCULINO','OTRO','NO_ESPECIFICADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NO_ESPECIFICADO',
  `foto_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_public_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_qr` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','EGRESADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `fecha_ingreso` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_nino`),
  UNIQUE KEY `uq_ninos_codigo_qr` (`codigo_qr`),
  KEY `idx_ninos_guarderia_nombre` (`id_guarderia`,`apellidos`,`nombres`),
  KEY `idx_ninos_guarderia_estado` (`id_guarderia`,`estado`),
  CONSTRAINT `fk_ninos_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificacion_destinatarios`
--

DROP TABLE IF EXISTS `notificacion_destinatarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion_destinatarios` (
  `id_destinatario` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_notificacion` bigint unsigned NOT NULL,
  `id_usuario` bigint unsigned NOT NULL,
  `enviada_push` tinyint(1) NOT NULL DEFAULT '0',
  `enviada_push_en` datetime DEFAULT NULL,
  `vista` tinyint(1) NOT NULL DEFAULT '0',
  `vista_en` datetime DEFAULT NULL,
  `confirmada` tinyint(1) NOT NULL DEFAULT '0',
  `confirmada_en` datetime DEFAULT NULL,
  `respuesta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_destinatario`),
  UNIQUE KEY `uq_notificacion_usuario` (`id_notificacion`,`id_usuario`),
  KEY `idx_destinatarios_usuario_vista` (`id_usuario`,`vista`,`creado_en`),
  CONSTRAINT `fk_destinatarios_notificacion` FOREIGN KEY (`id_notificacion`) REFERENCES `notificaciones` (`id_notificacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_destinatarios_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id_notificacion` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned NOT NULL,
  `id_empleado` bigint unsigned NOT NULL,
  `id_tipo_notificacion` smallint unsigned NOT NULL,
  `id_nino` bigint unsigned DEFAULT NULL,
  `alcance` enum('GLOBAL','PERSONAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridad` enum('NORMAL','IMPORTANTE','URGENTE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NORMAL',
  `imagen_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_public_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_confirmacion` tinyint(1) NOT NULL DEFAULT '0',
  `estado` enum('BORRADOR','PUBLICADA','CANCELADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PUBLICADA',
  `publicada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `fk_notificaciones_empleado` (`id_empleado`),
  KEY `fk_notificaciones_tipo` (`id_tipo_notificacion`),
  KEY `idx_notificaciones_guarderia_fecha` (`id_guarderia`,`publicada_en`),
  KEY `idx_notificaciones_nino_fecha` (`id_nino`,`publicada_en`),
  KEY `idx_notificaciones_estado` (`estado`),
  CONSTRAINT `fk_notificaciones_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_notificaciones_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_notificaciones_nino` FOREIGN KEY (`id_nino`) REFERENCES `ninos` (`id_nino`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_notificaciones_tipo` FOREIGN KEY (`id_tipo_notificacion`) REFERENCES `tipos_notificacion` (`id_tipo_notificacion`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `refresh_tokens`
--

DROP TABLE IF EXISTS `refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refresh_tokens` (
  `id_refresh_token` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expira_en` datetime NOT NULL,
  `revocado_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_refresh_token`),
  UNIQUE KEY `uq_refresh_token_hash` (`token_hash`),
  KEY `idx_refresh_usuario_expira` (`id_usuario`,`expira_en`),
  CONSTRAINT `fk_refresh_tokens_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id_rol` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_evento`
--

DROP TABLE IF EXISTS `tipos_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_evento` (
  `id_tipo_evento` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_detalle` tinyint(1) NOT NULL DEFAULT '0',
  `permite_imagen` tinyint(1) NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_tipo_evento`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_notificacion`
--

DROP TABLE IF EXISTS `tipos_notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_notificacion` (
  `id_tipo_notificacion` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_tipo_notificacion`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tutores`
--

DROP TABLE IF EXISTS `tutores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutores` (
  `id_tutor` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint unsigned NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono_alterno` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_public_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_tutor`),
  UNIQUE KEY `uq_tutores_usuario` (`id_usuario`),
  KEY `idx_tutores_nombre` (`apellidos`,`nombres`),
  CONSTRAINT `fk_tutores_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_usuario` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_guarderia` bigint unsigned DEFAULT NULL,
  `id_rol` tinyint unsigned NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requiere_cambio_password` tinyint(1) NOT NULL DEFAULT '1',
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `intentos_fallidos` tinyint unsigned NOT NULL DEFAULT '0',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  KEY `fk_usuarios_rol` (`id_rol`),
  KEY `idx_usuarios_guarderia_rol` (`id_guarderia`,`id_rol`),
  KEY `idx_usuarios_estado` (`estado`),
  CONSTRAINT `fk_usuarios_guarderia` FOREIGN KEY (`id_guarderia`) REFERENCES `guarderias` (`id_guarderia`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'safekids_v2'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-06 21:57:24
