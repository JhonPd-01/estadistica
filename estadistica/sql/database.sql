-- Database: quimiosalud_db
-- --------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Estructura de tabla para usuarios
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar usuario administrador predeterminado (contraseña: admin123)
INSERT INTO `users` (`username`, `password`, `fullname`, `email`, `role`) VALUES
('admin', '$2y$10$5E8BEzX05JEA.ygB7plQOO9iRNxhHWXHBYuzRX5wy1SkNo24nKaga', 'Administrador', 'admin@quimiosalud.com', 'admin');

-- --------------------------------------------------------
-- Estructura de tabla para EPS
-- --------------------------------------------------------

CREATE TABLE `eps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar EPS iniciales
INSERT INTO `eps` (`name`, `code`, `is_active`) VALUES
('Nueva EPS', 'NEPS', 1),
('Compensar', 'COMP', 1),
('Familiar de Colombia', 'FAMC', 1),
('FOMAG', 'FOMG', 1);

-- --------------------------------------------------------
-- Estructura de tabla para especialistas
-- --------------------------------------------------------

CREATE TABLE `specialists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `specialty_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `specialty_id` (`specialty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Estructura de tabla para especialidades
-- --------------------------------------------------------

CREATE TABLE `specialties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar especialidades
INSERT INTO `specialties` (`name`, `code`) VALUES
('Médico infectólogo adultos', 'INFAD'),
('Médico infectólogo pediátrico', 'INFPE'),
('Médico experto', 'MEDEX'),
('Psiquiatría', 'PSIQT'),
('Ginecología', 'GINEC'),
('Pediatría', 'PEDIA'),
('Psicología', 'PSICO'),
('Nutrición', 'NUTRI'),
('Trabajo Social', 'TRASOC'),
('Enfermería', 'ENFER'),
('Químico', 'QUIMI'),
('Odontología', 'ODONT'),
('Laboratorios', 'LABOR');

-- --------------------------------------------------------
-- Estructura de tabla para registros de población
-- --------------------------------------------------------

CREATE TABLE `population` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eps_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `total_population` int(11) NOT NULL DEFAULT '0',
  `active_eps_population` int(11) NOT NULL DEFAULT '0',
  `fertile_women` int(11) NOT NULL DEFAULT '0',
  `pregnant_women` int(11) NOT NULL DEFAULT '0',
  `adults` int(11) NOT NULL DEFAULT '0',
  `pediatric_diagnosed` int(11) NOT NULL DEFAULT '0',
  `minors_follow_up` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eps_year_month` (`eps_id`,`year`,`month`),
  KEY `eps_id` (`eps_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Estructura de tabla para contratos anuales
-- --------------------------------------------------------

CREATE TABLE `yearly_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eps_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `specialty_id` int(11) NOT NULL,
  `appointments_per_patient` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eps_year_specialty` (`eps_id`,`year`,`specialty_id`),
  KEY `eps_id` (`eps_id`),
  KEY `specialty_id` (`specialty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar datos iniciales de contratos para todas las EPS
INSERT INTO `yearly_contracts` (`eps_id`, `year`, `specialty_id`, `appointments_per_patient`) 
SELECT e.id, YEAR(CURRENT_DATE), s.id,
CASE 
    WHEN s.code = 'INFAD' OR s.code = 'INFPE' THEN IF(e.code = 'NEPS', 2, 1)
    WHEN s.code = 'MEDEX' THEN IF(e.code = 'NEPS', 10, 11)
    WHEN s.code IN ('PSIQT', 'GINEC', 'PSICO', 'NUTRI', 'TRASOC', 'LABOR') THEN 4
    WHEN s.code = 'ENFER' OR s.code = 'QUIMI' THEN 12
    WHEN s.code = 'ODONT' THEN 2
    ELSE 4
END
FROM `eps` e, `specialties` s;

-- --------------------------------------------------------
-- Estructura de tabla para objetivos mensuales
-- --------------------------------------------------------

CREATE TABLE `monthly_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eps_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `specialty_id` int(11) NOT NULL,
  `target_appointments` int(11) NOT NULL DEFAULT '0',
  `completed_appointments` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `eps_year_month_specialty` (`eps_id`,`year`,`month`,`specialty_id`),
  KEY `eps_id` (`eps_id`),
  KEY `specialty_id` (`specialty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Estructura de tabla para registro de atenciones
-- --------------------------------------------------------

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eps_id` int(11) NOT NULL,
  `specialty_id` int(11) NOT NULL,
  `specialist_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `eps_id` (`eps_id`),
  KEY `specialty_id` (`specialty_id`),
  KEY `specialist_id` (`specialist_id`),
  KEY `appointment_date` (`appointment_date`),
  KEY `year_month` (`year`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Añadir claves foráneas
-- --------------------------------------------------------

ALTER TABLE `population`
  ADD CONSTRAINT `population_eps_fk` FOREIGN KEY (`eps_id`) REFERENCES `eps` (`id`) ON DELETE CASCADE;

ALTER TABLE `yearly_contracts`
  ADD CONSTRAINT `yearly_contracts_eps_fk` FOREIGN KEY (`eps_id`) REFERENCES `eps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `yearly_contracts_specialty_fk` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`);

ALTER TABLE `monthly_targets`
  ADD CONSTRAINT `monthly_targets_eps_fk` FOREIGN KEY (`eps_id`) REFERENCES `eps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monthly_targets_specialty_fk` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`);

ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_eps_fk` FOREIGN KEY (`eps_id`) REFERENCES `eps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_specialty_fk` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`),
  ADD CONSTRAINT `appointments_specialist_fk` FOREIGN KEY (`specialist_id`) REFERENCES `specialists` (`id`) ON DELETE SET NULL;

ALTER TABLE `specialists`
  ADD CONSTRAINT `specialists_specialty_fk` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`);

COMMIT;
