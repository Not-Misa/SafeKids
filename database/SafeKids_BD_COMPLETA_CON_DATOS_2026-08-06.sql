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
-- Dumping data for table `asistencias`
--
-- ORDER BY:  `id_asistencia`

LOCK TABLES `asistencias` WRITE;
/*!40000 ALTER TABLE `asistencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `asistencias` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `bitacora`
--
-- ORDER BY:  `id_bitacora`

LOCK TABLES `bitacora` WRITE;
/*!40000 ALTER TABLE `bitacora` DISABLE KEYS */;
/*!40000 ALTER TABLE `bitacora` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `contactos_autorizados`
--
-- ORDER BY:  `id_contacto`

LOCK TABLES `contactos_autorizados` WRITE;
/*!40000 ALTER TABLE `contactos_autorizados` DISABLE KEYS */;
INSERT INTO `contactos_autorizados` (`id_contacto`, `id_guarderia`, `nombres`, `apellidos`, `parentesco`, `telefono`, `email`, `direccion`, `foto_url`, `foto_public_id`, `identificacion_referencia`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (1,1,'Ana','López','Abuela','8144444444',NULL,NULL,NULL,NULL,NULL,'2026-06-25 04:47:00','2026-06-25 04:47:00',NULL),(12,1,'Angel Misael','Vargas Mendez','Hermano','8126064387',NULL,NULL,NULL,NULL,NULL,'2026-07-23 05:03:18','2026-07-23 05:03:18',NULL);
/*!40000 ALTER TABLE `contactos_autorizados` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `dispositivos_fcm`
--
-- ORDER BY:  `id_dispositivo`

LOCK TABLES `dispositivos_fcm` WRITE;
/*!40000 ALTER TABLE `dispositivos_fcm` DISABLE KEYS */;
INSERT INTO `dispositivos_fcm` (`id_dispositivo`, `id_usuario`, `token_fcm`, `plataforma`, `nombre_dispositivo`, `activo`, `ultimo_uso`, `creado_en`, `actualizado_en`) VALUES (2,9,'ch2fUQcLTOuBdwLNtFG_LP','ANDROID','Google sdk_gphone64_x86_64',0,'2026-07-22 21:21:29','2026-07-19 18:43:47','2026-07-23 03:36:42'),(10,9,'c5FNcgQ8RyuptCoYpR8p6s','ANDROID','Xiaomi 23049PCD8G',1,'2026-07-22 22:11:21','2026-07-23 04:10:04','2026-07-23 04:11:21'),(14,32,'fglC365yRx63zqL-mn7qtw','ANDROID','Xiaomi 24116RACCG',1,'2026-07-22 23:13:41','2026-07-23 05:08:57','2026-07-23 05:13:41');
/*!40000 ALTER TABLE `dispositivos_fcm` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `empleados`
--
-- ORDER BY:  `id_empleado`

LOCK TABLES `empleados` WRITE;
/*!40000 ALTER TABLE `empleados` DISABLE KEYS */;
INSERT INTO `empleados` (`id_empleado`, `id_usuario`, `nombres`, `apellidos`, `telefono`, `puesto`, `fecha_ingreso`, `foto_url`, `foto_public_id`, `notas`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (1,1,'María','González','8111111111','Directora','2026-01-10',NULL,NULL,NULL,'2026-06-25 04:47:00','2026-06-25 04:47:00',NULL),(2,2,'Laura','Martínez','8122222222','Cuidadora','2026-02-01',NULL,NULL,NULL,'2026-06-25 04:47:00','2026-06-25 04:47:00',NULL);
/*!40000 ALTER TABLE `empleados` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `eventos_nino`
--
-- ORDER BY:  `id_evento`

LOCK TABLES `eventos_nino` WRITE;
/*!40000 ALTER TABLE `eventos_nino` DISABLE KEYS */;
INSERT INTO `eventos_nino` (`id_evento`, `id_guarderia`, `id_nino`, `id_tipo_evento`, `id_empleado`, `fecha_hora_evento`, `titulo`, `descripcion`, `detalle_json`, `nivel`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (7,1,9,1,1,'2026-07-13 20:19:36','Comida completa','Mateo comio correctamente durante la prueba de la app movil.','{\"alimento\": \"Comida de prueba\", \"cantidad_consumida\": \"Completa\"}','INFORMATIVO','2026-07-14 02:19:36','2026-07-14 02:19:36',NULL),(12,1,19,7,1,'2026-07-22 23:20:00','Aylin castrosa','Se ha comportado igual de castrosa que siempre, todo normal.','{\"estado_animo\": \"Irritable\"}','INFORMATIVO','2026-07-23 05:22:58','2026-07-23 05:22:58',NULL);
/*!40000 ALTER TABLE `eventos_nino` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `expedientes_medicos`
--
-- ORDER BY:  `id_expediente`

LOCK TABLES `expedientes_medicos` WRITE;
/*!40000 ALTER TABLE `expedientes_medicos` DISABLE KEYS */;
INSERT INTO `expedientes_medicos` (`id_expediente`, `id_nino`, `tipo_sangre`, `alergias`, `padecimientos`, `medicamentos_habituales`, `restricciones_alimentarias`, `medico_nombre`, `medico_telefono`, `institucion_medica`, `numero_seguro`, `indicaciones_emergencia`, `observaciones`, `creado_en`, `actualizado_en`) VALUES (1,1,'O+','Alergia leve al cacahuate','Ninguno','Ninguno','Evitar productos con cacahuate',NULL,NULL,NULL,NULL,'Contactar al tutor principal.','Datos completamente ficticios para pruebas.','2026-06-25 04:47:00','2026-06-25 04:47:00'),(7,9,'O+','Sin alergias registradas','Ninguno','Ninguno',NULL,NULL,NULL,NULL,NULL,NULL,'Registro creado para prueba de app movil','2026-07-14 02:19:36','2026-07-14 02:19:36'),(8,10,'O+','Ninguna conocida','Ninguno','Ninguno','Ninguna','Dra. Laura Gomez','8112345678','Clinica Infantil Demo',NULL,'Contactar a la tutora principal.','Expediente creado para probar la seleccion de varios hijos.','2026-07-18 22:24:29','2026-07-18 22:24:29'),(21,19,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 05:03:18','2026-07-23 05:03:18');
/*!40000 ALTER TABLE `expedientes_medicos` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `guarderias`
--
-- ORDER BY:  `id_guarderia`

LOCK TABLES `guarderias` WRITE;
/*!40000 ALTER TABLE `guarderias` DISABLE KEYS */;
INSERT INTO `guarderias` (`id_guarderia`, `nombre`, `razon_social`, `email`, `telefono`, `direccion`, `ciudad`, `estado_region`, `codigo_postal`, `zona_horaria`, `logo_url`, `logo_public_id`, `estado`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (1,'Guardería Dulces Días','Dulces Días Educacion Infantil S.C.','contacto@dulcesdias.test','8112345678','Av. Ejemplo 123','Monterrey','Nuevo León','64000','America/Monterrey',NULL,NULL,'ACTIVA','2026-06-25 04:47:00','2026-06-25 04:47:00',NULL);
/*!40000 ALTER TABLE `guarderias` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `imagenes_evento`
--
-- ORDER BY:  `id_imagen`

LOCK TABLES `imagenes_evento` WRITE;
/*!40000 ALTER TABLE `imagenes_evento` DISABLE KEYS */;
INSERT INTO `imagenes_evento` (`id_imagen`, `id_evento`, `url`, `public_id`, `descripcion`, `orden`, `creado_en`) VALUES (2,12,'https://res.cloudinary.com/dbkj7vozi/image/upload/f_auto/q_auto/v1784784179/safekids/uploads/reportes/pqwvtu6at9yxe4haxevx','safekids/uploads/reportes/pqwvtu6at9yxe4haxevx',NULL,1,'2026-07-23 05:23:00');
/*!40000 ALTER TABLE `imagenes_evento` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `nino_contacto`
--
-- ORDER BY:  `id_nino_contacto`

LOCK TABLES `nino_contacto` WRITE;
/*!40000 ALTER TABLE `nino_contacto` DISABLE KEYS */;
INSERT INTO `nino_contacto` (`id_nino_contacto`, `id_nino`, `id_contacto`, `es_contacto_emergencia`, `autorizado_recoger`, `prioridad_emergencia`, `observaciones`, `creado_en`) VALUES (1,1,1,1,1,1,NULL,'2026-06-25 04:47:00'),(21,19,12,1,1,1,NULL,'2026-07-23 05:03:18');
/*!40000 ALTER TABLE `nino_contacto` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `nino_tutor`
--
-- ORDER BY:  `id_nino_tutor`

LOCK TABLES `nino_tutor` WRITE;
/*!40000 ALTER TABLE `nino_tutor` DISABLE KEYS */;
INSERT INTO `nino_tutor` (`id_nino_tutor`, `id_nino`, `id_tutor`, `parentesco`, `es_principal`, `recibe_notificaciones`, `autorizado_recoger`, `prioridad_contacto`, `creado_en`) VALUES (1,1,1,'Padre',1,1,1,1,'2026-06-25 04:47:00'),(7,9,6,'Madre',1,1,1,1,'2026-07-14 02:19:36'),(8,10,6,'Madre',1,1,1,1,'2026-07-18 22:24:29'),(29,19,16,'Madre',1,1,1,1,'2026-07-23 05:03:18');
/*!40000 ALTER TABLE `nino_tutor` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `ninos`
--
-- ORDER BY:  `id_nino`

LOCK TABLES `ninos` WRITE;
/*!40000 ALTER TABLE `ninos` DISABLE KEYS */;
INSERT INTO `ninos` (`id_nino`, `id_guarderia`, `nombres`, `apellidos`, `fecha_nacimiento`, `genero`, `foto_url`, `foto_public_id`, `codigo_qr`, `estado`, `fecha_ingreso`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (1,1,'Sofía','Ramírez López','2022-05-15','FEMENINO',NULL,NULL,'e739f477-7050-11f1-bf23-0a0027000005','ACTIVO','2026-01-15','2026-06-25 04:47:00','2026-06-25 04:47:00',NULL),(9,1,'Mateo','Demo Movil','2022-08-15','MASCULINO',NULL,NULL,'75d4ad4c-7f2a-11f1-bd7e-0a0027000005','ACTIVO','2026-07-13','2026-07-14 02:19:36','2026-07-14 02:19:36',NULL),(10,1,'Sofia','Demo Movil','2023-11-20','FEMENINO',NULL,NULL,'7107a7e0-82f7-11f1-8084-0a0027000005','ACTIVO','2026-07-18','2026-07-18 22:24:28','2026-07-18 22:24:28',NULL),(19,1,'Aylin Andrea','Vargas','2011-01-24','FEMENINO',NULL,NULL,'d1b76ffc-8653-11f1-aa21-0a0027000005','ACTIVO','2026-07-23','2026-07-23 05:03:18','2026-07-23 05:03:18',NULL);
/*!40000 ALTER TABLE `ninos` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `notificacion_destinatarios`
--
-- ORDER BY:  `id_destinatario`

LOCK TABLES `notificacion_destinatarios` WRITE;
/*!40000 ALTER TABLE `notificacion_destinatarios` DISABLE KEYS */;
INSERT INTO `notificacion_destinatarios` (`id_destinatario`, `id_notificacion`, `id_usuario`, `enviada_push`, `enviada_push_en`, `vista`, `vista_en`, `confirmada`, `confirmada_en`, `respuesta`, `creado_en`) VALUES (3,3,9,0,NULL,0,NULL,0,NULL,NULL,'2026-07-14 02:19:36'),(7,5,9,1,'2026-07-19 12:44:26',1,'2026-07-19 12:57:14',0,NULL,NULL,'2026-07-19 18:44:25'),(8,6,3,0,NULL,0,NULL,0,NULL,NULL,'2026-07-19 19:04:04'),(9,6,9,1,'2026-07-19 13:04:06',0,NULL,0,NULL,NULL,'2026-07-19 19:04:04'),(22,11,3,0,NULL,0,NULL,0,NULL,NULL,'2026-07-23 04:11:02'),(23,11,9,1,'2026-07-22 22:11:03',0,NULL,0,NULL,NULL,'2026-07-23 04:11:02'),(25,12,9,1,'2026-07-22 22:12:15',0,NULL,0,NULL,NULL,'2026-07-23 04:12:14'),(41,19,32,1,'2026-07-22 23:09:49',1,'2026-07-22 23:19:23',0,NULL,NULL,'2026-07-23 05:09:48'),(42,20,3,0,NULL,0,NULL,0,NULL,NULL,'2026-07-23 05:10:33'),(43,20,9,1,'2026-07-22 23:10:35',0,NULL,0,NULL,NULL,'2026-07-23 05:10:33'),(44,20,32,1,'2026-07-22 23:10:35',0,NULL,0,NULL,NULL,'2026-07-23 05:10:33'),(45,21,32,1,'2026-07-22 23:17:54',0,NULL,0,NULL,NULL,'2026-07-23 05:17:53');
/*!40000 ALTER TABLE `notificacion_destinatarios` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `notificaciones`
--
-- ORDER BY:  `id_notificacion`

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` (`id_notificacion`, `id_guarderia`, `id_empleado`, `id_tipo_notificacion`, `id_nino`, `alcance`, `titulo`, `mensaje`, `prioridad`, `imagen_url`, `imagen_public_id`, `requiere_confirmacion`, `estado`, `publicada_en`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (3,1,1,1,9,'PERSONAL','Aviso de prueba movil','Este aviso confirma que la app movil esta consultando notificaciones personales.','NORMAL',NULL,NULL,1,'PUBLICADA','2026-07-13 20:19:36','2026-07-14 02:19:36','2026-07-14 02:19:36',NULL),(5,1,1,2,9,'PERSONAL','Prueba push de SafeKids','La integracion entre la web, PHP, MySQL, Firebase y Android funciona correctamente.','IMPORTANTE',NULL,NULL,0,'PUBLICADA','2026-07-19 12:44:25','2026-07-19 18:44:25','2026-07-19 18:44:25',NULL),(6,1,1,1,NULL,'GLOBAL','Prueba global de SafeKids','Este aviso confirma que todos los tutores activos de la guarderia reciben las notificaciones globales.','NORMAL',NULL,NULL,0,'PUBLICADA','2026-07-19 13:04:04','2026-07-19 19:04:04','2026-07-19 19:04:04',NULL),(11,1,1,1,NULL,'GLOBAL','Prueba','Esta es una prueba de notificacion push de manera general','NORMAL',NULL,NULL,0,'PUBLICADA','2026-07-22 22:11:02','2026-07-23 04:11:02','2026-07-23 04:11:02',NULL),(12,1,1,2,9,'PERSONAL','Prueba Notificacion','Esta es una prueba de manera personal','IMPORTANTE',NULL,NULL,1,'PUBLICADA','2026-07-22 22:12:14','2026-07-23 04:12:14','2026-07-23 04:12:14',NULL),(19,1,1,2,19,'PERSONAL','Notificacion de prueba','Prueba','NORMAL',NULL,NULL,0,'PUBLICADA','2026-07-22 23:09:48','2026-07-23 05:09:48','2026-07-23 05:09:48',NULL),(20,1,1,1,NULL,'GLOBAL','Prueba','Prueba','NORMAL',NULL,NULL,0,'PUBLICADA','2026-07-22 23:10:33','2026-07-23 05:10:33','2026-07-23 05:10:33',NULL),(21,1,1,2,19,'PERSONAL','Aylin cabeza de funko POP','Prueba de notificacion con imagen','IMPORTANTE','https://res.cloudinary.com/dbkj7vozi/image/upload/f_auto/q_auto/v1784783871/safekids/uploads/notificaciones/jiqqtmvijdewbqpoijwa','safekids/uploads/notificaciones/jiqqtmvijdewbqpoijwa',1,'PUBLICADA','2026-07-22 23:17:53','2026-07-23 05:17:53','2026-07-23 05:17:53',NULL);
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `refresh_tokens`
--
-- ORDER BY:  `id_refresh_token`

LOCK TABLES `refresh_tokens` WRITE;
/*!40000 ALTER TABLE `refresh_tokens` DISABLE KEYS */;
INSERT INTO `refresh_tokens` (`id_refresh_token`, `id_usuario`, `token_hash`, `expira_en`, `revocado_en`, `creado_en`) VALUES (1,1,'c260069dd80108005c85e15da62084bdad30fd50a261738f882241a2a1a40132','2026-07-20 09:21:59','2026-07-19 21:21:59','2026-07-20 03:21:59'),(2,9,'244a44e5b271614db41199f4cbcca7f45dc6eceb1ba5ad43b16b7de2baaf7407','2026-08-18 21:21:59','2026-07-19 21:39:27','2026-07-20 03:21:59'),(3,2,'a6b58a45ec642ccc1f2f84886bed48578c57258fcdab7b9431ed3b243e11295e','2026-07-20 09:30:35','2026-07-19 21:39:27','2026-07-20 03:30:35'),(4,1,'23243f68007138eb8e7bf405ca862be5d92752a85aef38b58cc83137ae16f10c','2026-07-20 09:30:36','2026-07-19 21:39:27','2026-07-20 03:30:36'),(5,2,'91d80f6513b82300aedc89128fadf32807ac074d8ae043f1d0c5e475534c27da','2026-07-20 09:31:00','2026-07-19 21:39:27','2026-07-20 03:31:00'),(6,1,'0510725dc86b7bf83a78394f8fce1077e6a80af6cf95995b9efc0e6fbc3a065e','2026-07-19 21:30:01','2026-07-19 21:39:27','2026-07-20 03:31:01'),(7,1,'a0339fc7ec258f52436a9e1df33cad4a476379d03b32db08bf1ad202ac246421','2026-07-20 09:32:30','2026-07-19 21:33:16','2026-07-20 03:32:30'),(8,9,'3d52be9ae4204474af0f18b08ffca78b63d5ba80cbab5ccd7556afa6823f50e9','2026-08-18 21:38:00','2026-07-19 21:38:56','2026-07-20 03:38:00'),(9,9,'9ac9fa96602ed11fed60c169bf17d3e1bdbe60ae18c8bad72bd176c88b4d8a28','2026-08-18 21:39:38','2026-07-22 21:36:42','2026-07-20 03:39:38'),(10,1,'5f04c76adc948dfc5843dcbde1eb384e3ac9864f2da009d1d48d608d92baf3a6','2026-07-20 09:40:04',NULL,'2026-07-20 03:40:04'),(11,1,'3cb7f4f9f3170051b2c158cc49a6a3a5f3b6e2165203b41a1950529344ac16dd','2026-07-20 09:41:04','2026-07-19 21:41:04','2026-07-20 03:41:04'),(12,1,'3d983a1ae9a7fb00c801b618170b4a851821991bc97bd4b95494710888b59362','2026-07-20 09:41:32','2026-07-19 21:41:33','2026-07-20 03:41:32'),(13,1,'d43534566af63947d73fc9c44e0fd14b79bbabd1a9c46ec8b014408a6922fdec','2026-07-21 08:40:11','2026-07-20 20:42:22','2026-07-21 02:40:11'),(14,1,'80094a8e3ca7c135c9a1fd2806d8399649b623cc26f7d40e2ecf292affe88265','2026-07-21 08:41:18','2026-07-20 20:42:22','2026-07-21 02:41:18'),(17,1,'c8ad20c6adb7d54e7fd71bfd9fc3b16f4893431ab5a1a106ad45b37c66e11cd6','2026-07-21 08:41:57','2026-07-20 20:41:59','2026-07-21 02:41:57'),(22,1,'76138fc1cef7b503b377822b8ceda34483bfdced9c83a8a41a2a236e8c656099','2026-07-21 08:44:58',NULL,'2026-07-21 02:44:58'),(36,1,'79895ec7289550cdc77c43d17e590bbda20b01c83374ec3019cab0cc3aae35e7','2026-07-23 03:19:06',NULL,'2026-07-22 21:19:06'),(37,9,'09c1e7b6cae1ea038113954c5cf3b1da4e305844ec52cf93456d1dea05cbd804','2026-08-21 21:36:53','2026-07-22 21:36:53','2026-07-23 03:36:53'),(38,9,'38f94fcd2fa85e1ef158efb0b3417fc9769c496a537c7dd8b58bdee05c3e65b2','2026-08-21 22:10:04',NULL,'2026-07-23 04:10:04'),(49,32,'7972ef3b2f58ec5543e54d048d81da601e64476b57619a177132998ffdc49e67','2026-08-21 23:07:17','2026-07-22 23:08:28','2026-07-23 05:07:17'),(50,32,'2f84d096915e0c4da8a4f51b1b5fdec53fccbc26e70cf44d6daa5baee336d44f','2026-08-21 23:08:57',NULL,'2026-07-23 05:08:57'),(51,1,'a2810684e94a253fb2596d7578251124daf2f2b4bb8bd99c477c2bcb11a35cef','2026-07-23 11:35:04','2026-07-22 23:35:04','2026-07-23 05:35:04'),(57,1,'f4f5b365f36fb61e9b51014fe0e02f20a1fc1eb589174d1dbc8a376c720b5443','2026-07-23 11:45:53',NULL,'2026-07-23 05:45:53'),(58,1,'a25e58285d74e442dd6e30c3b7ebc102a1b7e324c8c0480c48d97ac02627c881','2026-07-23 11:46:11',NULL,'2026-07-23 05:46:11');
/*!40000 ALTER TABLE `refresh_tokens` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `roles`
--
-- ORDER BY:  `id_rol`

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `creado_en`) VALUES (1,'SUPERADMIN','Administra las guarderías y la configuración global del sistema.','2026-06-25 04:46:17'),(2,'ADMIN','Administra empleados, niños, tutores y operaciones de una guardería.','2026-06-25 04:46:17'),(3,'EMPLEADO','Consulta expedientes y registra eventos y notificaciones.','2026-06-25 04:46:17'),(4,'TUTOR','Consulta información de sus hijos y recibe notificaciones.','2026-06-25 04:46:17');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tipos_evento`
--
-- ORDER BY:  `id_tipo_evento`

LOCK TABLES `tipos_evento` WRITE;
/*!40000 ALTER TABLE `tipos_evento` DISABLE KEYS */;
INSERT INTO `tipos_evento` (`id_tipo_evento`, `codigo`, `nombre`, `descripcion`, `requiere_detalle`, `permite_imagen`, `activo`) VALUES (1,'ALIMENTACION','Alimentación','Registro de alimentos y cantidad consumida.',1,1,1),(2,'SUENO_INICIO','Inicio de sueño','Inicio de siesta o periodo de sueño.',0,0,1),(3,'SUENO_FIN','Fin de sueño','Fin de siesta o periodo de sueño.',0,0,1),(4,'CAMBIO_PANAL','Cambio de pañal','Registro de cambio de pañal.',1,0,1),(5,'BANO','Baño','Registro de ida al baño.',1,0,1),(6,'MEDICAMENTO','Medicamento','Administración de medicamento autorizado.',1,1,1),(7,'ESTADO_ANIMO','Estado de ánimo','Observación del estado emocional del niño.',1,1,1),(8,'ACTIVIDAD','Actividad','Actividad educativa, recreativa o física.',1,1,1),(9,'INCIDENTE','Incidente','Golpe, caída, malestar u otra situación importante.',1,1,1),(10,'OBSERVACION','Observación general','Comentario general del personal.',1,1,1),(11,'ENTRADA','Entrada','Registro informativo de llegada.',0,0,1),(12,'SALIDA','Salida','Registro informativo de salida.',0,0,1);
/*!40000 ALTER TABLE `tipos_evento` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tipos_notificacion`
--
-- ORDER BY:  `id_tipo_notificacion`

LOCK TABLES `tipos_notificacion` WRITE;
/*!40000 ALTER TABLE `tipos_notificacion` DISABLE KEYS */;
INSERT INTO `tipos_notificacion` (`id_tipo_notificacion`, `codigo`, `nombre`, `descripcion`, `activo`) VALUES (1,'AVISO_GENERAL','Aviso general','Comunicado para todos los tutores de la guardería.',1),(2,'AVISO_PERSONAL','Aviso personal','Comunicado relacionado con un niño específico.',1),(3,'INCIDENTE','Incidente','Notificación sobre un incidente o situación relevante.',1),(4,'EMERGENCIA','Emergencia','Notificación urgente que puede requerir confirmación.',1),(5,'RECORDATORIO','Recordatorio','Recordatorio de material, horario o actividad.',1),(6,'ADMINISTRATIVA','Administrativa','Avisos administrativos de la guardería.',1);
/*!40000 ALTER TABLE `tipos_notificacion` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tutores`
--
-- ORDER BY:  `id_tutor`

LOCK TABLES `tutores` WRITE;
/*!40000 ALTER TABLE `tutores` DISABLE KEYS */;
INSERT INTO `tutores` (`id_tutor`, `id_usuario`, `nombres`, `apellidos`, `telefono`, `telefono_alterno`, `direccion`, `foto_url`, `foto_public_id`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (1,3,'Carlos','Ramírez','8133333333',NULL,'Calle Prueba 45',NULL,NULL,'2026-06-25 04:47:00','2026-06-25 04:47:00',NULL),(6,9,'Andrea','Tutor Movil','8112345678',NULL,'Direccion de prueba',NULL,NULL,'2026-07-14 02:19:36','2026-07-14 02:19:36',NULL),(16,32,'Angelica Arely','Mendez Alvarado','8110183953',NULL,NULL,NULL,NULL,'2026-07-23 05:03:18','2026-07-23 05:03:18',NULL);
/*!40000 ALTER TABLE `tutores` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `usuarios`
--
-- ORDER BY:  `id_usuario`

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` (`id_usuario`, `id_guarderia`, `id_rol`, `email`, `password_hash`, `requiere_cambio_password`, `estado`, `intentos_fallidos`, `bloqueado_hasta`, `ultimo_acceso`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES (1,1,2,'admin@dulcesdias.test','$2y$12$k7K6kkcnpWGMljuF3/cMSeV01TVhqKVG3VD.72dpHISusg.57V8dW',0,'ACTIVO',0,NULL,'2026-07-22 23:46:11','2026-06-25 04:47:00','2026-07-23 05:46:11',NULL),(2,1,3,'cuidadora@dulcesdias.test','$2y$12$k7K6kkcnpWGMljuF3/cMSeV01TVhqKVG3VD.72dpHISusg.57V8dW',0,'ACTIVO',0,NULL,'2026-07-19 21:31:00','2026-06-25 04:47:00','2026-07-21 02:40:44',NULL),(3,1,4,'tutor@dulcesdias.test','$2y$12$k7K6kkcnpWGMljuF3/cMSeV01TVhqKVG3VD.72dpHISusg.57V8dW',0,'ACTIVO',0,NULL,NULL,'2026-06-25 04:47:00','2026-07-21 02:40:44',NULL),(9,1,4,'tutor.movil@safekids.test','$2y$10$6MflBS0It.NwFrOLIsrbdu6B5fdE4n2UQwZv2D1fSAxUAmPMKvEBC',0,'ACTIVO',0,NULL,'2026-07-22 22:10:04','2026-07-14 02:19:36','2026-07-23 04:10:04',NULL),(32,1,4,'arely@safekids.test','$2y$10$ulduwS6HdDoqgGCJmG5sXO/NeKW6Oa7ji1vQlloaHnRUw3gahyPqi',0,'ACTIVO',0,NULL,'2026-07-22 23:08:57','2026-07-23 05:03:18','2026-07-23 05:08:57',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

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
