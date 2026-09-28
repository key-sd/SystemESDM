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
INSERT INTO `celulas` VALUES (1,'Celula 1',1,3,'ninos','Guarumal',13.4876791,-88.4605403,1,'2026-09-14 03:49:53');
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
  `sector_id_unico` int(10) unsigned GENERATED ALWAYS AS (coalesce(`sector_id`,0)) STORED,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metas`
--

LOCK TABLES `metas` WRITE;
/*!40000 ALTER TABLE `metas` DISABLE KEYS */;
INSERT INTO `metas` VALUES (1,2026,'celulas',10.00,NULL,0,1,'2026-09-14 22:16:06',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metas_asignaciones_sector`
--

LOCK TABLES `metas_asignaciones_sector` WRITE;
/*!40000 ALTER TABLE `metas_asignaciones_sector` DISABLE KEYS */;
INSERT INTO `metas_asignaciones_sector` VALUES (1,1,1,10.00,0.00,'2026-09-14 22:16:06');
/*!40000 ALTER TABLE `metas_asignaciones_sector` ENABLE KEYS */;
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
  UNIQUE KEY `uq_reporte_lider_semana` (`celula_id`,`fecha_reporte`),
  KEY `idx_reporte_lider_fecha` (`fecha_reporte`),
  KEY `fk_reporte_lider_usuario` (`lider_id`),
  CONSTRAINT `fk_reporte_lider_celula` FOREIGN KEY (`celula_id`) REFERENCES `celulas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reporte_lider_usuario` FOREIGN KEY (`lider_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_reporte_lider_sabado` CHECK (dayofweek(`fecha_reporte`) = 7),
  CONSTRAINT `chk_reporte_lider_ofrendas` CHECK (`ofrenda_iglesia` >= 0 and `ofrenda_misiones` >= 0 and `ofrenda_bus` >= 0),
  CONSTRAINT `chk_visita_programada` CHECK (`visita_programada` in (0,1)),
  CONSTRAINT `chk_bautismo_agua` CHECK (`bautismo_agua` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_lider`
--

LOCK TABLES `reportes_lider` WRITE;
/*!40000 ALTER TABLE `reportes_lider` DISABLE KEYS */;
INSERT INTO `reportes_lider` VALUES (1,1,3,'2026-09-19',7,9,0,0,5,21,1.50,0.00,0.00,1.50,1,1,0,'Es la primer visita programada','2026-09-14 22:13:20','2026-09-15 01:16:25','2026-09-15 01:16:25',NULL);
/*!40000 ALTER TABLE `reportes_lider` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_sup_detalle`
--

LOCK TABLES `reportes_sup_detalle` WRITE;
/*!40000 ALTER TABLE `reportes_sup_detalle` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes_supervisor`
--

LOCK TABLES `reportes_supervisor` WRITE;
/*!40000 ALTER TABLE `reportes_supervisor` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sectores`
--

LOCK TABLES `sectores` WRITE;
/*!40000 ALTER TABLE `sectores` DISABLE KEYS */;
INSERT INTO `sectores` VALUES (1,'Sector 1','1','1',2,1,'2026-09-14 03:49:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador de prueba','admin','$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i','admin',NULL,NULL,1,'2026-09-13 18:19:11'),(2,'Supervisor de prueba','supervisor','$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i','supervisor',1,NULL,1,'2026-09-13 18:19:11'),(3,'Líder de prueba','lider','$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i','lider',1,1,1,'2026-09-13 18:19:11');
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

-- Dump completed on 2026-09-14 22:04:42
