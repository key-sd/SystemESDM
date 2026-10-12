-- Respaldo Cloud InfinityFree

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: db_systemelimsdm
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `celulas`
--

DROP TABLE IF EXISTS `celulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `celulas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `sector_id` int(10) unsigned NOT NULL,
  `lider_id` int(10) unsigned DEFAULT NULL,
  `tipo` enum('adultos','jovenes','ninos') NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_celula_nombre_sector` (`nombre`,`sector_id`),
  UNIQUE KEY `uq_celula_lider` (`lider_id`),
  KEY `idx_celulas_sector` (`sector_id`),
  CONSTRAINT `fk_celula_lider` FOREIGN KEY (`lider_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_celula_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_celula_activa` CHECK (`activo` in (0,1)),
  CONSTRAINT `chk_latitud` CHECK (`lat` is null or `lat` between -90 and 90),
  CONSTRAINT `chk_longitud` CHECK (`lng` is null or `lng` between -180 and 180)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `celulas`
--

LOCK TABLES `celulas` WRITE;
/*!40000 ALTER TABLE `celulas` DISABLE KEYS */;
INSERT INTO `celulas` VALUES (1,'C01',1,5,'adultos','',13.4823212,-88.4670598,1,'2026-10-11 22:21:08');
/*!40000 ALTER TABLE `celulas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metas`
--

DROP TABLE IF EXISTS `metas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `anio` smallint(5) unsigned NOT NULL,
  `tipo` enum('celulas','asistencia_adultos','asistencia_ninos','conversiones','visitas','bautismos') NOT NULL,
  `objetivo` decimal(12,2) NOT NULL,
  `sector_id` int(10) unsigned DEFAULT NULL,
  `sector_id_unico` int(10) unsigned NOT NULL DEFAULT 0,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meta_anio_tipo_sector` (`anio`,`tipo`,`sector_id_unico`),
  KEY `fk_meta_sector` (`sector_id`),
  KEY `fk_meta_creador` (`creado_por`),
  CONSTRAINT `fk_meta_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_meta_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_meta_objetivo` CHECK (`objetivo` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metas`
--

LOCK TABLES `metas` WRITE;
/*!40000 ALTER TABLE `metas` DISABLE KEYS */;
/*!40000 ALTER TABLE `metas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metas_asignaciones_sector`
--

DROP TABLE IF EXISTS `metas_asignaciones_sector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metas_asignaciones_sector` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `meta_id` int(10) unsigned NOT NULL,
  `sector_id` int(10) unsigned NOT NULL,
  `objetivo` decimal(12,2) NOT NULL,
  `base_inicial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meta_asignacion_sector` (`meta_id`,`sector_id`),
  KEY `fk_asignacion_sector` (`sector_id`),
  CONSTRAINT `fk_asignacion_meta` FOREIGN KEY (`meta_id`) REFERENCES `metas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_asignacion_objetivo` CHECK (`objetivo` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metas_asignaciones_sector`
--

LOCK TABLES `metas_asignaciones_sector` WRITE;
/*!40000 ALTER TABLE `metas_asignaciones_sector` DISABLE KEYS */;
/*!40000 ALTER TABLE `metas_asignaciones_sector` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `miembros_celula`
--

DROP TABLE IF EXISTS `miembros_celula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `miembros_celula` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `celula_id` int(10) unsigned NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `estado_civil` enum('soltero','casado') NOT NULL DEFAULT 'soltero',
  `conversion` tinyint(1) NOT NULL DEFAULT 0,
  `bautismo_agua` tinyint(1) NOT NULL DEFAULT 0,
  `bautismo_espiritu` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_miembros_celula` (`celula_id`),
  CONSTRAINT `fk_miembros_celula` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_miembro_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `miembros_celula`
--

LOCK TABLES `miembros_celula` WRITE;
/*!40000 ALTER TABLE `miembros_celula` DISABLE KEYS */;
INSERT INTO `miembros_celula` VALUES (1,1,'Vladimir Rodríguez','soltero',1,1,1,'2000-10-10','Colonia Las Playitas','70709090',1,'2026-10-11 23:02:43',NULL);
/*!40000 ALTER TABLE `miembros_celula` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_lider`
--

DROP TABLE IF EXISTS `reportes_lider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_lider` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `celula_id` int(10) unsigned NOT NULL,
  `lider_id` int(10) unsigned NOT NULL,
  `fecha_reporte` date NOT NULL,
  `anio_semana` int(11) GENERATED ALWAYS AS (yearweek(`fecha_reporte`,1)) STORED,
  `adultos_amigos` int(10) unsigned NOT NULL DEFAULT 0,
  `adultos_hermanos` int(10) unsigned NOT NULL DEFAULT 0,
  `jovenes_amigos` int(10) unsigned NOT NULL DEFAULT 0,
  `jovenes_hermanos` int(10) unsigned NOT NULL DEFAULT 0,
  `ninos` int(10) unsigned NOT NULL DEFAULT 0,
  `total_asistencia` int(11) GENERATED ALWAYS AS (`adultos_amigos` + `adultos_hermanos` + `jovenes_amigos` + `jovenes_hermanos` + `ninos`) STORED,
  `ofrenda_iglesia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ofrenda_misiones` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ofrenda_bus` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ofrenda_total` decimal(12,2) GENERATED ALWAYS AS (`ofrenda_iglesia` + `ofrenda_misiones` + `ofrenda_bus`) STORED,
  `conversion` int(10) unsigned NOT NULL DEFAULT 0,
  `visita_programada` tinyint(1) NOT NULL DEFAULT 0,
  `bautismo_agua` tinyint(1) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `enviado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `visto_supervisor_en` timestamp NULL DEFAULT NULL,
  `visto_admin_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reporte_lider_celula_semana` (`celula_id`,`anio_semana`),
  KEY `idx_reporte_lider_fecha` (`fecha_reporte`),
  KEY `fk_reporte_lider_usuario` (`lider_id`),
  CONSTRAINT `fk_reporte_lider_celula` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reporte_lider_usuario` FOREIGN KEY (`lider_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_reporte_lider_ofrendas` CHECK (`ofrenda_iglesia` >= 0 and `ofrenda_misiones` >= 0 and `ofrenda_bus` >= 0),
  CONSTRAINT `chk_visita_programada` CHECK (`visita_programada` in (0,1)),
  CONSTRAINT `chk_bautismo_agua` CHECK (`bautismo_agua` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_lider`
--

LOCK TABLES `reportes_lider` WRITE;
/*!40000 ALTER TABLE `reportes_lider` DISABLE KEYS */;
INSERT INTO `reportes_lider` VALUES (1,1,5,'2026-10-11',202641,2,15,0,0,4,21,10.00,0.00,0.00,10.00,1,0,1,'Vladimir puede tocar un instrumento musical (guitarra) y quiere unirse al grupo musical de la iglesia.','2026-10-11 23:18:26','2026-10-11 23:26:10','2026-10-11 23:23:08','2026-10-11 23:26:10');
/*!40000 ALTER TABLE `reportes_lider` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_lider_bautismos`
--

DROP TABLE IF EXISTS `reportes_lider_bautismos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_lider_bautismos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporte_lider_id` int(10) unsigned NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `tipo_celula` enum('adultos','infantil','juvenil') NOT NULL DEFAULT 'adultos',
  `estado_civil` enum('soltero','casado') NOT NULL DEFAULT 'soltero',
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_bautismo` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rep_baut_reporte` (`reporte_lider_id`),
  CONSTRAINT `fk_rep_baut_reporte` FOREIGN KEY (`reporte_lider_id`) REFERENCES `reportes_lider` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_lider_bautismos`
--

LOCK TABLES `reportes_lider_bautismos` WRITE;
/*!40000 ALTER TABLE `reportes_lider_bautismos` DISABLE KEYS */;
INSERT INTO `reportes_lider_bautismos` VALUES (2,1,'Vladimir Rodríguez','adultos','soltero','2000-10-10','2026-10-12','Colonia Las Playitas','70709090','2026-10-11 23:21:01');
/*!40000 ALTER TABLE `reportes_lider_bautismos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_lider_conversiones`
--

DROP TABLE IF EXISTS `reportes_lider_conversiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_lider_conversiones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporte_lider_id` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `tipo_decision` enum('conversion','reconcilio') NOT NULL DEFAULT 'conversion',
  `estado_civil` enum('soltero','casado') NOT NULL DEFAULT 'soltero',
  `bautismo_agua` tinyint(1) NOT NULL DEFAULT 0,
  `bautismo_espiritu` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rep_conv_reporte` (`reporte_lider_id`),
  CONSTRAINT `fk_rep_conv_reporte` FOREIGN KEY (`reporte_lider_id`) REFERENCES `reportes_lider` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_lider_conversiones`
--

LOCK TABLES `reportes_lider_conversiones` WRITE;
/*!40000 ALTER TABLE `reportes_lider_conversiones` DISABLE KEYS */;
INSERT INTO `reportes_lider_conversiones` VALUES (2,1,'2026-10-12','Vladimir Rodríguez','reconcilio','soltero',1,1,'2000-10-10','Colonia Las Playitas','70709090','2026-10-11 23:21:01');
/*!40000 ALTER TABLE `reportes_lider_conversiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_lider_visitas`
--

DROP TABLE IF EXISTS `reportes_lider_visitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_lider_visitas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporte_lider_id` int(10) unsigned NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `tipo_celula` enum('adultos','infantil','juvenil') NOT NULL DEFAULT 'adultos',
  `estado_civil` enum('soltero','casado') NOT NULL DEFAULT 'soltero',
  `bautismo_agua` tinyint(1) NOT NULL DEFAULT 0,
  `bautismo_espiritu` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_nacimiento` date DEFAULT NULL,
  `numero_visita` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `fecha_visita` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rep_vis_reporte` (`reporte_lider_id`),
  CONSTRAINT `fk_rep_vis_reporte` FOREIGN KEY (`reporte_lider_id`) REFERENCES `reportes_lider` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_rep_vis_numero` CHECK (`numero_visita` between 1 and 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_lider_visitas`
--

LOCK TABLES `reportes_lider_visitas` WRITE;
/*!40000 ALTER TABLE `reportes_lider_visitas` DISABLE KEYS */;
/*!40000 ALTER TABLE `reportes_lider_visitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_sup_detalle`
--

DROP TABLE IF EXISTS `reportes_sup_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_sup_detalle` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporte_supervisor_id` int(10) unsigned NOT NULL,
  `lider_id` int(10) unsigned DEFAULT NULL,
  `celula_id` int(10) unsigned DEFAULT NULL,
  `tipo_celula` enum('adultos','jovenes','ninos') NOT NULL,
  `adultos_amigos` int(10) unsigned NOT NULL DEFAULT 0,
  `adultos_hermanos` int(10) unsigned NOT NULL DEFAULT 0,
  `jovenes_amigos` int(10) unsigned NOT NULL DEFAULT 0,
  `jovenes_hermanos` int(10) unsigned NOT NULL DEFAULT 0,
  `ninos` int(10) unsigned NOT NULL DEFAULT 0,
  `conversiones` int(10) unsigned NOT NULL DEFAULT 0,
  `visitas_programadas` int(10) unsigned NOT NULL DEFAULT 0,
  `bautismos_agua` int(10) unsigned NOT NULL DEFAULT 0,
  `ofrenda_iglesia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ofrenda_misiones` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ofrenda_bus` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_detalle_reporte` (`reporte_supervisor_id`),
  KEY `fk_detalle_lider` (`lider_id`),
  KEY `fk_detalle_celula` (`celula_id`),
  CONSTRAINT `fk_detalle_celula` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_lider` FOREIGN KEY (`lider_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_reporte` FOREIGN KEY (`reporte_supervisor_id`) REFERENCES `reportes_supervisor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_sup_detalle`
--

LOCK TABLES `reportes_sup_detalle` WRITE;
/*!40000 ALTER TABLE `reportes_sup_detalle` DISABLE KEYS */;
INSERT INTO `reportes_sup_detalle` VALUES (1,1,5,1,'adultos',2,40,0,0,4,1,0,1,10.00,0.00,0.00);
/*!40000 ALTER TABLE `reportes_sup_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes_supervisor`
--

DROP TABLE IF EXISTS `reportes_supervisor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reportes_supervisor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sector_id` int(10) unsigned NOT NULL,
  `supervisor_id` int(10) unsigned NOT NULL,
  `fecha_reporte` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `enviado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `visto_admin_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reporte_supervisor_semana` (`sector_id`,`fecha_reporte`),
  KEY `idx_reporte_supervisor_fecha` (`fecha_reporte`),
  KEY `fk_reporte_supervisor_usuario` (`supervisor_id`),
  CONSTRAINT `fk_reporte_supervisor_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reporte_supervisor_usuario` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_reporte_supervisor_sabado` CHECK (dayofweek(`fecha_reporte`) = 7)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_supervisor`
--

LOCK TABLES `reportes_supervisor` WRITE;
/*!40000 ALTER TABLE `reportes_supervisor` DISABLE KEYS */;
INSERT INTO `reportes_supervisor` VALUES (1,1,4,'2026-10-10','modifique total','2026-10-11 23:25:24','2026-10-11 23:26:17','2026-10-11 23:26:17');
/*!40000 ALTER TABLE `reportes_supervisor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sectores`
--

DROP TABLE IF EXISTS `sectores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sectores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `zona` varchar(20) DEFAULT NULL,
  `distrito` varchar(20) DEFAULT NULL,
  `supervisor_id` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sector_nombre` (`nombre`),
  UNIQUE KEY `uq_sector_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_sector_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_sector_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sectores`
--

LOCK TABLES `sectores` WRITE;
/*!40000 ALTER TABLE `sectores` DISABLE KEYS */;
INSERT INTO `sectores` VALUES (1,'SECTOR 1','','',4,1,'2026-10-11 21:46:37'),(2,'SECTOR 2','','',11,1,'2026-10-11 22:17:51'),(3,'SECTOR 3','','',15,1,'2026-10-11 22:18:05'),(4,'SECTOR 4','','',6,1,'2026-10-11 22:18:21'),(5,'SECTOR 5','','',17,1,'2026-10-11 22:18:30'),(6,'SECTOR 6','','',18,1,'2026-10-11 22:18:38'),(7,'SECTOR 7','','',21,1,'2026-10-11 22:18:44');
/*!40000 ALTER TABLE `sectores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicios_iglesia`
--

DROP TABLE IF EXISTS `servicios_iglesia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicios_iglesia` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `tipo_servicio` enum('martes','jueves','domingo') NOT NULL,
  `asistencia_adultos` int(10) unsigned NOT NULL DEFAULT 0,
  `asistencia_ninos` int(10) unsigned NOT NULL DEFAULT 0,
  `asistencia_total` int(11) GENERATED ALWAYS AS (`asistencia_adultos` + `asistencia_ninos`) STORED,
  `ofrenda` decimal(12,2) NOT NULL DEFAULT 0.00,
  `diezmos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `registrado_por` int(10) unsigned DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servicio_fecha` (`fecha`),
  KEY `idx_servicio_fecha` (`fecha`),
  KEY `fk_servicio_registrador` (`registrado_por`),
  CONSTRAINT `fk_servicio_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_servicio_dinero` CHECK (`ofrenda` >= 0 and `diezmos` >= 0),
  CONSTRAINT `chk_servicio_dia` CHECK (`tipo_servicio` = 'martes' and dayofweek(`fecha`) = 3 or `tipo_servicio` = 'jueves' and dayofweek(`fecha`) = 5 or `tipo_servicio` = 'domingo' and dayofweek(`fecha`) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicios_iglesia`
--

LOCK TABLES `servicios_iglesia` WRITE;
/*!40000 ALTER TABLE `servicios_iglesia` DISABLE KEYS */;
/*!40000 ALTER TABLE `servicios_iglesia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(120) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('lider','supervisor','admin') NOT NULL,
  `sector_id` int(10) unsigned DEFAULT NULL,
  `celula_id` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_login` (`usuario`),
  KEY `idx_usuarios_sector` (`sector_id`),
  KEY `fk_usuario_celula` (`celula_id`),
  CONSTRAINT `fk_usuario_celula` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_usuario_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador de prueba','admin','$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i','admin',NULL,NULL,1,'2026-10-10 04:26:34'),(2,'Supervisor de prueba','supervisor','$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i','supervisor',NULL,NULL,1,'2026-10-10 04:26:34'),(3,'Lider de prueba','lider','$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i','lider',NULL,NULL,1,'2026-10-10 04:26:34'),(4,'Juan Fracisco Chevez Portillo','francisco','$2y$10$ENdqO7Ndz0vz29dTWF.rledzS5guMZrTXe3febaXy3sAoNnxggqLC','supervisor',1,NULL,1,'2026-10-11 21:45:28'),(5,'Gonzalo Rodríguez','gonzalo','$2y$10$yCJawxGqzYHMDoVd5X7HEeU7vIcO914adBX4onndK5JOAjShqk34m','lider',1,1,1,'2026-10-11 21:50:04'),(6,'Rafael Fabricio Flores Marquez','fabricio','$2y$10$4xBtSJTlEAn.9b2ryWrDEu352RD9r2nKHcM5U.eQFjWFbnFmAwco.','supervisor',4,NULL,1,'2026-10-11 21:50:32'),(7,'Michelle Recinos','michelle','$2y$10$ZRCuwmg6qoywLlpSa0VbWeD2JvV7qLkvltDiiLkDb6FGzIQYFJjxO','lider',NULL,NULL,1,'2026-10-11 21:51:55'),(9,'Francisco Chévez','juan','$2y$10$o1L34h/9uCH5dPZzDsoADe9fh/a7ji1M6yl9CdaPAvRrf3Vu0Q/yu','lider',NULL,NULL,1,'2026-10-11 21:54:34'),(10,'Rene Hernandez','rene','$2y$10$x7GDHCXoADGpemTTwLxGR.HyTkqmjvrnSqTyOM6MQIrAijCumatK.','lider',NULL,NULL,1,'2026-10-11 21:54:37'),(11,'Walter Rafael Rodríguez','walter','$2y$10$ZKGYz9d8TMrMmsydtpYdUO5Uo3Dk0is9mjM56cFsOtZ2Pkt26PDZC','supervisor',2,NULL,1,'2026-10-11 21:55:22'),(12,'Ángel Rivera','angel','$2y$10$5VHGgxzYoPB09D/OeFP4xOvaPzWrZOnVHEyTK0PKhk.1Qk0OMErei','lider',NULL,NULL,1,'2026-10-11 21:56:13'),(13,'Zoila Ramirez','zoila','$2y$10$cZuiKNkXBDlOP/DsPbKvbeI9OKr15SnlhEX5Q2Ti027J26QIimtK6','lider',NULL,NULL,1,'2026-10-11 21:56:28'),(14,'Walter Rodríguez','rafael','$2y$10$mQiF1QEX4nZrt8joGVDDKuEgZvios.Gtml/bsax0qmX0lI1h87ekS','lider',NULL,NULL,1,'2026-10-11 21:57:31'),(15,'Ana Elizabeth Alfaro','ana','$2y$10$flTRlSdavUcx1Siv0TBVS.xVMHhUREOkwrYB.ltfHGzNL4uWErTB.','supervisor',3,NULL,1,'2026-10-11 21:58:17'),(16,'Ana Alfaro','elizabeth','$2y$10$QXHZQRZloMp/ycqpMIXmsOCv6HPpQi7Wdh8Qt5idWardZqthvrol.','lider',NULL,NULL,1,'2026-10-11 21:59:15'),(17,'Iris Arely Portillo','arely','$2y$10$Dgb6hitBVvGcK93U3Tnzj.6E30Ilqua/xra7mJ8Aix79KUZfffAMm','supervisor',5,NULL,1,'2026-10-11 22:00:24'),(18,'Jonathan David Flores','david','$2y$10$G/jlf8ewJMSL0KkJ.x.Xs.reEcFvyopju.nPiXPgNyKGKtRf77Yc6','supervisor',6,NULL,1,'2026-10-11 22:00:48'),(19,'David Flores','jonathan1','$2y$10$pwLBZaiaHktBJ88PHoYqXuz5Inh72S0DAFqaFoG0uoqNwr0xFzS32','lider',NULL,NULL,1,'2026-10-11 22:01:58'),(20,'David Flores','jonathan2','$2y$10$thiHtUAnn2ekajQxORnuXuaSx5.e3ZJ3n4l.AM/FoqHI.vsgeP.NC','lider',NULL,NULL,1,'2026-10-11 22:08:01'),(21,'Alcides Dinarte','alcides','$2y$10$4fDtrulbE2UjcHUQh9FdAu0HZHpN3Sd9nKkX0rCxCavG.ybiduiBy','supervisor',7,NULL,1,'2026-10-11 22:09:18'),(22,'Alcides Dinarte','alcidescelula','$2y$10$u3.zSBSPqyStGN3rmGEUWeC2s8DDaAaucNktjggWQbpDS10zYsrq.','lider',NULL,NULL,1,'2026-10-11 22:10:15'),(23,'Yesenia Mejía de Dinarte','yesenia','$2y$10$TkdsqqZ.6Glghgs0UzAvOOcFSR8tYg8WPEvmKmDxSUbQYN.Oeg/pS','lider',NULL,NULL,1,'2026-10-11 22:11:34'),(24,'Arely Portillo','iris','$2y$10$1Wh7QR6OYcSgTJ05uFMpG.WwBDmP34mzv/hyszEarzJzlEYd67nP.','lider',NULL,NULL,1,'2026-10-11 22:12:48'),(25,'Daniela Chévez','daniela','$2y$10$loavJQRcuL/8bfnKbCsmGeqcgj8gWWsOP6J7ZNSRPhoASb4XYzHSS','lider',NULL,NULL,1,'2026-10-11 22:13:24');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-11 21:26:37


SET FOREIGN_KEY_CHECKS = 1;
