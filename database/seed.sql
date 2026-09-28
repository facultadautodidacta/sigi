-- =====================================================================
-- SIGI - Datos de demostracion
-- Todas las personas y equipos son FICTICIOS.
-- Hashes bcrypt generados con password_hash() en PHP 8.3
-- Ejecutar DESPUES de schema.sql
-- =====================================================================

USE `sigi`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- No se fija time_zone a proposito: el seed debe correr en la zona del
-- servidor (CST, UTC-6), que es la misma que usa PHP (America/Mexico_City)
-- y en la que MariaDB interpreta las columnas TIMESTAMP al ejecutar la
-- aplicacion. Fijarla en UTC corria el seed un dia por delante del sistema.

TRUNCATE TABLE `maintenance`;
TRUNCATE TABLE `assignments`;
TRUNCATE TABLE `equipment`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `brands`;
TRUNCATE TABLE `employees`;
TRUNCATE TABLE `audit_log`;
TRUNCATE TABLE `login_attempts`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `roles`;

-- ---------------------------------------------------------------------
-- ROLES (3)
-- ---------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Administrador', 'Acceso total: usuarios, empleados, marcas, categorias, equipos, asignaciones, mantenimiento, reportes y auditoria'),
(2, 'Tecnico',        'Consulta y gestion de equipos, asignaciones y mantenimiento. Sin acceso a la gestion de usuarios'),
(3, 'Consulta',       'Solo lectura del inventario. No puede modificar informacion');

-- ---------------------------------------------------------------------
-- USUARIOS
--
-- password         = hash bcrypt con password_hash() (PASSWORD_DEFAULT)
-- password_legacy  = MD5 de la misma contrasena, que es lo que el modulo
--                    de autenticacion viejo de login.php sigue comparando.
--
-- admin    / Admin_2026!     rol Administrador
-- tecnico  / Tecnico_2026!   rol Tecnico
-- consulta / Consulta_2026!  rol Consulta
-- m.quiroga / Quiroga_2026!  rol Consulta. Cuenta de solo lectura usada
--                            como objetivo del ataque de fuerza bruta (LAB-09)
-- ---------------------------------------------------------------------
INSERT INTO `users` (`id`,`nombre`,`apellido`,`email`,`username`,`password`,`password_legacy`,`role_id`,`estado`,`created_at`,`updated_at`) VALUES
(1,'Carlos','Mendoza','carlos.mendoza@empresa.local','admin',   '$2y$10$Mw7MKBAX7lmcwocqOBzpf.2itYc5SYi1rU4kPX/7gAJ.NbYujB.uq','e7839c1cf69cd8730944596844426ff3',1,'Activo',TIMESTAMP(CURDATE() - INTERVAL 1029 DAY, '08:30:00'),TIMESTAMP(CURDATE() - INTERVAL 1064 DAY, '08:30:00')),
(2,'Lucia','Ferrer',  'lucia.ferrer@empresa.local',  'tecnico', '$2y$10$q0jl38CBWZHP5BCFzQKN2ePHBlQ1HShSkaKm/rMXJmlnwRU1mW1LG','5bac750dcdca69621a85e53957c0325d',2,'Activo',TIMESTAMP(CURDATE() - INTERVAL 1011 DAY, '09:15:00'),TIMESTAMP(CURDATE() - INTERVAL 1046 DAY, '09:15:00')),
(3,'Miguel','Sanz',   'miguel.sanz@empresa.local',   'consulta','$2y$10$HeF5fK/3SMqNPIm1t9sm5.ctoBqhDZEB/jGeN358rNjVnDuWLLb9e','d81aeacfb47c928c699c279270150f97',3,'Activo',TIMESTAMP(CURDATE() - INTERVAL 963 DAY, '14:05:00'),TIMESTAMP(CURDATE() - INTERVAL 998 DAY, '14:05:00')),
(4,'Marta','Quiroga', 'marta.quiroga@empresa.local','m.quiroga','$2y$10$/RjwmyneCX2yxOWU7JXcLuPn/F2piEMnvzR0u1DK674x0ba6TSUti','cbbc7ad3b8f335ac3ce31fed2c60806c',3,'Activo',TIMESTAMP(TIMESTAMP(CURDATE(), '16:40:00'), '11:20:00'),CURDATE() - INTERVAL 35 DAY),
(5,'Ana','Delgado',  'ana.delgado@empresa.local',   'a.delgado','$2y$10$bmme2vwpSBMmHMsf9poTWuc7YvC7Ou19bNpM7W9oXs.e2.M/dt/mO','801fbc871d9e32550ac7c303025893b5',2,'Inactivo',TIMESTAMP(CURDATE() - INTERVAL 577 DAY, '09:00:00'),TIMESTAMP(CURDATE() - INTERVAL 349 DAY, '09:00:00')),
(6,'Raul','Ibarra',  'raul.ibarra@empresa.local',   'r.ibarra','$2y$10$1hRIt6bDca.lGKm6tMH2aujDl30eg0UmZG8ilR.ROMqO83epafAKe','bd1dbee6f9af980767ce2355210d4273',1,'Activo',TIMESTAMP(CURDATE() - INTERVAL 721 DAY, '13:45:00'),TIMESTAMP(CURDATE() - INTERVAL 756 DAY, '13:45:00')),
(7,'Sofia','Navarro', 'sofia.navarro@empresa.local', 's.navarro','$2y$10$p3BB/rGhNgdwEIWS0Twm.uenN1rDS2Cn4z9E0PMNp0j1Ycx6nQ9rS','431551e5d119a05a7179511da1b663d4',3,'Activo',TIMESTAMP(CURDATE() - INTERVAL 490 DAY, '12:05:00'),TIMESTAMP(CURDATE() - INTERVAL 525 DAY, '12:05:00'));

-- ---------------------------------------------------------------------
-- EMPLEADOS (22) - repartidos en los 6 departamentos
-- ---------------------------------------------------------------------
INSERT INTO `employees` (`id`,`numero_empleado`,`nombre`,`apellido`,`email`,`departamento`,`puesto`,`telefono`,`notas_confidenciales`,`estado`) VALUES
(1, 'EMP-1001','Carlos','Mendoza','carlos.mendoza@empresa.local','Sistemas','Coordinador de Sistemas','+52 614 100 1101','Ingreso 2015. Titular de la cuenta de dominio empresa.local. Doble factor obligatorio desde 2024.','Activo'),
(2, 'EMP-1002','Lucia','Ferrer','lucia.ferrer@empresa.local','Sistemas','Tecnico de Soporte N1','+52 614 100 1102','Ingreso 2015. Certificacion Cisco CCNA, renovacion 2026-03. Accesista al rack principal.','Activo'),
(3, 'EMP-1003','Miguel','Sanz','miguel.sanz@empresa.local','Sistemas','Analista de Redes','+52 614 100 1103','Ingreso 2015. Autorizado para cambios de configuracion en switches de piso.','Activo'),
(4, 'EMP-1004','Raul','Ibarra','raul.ibarra@empresa.local','Sistemas','Administrador de Servidores','+52 614 100 1104','Ingreso 2015. Unico con acceso al hypervisor. Respaldo de claves en custodia sellada.','Activo'),
(5, 'EMP-1005','Ana','Delgado','ana.delgado@empresa.local','Sistemas','Tecnico de Soporte N2','+52 614 100 1105','Ingreso 2021. Baja medica prolongada desde 2026-07. Suspension de accesos 2026-08.','Inactivo'),
(6, 'EMP-1006','Patricia','Ramos','patricia.ramos@empresa.local','Sistemas','Especialista en Seguridad','+52 614 100 1106','Ingreso 2016. Analista de seguridad. Homologacion CISSP en curso.','Activo'),
(7, 'EMP-1007','Jorge','Villasmil','jorge.villasmil@empresa.local','Sistemas','Tecnico de Hardware','+52 614 100 1107','Ingreso 2016. Responsable de compra de refacciones y garantia de fabricante.','Activo'),
(8, 'EMP-1008','Elena','Cortes','elena.cortes@empresa.local','Contabilidad','Contadora General','+52 614 200 2208','Ingreso 2016. Firma digital habilitada por el SAT. Custodia de libros mayores.','Activo'),
(9, 'EMP-1009','Fernando','Aguilar','fernando.aguilar@empresa.local','Contabilidad','Auxiliar Contable','+52 614 200 2209','Ingreso 2018. Acceso al sistema contable en modo solo lectura.','Activo'),
(10, 'EMP-1010','Gabriela','Nunez','gabriela.nunez@empresa.local','Contabilidad','Analista Contable','+52 614 200 2210','Ingreso 2018. Concentracion de pago a proveedores.','Activo'),
(11, 'EMP-1011','Hector','Salas','hector.salas@empresa.local','Recursos Humanos','Coordinador de RRHH','+52 614 300 3311','Ingreso 2016. Responsable del expediente laboral. Acceso a datos personales completos.','Activo'),
(12, 'EMP-1012','Monica','Bravo','monica.bravo@empresa.local','Recursos Humanos','Analista de RRHH','+52 614 300 3312','Ingreso 2019. Antecedentes y datos de contacto de emergencia.','Activo'),
(13, 'EMP-1013','Diego','Fuentes','diego.fuentes@empresa.local','Recursos Humanos','Asistente Administrativo','+52 614 300 3313','Ingreso 2020. Captura de adelantos y horas extra.','Activo'),
(14, 'EMP-1014','Veronica','Luna','veronica.luna@empresa.local','Ventas','Ejecutiva de Ventas','+52 614 400 4414','Ingreso 2019. Comisiones variables en nomina.','Activo'),
(15, 'EMP-1015','Andres','Pardo','andres.pardo@empresa.local','Ventas','Ejecutivo de Ventas','+52 614 400 4415','Ingreso 2019. Ruta de clientes y pipeline critico.','Activo'),
(16, 'EMP-1016','Silvia','Marquez','silvia.marquez@empresa.local','Ventas','Asistente de Ventas','+52 614 400 4416','Ingreso 2020. Atencion a clientes en piso.','Activo'),
(17, 'EMP-1017','Ricardo','Olmo','ricardo.olmo@empresa.local','Direccion','Director General','+52 614 500 5517','Ingreso 2011. Accesos al sistema financiero. Autoriza compras.','Activo'),
(18, 'EMP-1018','Marta','Quintana','marta.quintana@empresa.local','Direccion','Directora Administrativa','+52 614 500 5518','Ingreso 2011. Supervisora de pagos. Autoriza transferencias.','Activo'),
(19, 'EMP-1019','Pablo','Ceballos','pablo.ceballos@empresa.local','Direccion','Gerente de Operaciones','+52 614 500 5519','Ingreso 2012. Responsable de logistica de sitio.','Activo'),
(20, 'EMP-1020','Natalia','Rendon','natalia.rendon@empresa.local','Soporte','Tecnica de Soporte N1','+52 614 600 6620','Ingreso 2023. Turno rotativo N1.','Activo'),
(21, 'EMP-1021','Ivan','Beltran','ivan.beltran@empresa.local','Soporte','Coordinador de Soporte','+52 614 600 6621','Ingreso 2023. Escalamiento de incidentes de usuario final.','Activo'),
(22, 'EMP-1022','Rosa','Maldonado','rosa.maldonado@empresa.local','Soporte','Recepcionista de Soporte','+52 614 600 6622','Ingreso 2024. Recepcion y control de accesos de visitantes.','Activo');

-- ---------------------------------------------------------------------
-- MARCAS (7)
-- ---------------------------------------------------------------------
INSERT INTO `brands` (`id`,`nombre`,`descripcion`,`estado`) VALUES
(1,'Dell',     'Fabricante de PCs, laptops y servidores de empresa','Activo'),
(2,'HP',       'Hardware y soluciones de impresion para oficina','Activo'),
(3,'Lenovo',   'Portatiles y equipos de escritorio para empresa','Activo'),
(4,'ASUS',     'Portatiles,Motobordes y estaciones de trabajo','Activo'),
(5,'Acer',     'Portatiles y monitores de gama media','Activo'),
(6,'Apple',    'Equipos Mac para diseno y direccion','Activo'),
(7,'Microsoft','Equipos de superficie y laptops Surface','Activo');

-- ---------------------------------------------------------------------
-- CATEGORIAS (8)
-- ---------------------------------------------------------------------
INSERT INTO `categories` (`id`,`nombre`,`descripcion`,`estado`) VALUES
(1,'Desktop',  'Equipo de escritorio de torre para puesto de trabajo','Activo'),
(2,'Laptop',   'Portatil para movilidad del usuario','Activo'),
(3,'Servidor', 'Servidor de aplicaciones o de archivos','Activo'),
(4,'Monitor',  'Monitor de pantalla para puesto de trabajo','Activo'),
(5,'Impresora','Impresora para area compartida','Activo'),
(6,'Switch',   'Switch de red para segmento de oficina','Activo'),
(7,'Router',   'Router de borde o de sucursal','Activo'),
(8,'UPS',      'Sistema de alimentacion ininterrumpida','Activo');

-- ---------------------------------------------------------------------
-- EQUIPOS (55)
-- Estados repartidos: Disponible / Asignado / Mantenimiento / Baja
-- description: solo texto limpio. El payload LAB-04 se carga en vivo.
-- ---------------------------------------------------------------------
INSERT INTO `equipment` (`id`,`inventory_number`,`serial_number`,`brand_id`,`category_id`,`model`,`processor`,`ram`,`storage`,`operating_system`,`purchase_date`,`price`,`location`,`status`,`description`) VALUES
(1,'INV-000001','DL7060SM4K2A1',1,1,'Dell OptiPlex 7060','Intel Core i5-8500 3.0GHz','8 GB DDR4','500 GB HDD','Windows 10 Pro','2019-03-12',685.00,'Planta Baja - Sistemas','Asignado','Equipo de escritorio del area de Sistemas'),
(2,'INV-000002','DL7060SM4K2A2',1,1,'Dell OptiPlex 7060','Intel Core i5-8500 3.0GHz','8 GB DDR4','500 GB HDD','Windows 10 Pro','2019-03-12',685.00,'Planta Baja - Sistemas','Asignado','Equipo de escritorio del area de Sistemas'),
(3,'INV-000003','DL7060SM4K2A3',1,1,'Dell OptiPlex 7060','Intel Core i5-8500 3.0GHz','16 GB DDR4','1 TB HDD','Windows 10 Pro','2019-06-04',742.50,'Piso 1 - Contabilidad','Asignado','Equipo para contabilidad'),
(4,'INV-000004','DL5420LT9B1',1,2,'Dell Latitude 5420','Intel Core i5-1135G7','16 GB DDR4','512 GB SSD','Windows 11 Pro','2021-09-18',1120.00,'Piso 2 - Ventas','Asignado','Portatil para financiero comercial'),
(5,'INV-000005','DL5420LT9B2',1,2,'Dell Latitude 5420','Intel Core i5-1135G7','8 GB DDR4','256 GB SSD','Windows 11 Pro','2021-09-18',980.00,'Piso 1 - Ventas','Asignado','Portatil para ejecutivo de ventas'),
(6,'INV-000006','DL5420LT9B3',1,2,'Dell Latitude 5420','Intel Core i7-1185G7','16 GB DDR4','512 GB SSD','Windows 11 Pro','2022-02-11',1345.00,'Planta Baja - Direccion','Asignado','Portatil de direccion'),
(7,'INV-000007','HP600PD6X1',2,1,'HP ProDesk 600 G5','Intel Core i5-10500 3.0GHz','8 GB DDR4','1 TB HDD','Windows 10 Pro','2020-07-22',720.00,'Piso 1 - Contabilidad','Asignado','Equipo de escritorio contable'),
(8,'INV-000008','HP600PD6X2',2,1,'HP ProDesk 600 G5','Intel Core i5-10500 3.0GHz','8 GB DDR4','1 TB HDD','Windows 10 Pro','2020-07-22',720.00,'Piso 1 - Contabilidad','Disponible','Equipo en espera de asignacion'),
(9,'INV-000009','HP840EB4Y1',2,2,'HP EliteBook 840 G7','Intel Core i5-10210U','16 GB DDR4','512 GB SSD','Windows 10 Pro','2021-05-30',1180.00,'Piso 2 - Ventas','Asignado','Portatil corporativo'),
(10,'INV-000010','HP840EB4Y2',2,2,'HP EliteBook 840 G7','Intel Core i7-10510U','16 GB DDR4','1 TB SSD','Windows 10 Pro','2021-05-30',1290.00,'Piso 2 - RRHH','Asignado','Portatil de Recursos Humanos'),
(11,'INV-000011','LNT480TH1',3,2,'Lenovo ThinkPad T480','Intel Core i5-8250U','16 GB DDR4','512 GB SSD','Windows 10 Pro','2020-11-05',1050.00,'Piso 1 - Sistemas','Asignado','Portatil tecnico de campo'),
(12,'INV-000012','LNT480TH2',3,2,'Lenovo ThinkPad T480','Intel Core i7-8550U','16 GB DDR4','1 TB SSD','Windows 10 Pro','2020-11-05',1180.00,'Piso 1 - Sistemas','Asignado','Portatil tecnico de campo'),
(13,'INV-000013','LNM720TC1',3,1,'Lenovo ThinkCentre M720','Intel Core i5-8500 3.0GHz','8 GB DDR4','1 TB HDD','Windows 10 Pro','2019-08-14',760.00,'Planta Baja - Soporte','Asignado','Equipo de soporte al usuario'),
(14,'INV-000014','LNM720TC2',3,1,'Lenovo ThinkCentre M720','Intel Core i5-8500 3.0GHz','16 GB DDR4','1 TB HDD','Windows 10 Pro','2019-08-14',790.00,'Planta Baja - Soporte','Disponible','Repuesto de escritorio'),
(15,'INV-000015','ASB14BU1',4,2,'ASUS ExpertBook B14','Intel Core i5-1135G7','16 GB DDR4','512 GB SSD','Windows 11 Pro','2022-06-09',1080.00,'Piso 2 - Ventas','Asignado','Portatil ligero para ventas'),
(16,'INV-000016','ASWPRO551',4,1,'ASUS ProArt Station','Intel Core i7-11700','32 GB DDR4','1 TB NVMe SSD','Windows 11 Pro','2022-10-21',2450.00,'Planta Baja - Diseno','Disponible','Estacion de trabajo de diseno'),
(17,'INV-000017','ACRN5151',5,2,'Acer Aspire 5','Intel Core i5-1135G7','8 GB DDR4','512 GB SSD','Windows 11 Home','2021-12-03',690.00,'Piso 1 - RRHH','Asignado','Portatil de oficina'),
(18,'INV-000018','APMN2411',6,2,'Apple MacBook Air M2','Apple M2 8 nucleos','8 GB unificada','256 GB SSD','macOS Sonoma','2023-04-17',1199.00,'Planta Baja - Direccion','Asignado','Portatil de direccion'),
(19,'INV-000019','APMP1421',6,2,'Apple MacBook Pro 14','Apple M3 Pro 11 nucleos','18 GB unificada','512 GB SSD','macOS Sonoma','2023-11-28',2199.00,'Planta Baja - Diseno','Asignado','Portatil de diseno grafico'),
(20,'INV-000020','MSFP3121',7,2,'Microsoft Surface Pro 9','Intel Core i5-1235U','8 GB LPDDR5','512 GB SSD','Windows 11 Pro','2023-03-30',1399.00,'Piso 2 - Direccion','Asignado','Equipo de presentaciones'),
(21,'INV-000021','DLSR7711',1,3,'Dell PowerEdge R740','Intel Xeon Gold 5220 2x','64 GB ECC RDIMM','4 x 1.2 TB SAS','VMware ESXi 7','2018-11-20',4850.00,'Sala de Servidores - Rack 1','Asignado','Servidor de virtualizacion principal'),
(22,'INV-000022','DLSR7712',1,3,'Dell PowerEdge R740','Intel Xeon Silver 4210 2x','32 GB ECC RDIMM','2 x 1.2 TB SAS','Windows Server 2019','2018-11-20',3620.00,'Sala de Servidores - Rack 1','Asignado','Servidor de archivos'),
(23,'INV-000023','HPSR2271',2,3,'HP ProLiant DL380 Gen10','Intel Xeon Gold 5218 2x','64 GB ECC RDIMM','8 x 1.2 TB SAS','Windows Server 2019','2020-02-11',5230.00,'Sala de Servidores - Rack 2','Asignado','Servidor de base de datos'),
(24,'INV-000024','DLSR7713',1,3,'Dell PowerEdge T640','Intel Xeon Silver 4214','32 GB ECC RDIMM','2 x 2 TB SATA','CentOS 8','2019-07-08',2980.00,'Sala de Servidores - Rack 2','Mantenimiento','Servidor de respaldos - en mantenimiento preventivo'),
(25,'INV-000025','DLMON2411',1,4,'Dell UltraSharp U2415','No aplica','No aplica','No aplica','No aplica','2017-05-19',245.00,'Piso 1 - Contabilidad','Asignado','Monitor principal de escritorio'),
(26,'INV-000026','DLMON2412',1,4,'Dell UltraSharp U2415','No aplica','No aplica','No aplica','No aplica','2017-05-19',245.00,'Piso 1 - Contabilidad','Asignado','Monitor principal de escritorio'),
(27,'INV-000027','HPMON2711',2,4,'HP EliteDisplay E233','No aplica','No aplica','No aplica','No aplica','2018-04-25',198.00,'Piso 2 - Ventas','Asignado','Monitor de ventas'),
(28,'INV-000028','HPMON2712',2,4,'HP EliteDisplay E233','No aplica','No aplica','No aplica','No aplica','2018-04-25',198.00,'Piso 2 - Ventas','Asignado','Monitor de ventas'),
(29,'INV-000029','LVMON2411',3,4,'Lenovo ThinkVision P24','No aplica','No aplica','No aplica','No aplica','2019-09-12',210.00,'Planta Baja - Soporte','Asignado','Monitor de soporte'),
(30,'INV-000030','ACMON2411',5,4,'Acer CB241y','No aplica','No aplica','No aplica','No aplica','2018-11-08',165.00,'Piso 1 - RRHH','Disponible','Monitor en almacen'),
(31,'INV-000031','HPMON2713',2,4,'HP Z27k G3 4K','No aplica','No aplica','No aplica','No aplica','2021-03-15',580.00,'Planta Baja - Diseno','Asignado','Monitor 4K de diseno'),
(32,'INV-000032','HPPR4311',2,5,'HP LaserJet Pro M404dn','No aplica','No aplica','No aplica','No aplica','2019-10-30',389.00,'Planta Baja - Recepcion','Asignado','Impresora compartida de planta baja'),
(33,'INV-000033','HPPR4312',2,5,'HP LaserJet Pro M428fdn','No aplica','No aplica','No aplica','No aplica','2021-07-14',549.00,'Piso 1 - Contabilidad','Asignado','Impresora con escaner de contabilidad'),
(34,'INV-000034','HPPR4313',2,5,'HP OfficeJet Pro 9020','No aplica','No aplica','No aplica','No aplica','2018-06-21',429.00,'Piso 2 - Ventas','Mantenimiento','Impresora de ventas - atasco de papel'),
(35,'INV-000035','CANPR2411',4,5,'Canon imageRUNNER 2206','No aplica','No aplica','No aplica','No aplica','2017-11-10',1180.00,'Planta Baja - Direccion','Asignado','Impresora departamental'),
(36,'INV-000036','CANSW2411',4,6,'ASUS Switch GX-E 24-PORT','No aplica','No aplica','No aplica','SwitchOS','2020-08-17',295.00,'Sala de Servidores - Rack 3','Asignado','Switch de acceso piso 1'),
(37,'INV-000037','CANSW2412',4,6,'ASUS Switch GX-E 24-PORT','No aplica','No aplica','No aplica','SwitchOS','2020-08-17',295.00,'Sala de Servidores - Rack 3','Asignado','Switch de acceso piso 2'),
(38,'INV-000038','DLNW2411',1,6,'Dell Networking N2224PX','No aplica','No aplica','No aplica','Dell OS10','2021-02-09',640.00,'Sala de Servidores - Rack 3','Asignado','Switch agregacion piso 1'),
(39,'INV-000039','TPR2411',3,6,'TP-Link TL-SG1016D','No aplica','No aplica','No aplica','No aplicable','2018-03-22',98.00,'Piso 2 - Rack local','Disponible','Switch de 16 puertos en almacen'),
(40,'INV-000040','DLRT2411',1,7,'Dell N1525P','No aplica','No aplica','No aplica','Dell Networking OS','2019-01-30',225.00,'Sala de Servidores - Rack 4','Asignado','Router de borde principal'),
(41,'INV-000041','DLRT2412',1,7,'Dell N1525P','No aplica','No aplica','No aplica','Dell Networking OS','2019-01-30',225.00,'Sala de Servidores - Rack 4','Asignado','Router de sucursal'),
(42,'INV-000042','TPR2412',3,7,'TP-Link Archer C6 AC1200','No aplica','No aplica','No aplica','No aplicable','2020-05-14',58.00,'Piso 1 - Rack local','Disponible','Router inalambrico de respaldo'),
(43,'INV-000043','APC9411',7,8,'APC Smart-UPS 1500VA','No aplica','No aplica','Baterias internas','No aplica','2018-12-05',720.00,'Sala de Servidores - Rack 1','Asignado','UPS del servidor de virtualizacion'),
(44,'INV-000044','APC9412',7,8,'APC Back-UPS Pro 1500','No aplica','No aplica','Baterias internas','No aplica','2019-09-26',410.00,'Planta Baja - Sala IT','Asignado','UPS de respaldo de planta baja'),
(45,'INV-000045','APC9413',7,8,'APC Smart-UPS 3000VA','No aplica','No aplica','Baterias internas','No aplica','2020-04-11',1350.00,'Sala de Servidores - Rack 2','Asignado','UPS de sala de servidores'),
(46,'INV-000046','DL7060SM4K2A4',1,1,'Dell OptiPlex 7060','Intel Core i3-8100 3.6GHz','8 GB DDR4','256 GB HDD','Windows 10 Pro','2017-05-30',540.00,'Piso 1 - RRHH','Baja','Equipo fuera de servicio - circuito motherboard'),
(47,'INV-000047','DL7060SM4K2A5',1,1,'Dell OptiPlex 7060','Intel Core i3-8100 3.6GHz','8 GB DDR4','256 GB HDD','Windows 10 Pro','2017-05-30',540.00,'Piso 1 - RRHH','Baja','Equipo fuera de servicio - circuito motherboard'),
(48,'INV-000048','HP600PD6X3',2,1,'HP ProDesk 400 G3','Intel Core i5-6500 3.2GHz','8 GB DDR4','500 GB HDD','Windows 7 Pro','2015-08-14',410.00,'Almacen - Estanteria 2','Baja','Equipo obsoleto - dado de baja por antiguedad'),
(49,'INV-000049','HPMON2714',2,4,'HP EliteDisplay E221','No aplica','No aplica','No aplica','No aplica','2015-02-09',132.00,'Almacen - Estanteria 3','Baja','Monitor con panel danado - baja'),
(50,'INV-000050','APMP1422',6,2,'Apple MacBook Pro 13','Intel Core i5-8259U','16 GB unificada','512 GB SSD','macOS Monterey','2019-04-02',1599.00,'Piso 2 - Ventas','Mantenimiento','Bateria degradada - reemplazo en tramite'),
(51,'INV-000051','LNT480TH3',3,2,'Lenovo ThinkPad T480','Intel Core i5-8250U','8 GB DDR4','256 GB SSD','Windows 10 Pro','2018-10-15',890.00,'Piso 1 - Sistemas','Disponible','Portatil de respaldo disponible'),
(52,'INV-000052','LNT480TH4',3,2,'Lenovo ThinkPad T480','Intel Core i5-8250U','8 GB DDR4','256 GB SSD','Windows 10 Pro','2018-10-15',890.00,'Piso 1 - Sistemas','Disponible','Portatil de respaldo disponible'),
(53,'INV-000053','DLSR7714',1,3,'Dell PowerEdge R730','Intel Xeon E5-2680 v4 2x','128 GB ECC RDIMM','8 x 600 GB SAS','VMware ESXi 6.7','2016-10-08',3950.00,'Sala de Servidores - Rack 1','Mantenimiento','Actualizacion de firmware en curso'),
(54,'INV-000054','ASB14BU2',4,2,'ASUS ExpertBook B14','Intel Core i5-1135G7','16 GB DDR4','512 GB SSD','Windows 11 Pro','2022-06-09',1080.00,'Piso 2 - Ventas','Disponible','Portatil disponible en almacen'),
(55,'INV-000055','MSFP3122',7,2,'Microsoft Surface Laptop 5','Intel Core i5-1235U','8 GB LPDDR5','512 GB SSD','Windows 11 Pro','2023-08-22',1099.00,'Planta Baja - Direccion','Disponible','Portatil de direccion disponible');

-- ---------------------------------------------------------------------
-- ASIGNACIONES (24)
-- 20 activas (fecha_devolucion NULL) + 4 devueltas historicas
-- Regla: un equipo solo puede tener UNA asignacion activa.
--   Los equipos con fecha_devolucion NULL son los que estan en estado
--   'Asignado' en la tabla equipment.
-- ---------------------------------------------------------------------
INSERT INTO `assignments` (`id`,`equipment_id`,`employee_id`,`fecha_asignacion`,`fecha_devolucion`,`observaciones`) VALUES
(1, 1, 1,CURDATE() - INTERVAL 1096 DAY,NULL,             'Equipo inicial del coordinador de Sistemas'),
(2, 2, 2,CURDATE() - INTERVAL 1061 DAY,NULL,             'Estacion de trabajo del tecnico N1'),
(3, 3, 8,CURDATE() - INTERVAL 1046 DAY,NULL,             'Contabilidad - stationary de facturacion'),
(4, 4,14,CURDATE() - INTERVAL 732 DAY,NULL,             'Ejecutiva comercial con movilidad'),
(5, 5,15,CURDATE() - INTERVAL 732 DAY,NULL,             'Ejecutivo comercial con movilidad'),
(6, 6,17,CURDATE() - INTERVAL 682 DAY,NULL,             'Portatil reserved para la direccion general'),
(7, 7, 9,CURDATE() - INTERVAL 970 DAY,NULL,             'Auxiliar contable'),
(8, 8, 9,CURDATE() - INTERVAL 970 DAY,NULL,             'Equipo compartido contabilidad'),
(9, 9,14,CURDATE() - INTERVAL 648 DAY,NULL,             'Portatil corporativo de ventas'),
(10,10,11,CURDATE() - INTERVAL 648 DAY,NULL,             'Portatil del coordinador de RRHH'),
(11,11, 2,CURDATE() - INTERVAL 935 DAY,NULL,             'Portatil tecnico de campo'),
(12,12, 3,CURDATE() - INTERVAL 935 DAY,NULL,             'Portatil tecnico de campo'),
(13,13,20,CURDATE() - INTERVAL 851 DAY,NULL,             'Equipo de soporte al usuario'),
(14,14,20,CURDATE() - INTERVAL 851 DAY,NULL,             'Segundo puesto de soporte'),
(15,15,16,CURDATE() - INTERVAL 494 DAY,NULL,             'Asistente de ventas'),
(16,16,19,CURDATE() - INTERVAL 326 DAY,NULL,             'Estacion de trabajo de diseno'),
(17,17,12,CURDATE() - INTERVAL 592 DAY,NULL,             'Analista de RRHH'),
(18,18,18,CURDATE() - INTERVAL 242 DAY,NULL,             'Directora administrativa'),
(19,19,19,CURDATE() - INTERVAL 4 DAY,NULL,             'Portatil de diseno grafico'),
(20,20,17,CURDATE() - INTERVAL 164 DAY,NULL,             'Equipo para presentaciones de direccion'),
(21,21, 4,CURDATE() - INTERVAL 1823 DAY,NULL,             'Servidor de virtualizacion principal - administracion'),
(22,22, 4,CURDATE() - INTERVAL 1823 DAY,NULL,             'Servidor de archivos - administracion'),
(23,23, 4,CURDATE() - INTERVAL 1320 DAY,NULL,             'Servidor de base de datos - administracion'),
(24, 5,15,CURDATE() - INTERVAL 1411 DAY,CURDATE() - INTERVAL 821 DAY,    'DEVUELTO - portatil anterior del ejecutivo comercial'),
(25, 3, 8,CURDATE() - INTERVAL 1432 DAY,CURDATE() - INTERVAL 1109 DAY,    'DEVUELTO - equipo anterior de contabilidad'),
(26,13,22,CURDATE() - INTERVAL 1243 DAY,CURDATE() - INTERVAL 923 DAY,    'DEVUELTO - traslado de puesto'),
(27,19,16,CURDATE() - INTERVAL 718 DAY,CURDATE() - INTERVAL 425 DAY,    'DEVUELTO - portatil anterior de ventas');

-- ---------------------------------------------------------------------
-- MANTENIMIENTOS (18)
-- 3 equipos en estado 'Mantenimiento': 24, 34, 50, 53
-- ---------------------------------------------------------------------
INSERT INTO `maintenance` (`id`,`equipment_id`,`technician_id`,`type`,`description`,`cost`,`maintenance_date`,`status`) VALUES
(1, 24,2,'Preventivo','Limpieza interna y cambio de pasta termica. Verificacion de RAID.',185.00,CURDATE() - INTERVAL 20 DAY,'En Proceso'),
(2, 24,2,'Correctivo','Falla en segundo disco SAS del arreglo RAID 1. Pendiente de repuesto.',0.00,CURDATE() + INTERVAL 9 DAY,'Pendiente'),
(3, 34,2,'Correctivo','Atasco constante de papel en unidad duplex. Desmontaje y limpieza del rodillo.',95.50,CURDATE() - INTERVAL 13 DAY,'En Proceso'),
(4, 50,2,'Correctivo','Bateria con 3 horas de autonomia. Reemplazo de bateria y calibracion.',215.00,CURDATE() + INTERVAL 23 DAY,'Pendiente'),
(5, 53,4,'Preventivo','Actualizacion de firmware de iDRAC y controladora SAS.',0.00,CURDATE() - INTERVAL 25 DAY,'En Proceso'),
(6, 53,4,'Correctivo','Reinicio constante de un nodo ESXi. Diagnostico de memoria ECC.',340.00,CURDATE() - INTERVAL 30 DAY,'Completado'),
(7, 1,2,'Preventivo','Revision general anual. Limpieza de interna y verificacion de temperaturas.',45.00,CURDATE() - INTERVAL 76 DAY,'Completado'),
(8, 7,2,'Preventivo','Revision general anual del equipo de contabilidad.',45.00,CURDATE() - INTERVAL 76 DAY,'Completado'),
(9, 13,2,'Correctivo','Reemplazo de fuente de poder por falla de encendido.',120.00,CURDATE() - INTERVAL 143 DAY,'Completado'),
(10,21,4,'Preventivo','Actualizacion de firmware del servidor de virtualizacion.',0.00,CURDATE() - INTERVAL 125 DAY,'Completado'),
(11,22,4,'Preventivo','Verificacion de salud de disco SMART y reconstruccion de RAID 5.',0.00,CURDATE() - INTERVAL 125 DAY,'Completado'),
(12,23,4,'Correctivo','Reemplazo de fuente de alimentacion redundante B.',280.00,CURDATE() - INTERVAL 169 DAY,'Completado'),
(13,43,4,'Preventivo','Reemplazo de bateria del UPS y prueba de corte simulado.',0.00,CURDATE() - INTERVAL 194 DAY,'Completado'),
(14,19,2,'Diagnostico','Corte de energia no programado. Revision de estado general del portatil.',0.00,CURDATE() - INTERVAL 238 DAY,'Completado'),
(15,17,2,'Diagnostico','Puerto USB derecho no reconoce dispositivos. Revision de placa.',0.00,CURDATE() - INTERVAL 250 DAY,'Completado'),
(16,32,2,'Preventivo','Limpieza de rodillos y calibracion de calidad de impresion.',65.00,CURDATE() - INTERVAL 65 DAY,'Completado'),
(17,44,4,'Diagnostico','UPS no enciende con bateria. Verificacion de circuito de carga.',0.00,CURDATE() - INTERVAL 292 DAY,'Completado'),
(18,10,2,'Preventivo','Revision anual y limpieza de teclado y bisagra.',45.00,CURDATE() - INTERVAL 30 DAY,'Pendiente');

-- ---------------------------------------------------------------------
-- CONSISTENCIA DE ESTADOS
-- Regla de negocio: status='Asignado' <=> existe asignacion activa
-- (fecha_devolucion IS NULL). Los datos anteriores dejaban 17 equipos de
-- infraestructura (monitores, impresoras, switches, routers, UPS) en estado
-- 'Asignado' sin asignacion activa. Se les asigna un responsable segun su
-- area. Y a la inversa, 3 equipos con asignacion activa quedaban como
-- 'Disponible'.
-- ---------------------------------------------------------------------
UPDATE `equipment` SET `status`='Asignado'
WHERE `id` IN (8,14,16);

-- Infraestructura de red -> Analista de Redes / Administrador de Servidores
INSERT INTO `assignments` (`equipment_id`,`employee_id`,`fecha_asignacion`,`fecha_devolucion`,`observaciones`) VALUES
(36, 3,CURDATE() - INTERVAL 1087 DAY,NULL,'Switch de acceso piso 1 - responsable Analista de Redes'),
(37, 3,CURDATE() - INTERVAL 1087 DAY,NULL,'Switch de acceso piso 2 - responsable Analista de Redes'),
(38, 4,CURDATE() - INTERVAL 1038 DAY,NULL,'Switch de agregacion piso 1 - responsable Administrador de Servidores'),
(40, 3,CURDATE() - INTERVAL 1071 DAY,NULL,'Router de borde principal - responsable Analista de Redes'),
(41, 3,CURDATE() - INTERVAL 1071 DAY,NULL,'Router de sucursal - responsable Analista de Redes'),
(43, 4,CURDATE() - INTERVAL 1005 DAY,NULL,'UPS del servidor de virtualizacion - responsable Administrador de Servidores'),
(44, 4,CURDATE() - INTERVAL 1005 DAY,NULL,'UPS de planta baja - responsable Administrador de Servidores'),
(45, 4,CURDATE() - INTERVAL 1005 DAY,NULL,'UPS de sala de servidores - responsable Administrador de Servidores'),
-- Monitores de puestos de trabajo
(25, 9,CURDATE() - INTERVAL 970 DAY,NULL,'Monitor principal de contabilidad - auxiliar contable'),
(26,10,CURDATE() - INTERVAL 970 DAY,NULL,'Monitor principal de contabilidad - analista contable'),
(27,15,CURDATE() - INTERVAL 648 DAY,NULL,'Monitor de ventas - ejecutivo comercial'),
(28,16,CURDATE() - INTERVAL 648 DAY,NULL,'Monitor de ventas - asistente de ventas'),
(29,22,CURDATE() - INTERVAL 851 DAY,NULL,'Monitor de recepcion de soporte'),
(31,19,CURDATE() - INTERVAL 955 DAY,NULL,'Monitor 4K de diseno grafico'),
-- Impresoras compartidas por area
(32,22,CURDATE() - INTERVAL 833 DAY,NULL,'Impresora compartida de planta baja - recepcion'),
(33, 8,CURDATE() - INTERVAL 925 DAY,NULL,'Impresora de contabilidad con escaner'),
(35,17,CURDATE() - INTERVAL 788 DAY,NULL,'Impresora departamental de direccion');

-- ---------------------------------------------------------------------
-- HISTORIAL DE ACCIONES (audit_log)
--
-- Todas las fechas son relativas a CURDATE(), de modo que el panel de
-- "Actividad reciente" del dashboard se vea siempre como un sistema en uso
-- activo, sin importar el dia en que se importe el seed.
--
-- Las acciones replican el uso real de un sistema de inventario: altas y
-- bajas de personal, cambios de estado de equipos, entregas y devoluciones,
-- y el mantenimiento del catalogo.
-- ---------------------------------------------------------------------
INSERT INTO `audit_log` (`usuario`,`accion`,`modulo`,`ip`,`descripcion`,`created_at`) VALUES
('admin',   'CREATE','equipment','192.168.100.34','Registro el equipo INV-000053 Dell PowerEdge R730 (Mantenimiento)',TIMESTAMP(CURDATE() - INTERVAL 967 DAY, '09:40:00')),
('admin',   'CREATE','employees','192.168.100.34','Registro al empleado EMP-1022 Rosa Maldonado (Recepcionista de Soporte)',TIMESTAMP(CURDATE() - INTERVAL 967 DAY, '08:12:00')),
('tecnico', 'UPDATE','maintenance','192.168.100.51','Actualizo el mantenimiento 53 a estado En Proceso',TIMESTAMP(CURDATE() - INTERVAL 966 DAY, '10:05:00')),
('admin',   'UPDATE','users','192.168.100.34','Actualizo el usuario r.ibarra (id 6)',TIMESTAMP(CURDATE() - INTERVAL 965 DAY, '11:22:00')),
('tecnico', 'CREATE','assignments','192.168.100.51','Asigno el equipo INV-000050 Apple MacBook Pro 13 a Silvia Marquez',TIMESTAMP(CURDATE() - INTERVAL 949 DAY, '14:30:00')),
('tecnico', 'UPDATE','maintenance','192.168.100.51','Registro el mantenimiento 50: bateria con 3 horas de autonomia',TIMESTAMP(CURDATE() - INTERVAL 946 DAY, '09:25:00')),
('admin',   'UPDATE','equipment','192.168.100.34','Cambio el estado del equipo INV-000050 a Mantenimiento',TIMESTAMP(CURDATE() - INTERVAL 946 DAY, '09:18:00')),
('admin',   'CREATE','users','192.168.100.34','Registro el usuario s.navarro (id 7)',TIMESTAMP(CURDATE() - INTERVAL 884 DAY, '13:45:00')),
('tecnico', 'CREATE','assignments','192.168.100.51','Asigno el equipo INV-000019 Apple MacBook Pro 14 a Pablo Ceballos',TIMESTAMP(CURDATE() - INTERVAL 743 DAY, '16:20:00')),
('admin',   'UPDATE','equipment','192.168.100.34','Cambio el estado del equipo INV-000019 a Disponible',TIMESTAMP(CURDATE() - INTERVAL 743 DAY, '16:12:00')),
('tecnico', 'UPDATE','assignments','192.168.100.51','Registro la devolucion del equipo INV-000019 Apple MacBook Pro 14',TIMESTAMP(CURDATE() - INTERVAL 743 DAY, '16:10:00')),
('admin',   'UPDATE','users','192.168.100.34','Cambio el estado del usuario a.delgado a Inactivo',TIMESTAMP(CURDATE() - INTERVAL 708 DAY, '17:05:00')),
('tecnico', 'UPDATE','maintenance','192.168.100.51','Cerró el mantenimiento 44 del UPS APC Back-UPS Pro 1500',TIMESTAMP(CURDATE() - INTERVAL 662 DAY, '11:40:00')),
('admin',   'UPDATE','brands','192.168.100.34','Actualizo la marca ASUS: Portatiles, Motobordes y estaciones de trabajo',TIMESTAMP(CURDATE() - INTERVAL 607 DAY, '09:02:00')),
('admin',   'CREATE','equipment','192.168.100.34','Registro el equipo INV-000054 ASUS ExpertBook B14',TIMESTAMP(CURDATE() - INTERVAL 607 DAY, '08:55:00')),
('tecnico', 'CREATE','maintenance','192.168.100.51','Programo el mantenimiento preventivo del servidor Dell PowerEdge R730',TIMESTAMP(CURDATE() - INTERVAL 554 DAY, '10:30:00')),
('admin',   'CREATE','users','192.168.100.34','Registro el usuario m.quiroga (id 4)',TIMESTAMP(CURDATE() - INTERVAL 491 DAY, '15:15:00')),
('tecnico', 'UPDATE','equipment','192.168.100.51','Cambio la ubicacion del equipo INV-000043 APC Smart-UPS 1500VA a Rack 1',TIMESTAMP(CURDATE() - INTERVAL 445 DAY, '12:00:00')),
('tecnico', 'UPDATE','maintenance','192.168.100.51','Registro la baja del monitor INV-000049 por panel danado',TIMESTAMP(CURDATE() - INTERVAL 386 DAY, '09:48:00')),
('admin',   'UPDATE','equipment','192.168.100.34','Cambio el estado del equipo INV-000049 HP EliteDisplay E221 a Baja',TIMESTAMP(CURDATE() - INTERVAL 386 DAY, '09:45:00')),
('admin',   'UPDATE','users','192.168.100.34','Actualizo el usuario tecnico (id 2)',TIMESTAMP(CURDATE() - INTERVAL 323 DAY, '14:10:00')),
('tecnico', 'CREATE','assignments','192.168.100.51','Asigno el equipo INV-000018 Apple MacBook Air M2 a Veronica Luna',TIMESTAMP(CURDATE() - INTERVAL 267 DAY, '10:20:00')),
('admin',   'UPDATE','categories','192.168.100.34','Actualizo la categoria UPS: Sistema de alimentacion ininterrumpida',TIMESTAMP(CURDATE() - INTERVAL 210 DAY, '11:35:00')),
('tecnico', 'UPDATE','maintenance','192.168.100.51','Cerró el mantenimiento 17 del UPS de planta baja',TIMESTAMP(CURDATE() - INTERVAL 171 DAY, '16:25:00')),
('admin',   'UPDATE','equipment','192.168.100.34','Cambio el estado del equipo INV-000053 Dell PowerEdge R730 a Mantenimiento',TIMESTAMP(CURDATE() - INTERVAL 127 DAY, '08:30:00')),
('admin',   'CREATE','users','192.168.100.34','Registro el usuario a.delgado (id 5)',TIMESTAMP(CURDATE() - INTERVAL 90 DAY, '09:50:00')),
('tecnico', 'UPDATE','assignments','192.168.100.51','Registro la devolucion del equipo INV-000003 Dell OptiPlex 7060',TIMESTAMP(CURDATE() - INTERVAL 64 DAY, '13:15:00')),
('admin',   'UPDATE','users','192.168.100.34','Actualizo el rol del usuario m.quiroga a Consulta',TIMESTAMP(CURDATE() - INTERVAL 29 DAY, '10:00:00')),
('admin',   'UPDATE','equipment','192.168.100.34','Cambio el estado del equipo INV-000046 Dell OptiPlex 7060 a Baja',TIMESTAMP(CURDATE() - INTERVAL 28 DAY, '10:05:00')),
('tecnico', 'UPDATE','maintenance','192.168.100.51','Registro el mantenimiento preventivo anual del equipo INV-000001',TIMESTAMP(CURDATE() - INTERVAL 27 DAY, '09:00:00')),
('tecnico', 'UPDATE','equipment','192.168.100.51','Cambio el estado del equipo INV-000034 HP OfficeJet Pro 9020 a Mantenimiento',TIMESTAMP(CURDATE() - INTERVAL 15 DAY, '08:45:00')),
('admin',   'UPDATE','equipment','192.168.100.34','Cambio la ubicacion del equipo INV-000055 Microsoft Surface Laptop 5',TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '11:15:00'));

-- ---------------------------------------------------------------------
-- HISTORIAL DE INTENTOS DE ACCESO (login_attempts)
--
-- Incluye los 3 usuarios que inician sesion a diario, con la hora de entrada
-- de cada uno, mas algunos intentos fallidos por error de escritura. Sirve
-- para que la tabla se vea con uso real antes de la demostracion.
--
-- La columna `success` es 0 para los intentos rechazados.
-- ---------------------------------------------------------------------
INSERT INTO `login_attempts` (`username`,`ip`,`success`,`user_agent`,`created_at`) VALUES
('admin',    '192.168.100.34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '08:07:12')),
('tecnico',  '192.168.100.51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '08:31:44')),
('admin',    '192.168.100.34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '13:22:08')),
('consulta', '192.168.100.77', 1, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7) Safari/18.2', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '09:14:55')),
('tecnico',  '192.168.100.51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '14:03:19')),
('tecnico',  '192.168.100.51', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '14:05:02')),
('tecnico',  '192.168.100.51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '14:05:41')),
('admin',    '192.168.100.34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 4 DAY, '16:48:30')),
('m.quiroga','192.168.100.60', 1, 'Mozilla/5.0 (X11; Linux x86_64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 4 DAY, '10:41:07')),
('admin',    '192.168.100.34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '08:09:33')),
('consulta', '192.168.100.77', 1, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7) Safari/18.2', TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '09:20:16')),
('tecnico',  '192.168.100.51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '08:28:50')),
('admin',    '192.168.100.34', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '12:15:44')),
('admin',    '192.168.100.34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '12:17:20')),
('m.quiroga','192.168.100.60', 1, 'Mozilla/5.0 (X11; Linux x86_64) Chrome/141.0', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '15:52:38')),
('tecnico',  '192.168.100.51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '13:44:11')),
('consulta', '192.168.100.77', 1, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7) Safari/18.2', TIMESTAMP(CURDATE(), '10:02:33')),
('admin',    '192.168.100.34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0', TIMESTAMP(CURDATE(), '17:10:05')),
('tecnico',  '192.168.100.51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/142.0', TIMESTAMP(CURDATE(), '09:58:21')),
('m.quiroga','192.168.100.60', 1, 'Mozilla/5.0 (X11; Linux x86_64) Chrome/141.0', TIMESTAMP(CURDATE(), '11:36:52'));

-- ---------------------------------------------------------------------
-- FECHA DE ULTIMA MODIFICACION DE LOS EQUIPOS (equipment.updated_at)
--
-- La tabla declara `updated_at ... ON UPDATE CURRENT_TIMESTAMP`, y los
-- bloques anteriores corrigen el estado de varios equipos para que cuadre
-- con las asignaciones. Eso deja `updated_at` de los 55 equipos en el
-- instante de la importacion, lo que hace que el panel "Equipos
-- actualizados recientemente" del dashboard se vea plano.
--
-- Por eso esta seccion va AL FINAL: sobrescribe esas marcas de tiempo con
-- una antiguedad realista. Un identificador concreto se ancla a pocos dias
-- (equipos con intervencion reciente) y el resto se reparte en los ultimos
-- 14 meses. Todas las fechas son relativas a CURDATE().
-- ---------------------------------------------------------------------
UPDATE `equipment` SET `updated_at` = CURDATE() - INTERVAL 6 DAY - INTERVAL 4 HOUR WHERE `id` = 24;
UPDATE `equipment` SET `updated_at` = CURDATE() - INTERVAL 3 DAY - INTERVAL 2 HOUR WHERE `id` = 34;
UPDATE `equipment` SET `updated_at` = CURDATE() - INTERVAL 4 DAY - INTERVAL 7 HOUR WHERE `id` = 44;
UPDATE `equipment` SET `updated_at` = CURDATE() - INTERVAL 1 DAY - INTERVAL 3 HOUR WHERE `id` = 53;
UPDATE `equipment` SET `updated_at` = CURDATE() - INTERVAL 2 DAY - INTERVAL 5 HOUR WHERE `id` = 55;
UPDATE `equipment` SET `updated_at` = CURDATE() - INTERVAL ((`id` * 23) % 420) DAY - INTERVAL ((`id` * 7) % 20) HOUR
  WHERE `id` NOT IN (24,34,44,53,55);

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- DOCUMENTOS DEL LABORATORIO (2) - catalogo de lab/documentos.php
--
-- El archivo confidencial-nomina.txt existe en lab/contratos/ pero NO
-- se cataloga aqui a proposito: leerlo exige recorrer la ruta, que es
-- justo la practica LAB-05.
-- ---------------------------------------------------------------------
INSERT INTO `lab_documentos` (`id`,`archivo`,`descripcion`) VALUES
(1,'contrato-servicios.txt','Contrato marco de prestacion de servicios, vigencia 2026'),
(2,'polizas.md','Polizas de seguros corporativos vigentes');

-- =====================================================================
-- Fin seed.sql
-- =====================================================================
