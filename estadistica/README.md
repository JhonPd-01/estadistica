# Quimiosalud SAS - Sistema de Pronóstico de Atenciones y Laboratorios

Este sistema permite la gestión, pronóstico y monitoreo de atenciones médicas y laboratorios para Quimiosalud SAS.

## Requisitos

- XAMPP (Apache, MySQL, PHP)
- Navegador web moderno

## Instalación

1. Instala XAMPP desde https://www.apachefriends.org/
2. Coloca todos los archivos del proyecto en la carpeta `htdocs` de tu instalación de XAMPP
3. Inicia los servicios de Apache y MySQL desde el panel de control de XAMPP
4. Importa la base de datos utilizando phpMyAdmin

## Creación de la Base de Datos

Accede a phpMyAdmin (generalmente en http://localhost/phpmyadmin) y ejecuta el siguiente SQL:

```sql
-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS quimiosalud;
USE quimiosalud;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar usuario admin por defecto (contraseña: admin123)
INSERT INTO users (username, password, name, role) 
VALUES ('admin', '$2y$10$HvSDJr.Ali7lX88jTwEE..kQ1jDQJq7XCuAdNUaMoqsQG933V/8hW', 'Administrador', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Tabla de EPS
CREATE TABLE IF NOT EXISTS eps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar EPS iniciales
INSERT INTO eps (name) VALUES 
('Nueva EPS'),
('Compensar'),
('Familiar de Colombia'),
('FOMAG')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Tabla de especialidades
CREATE TABLE IF NOT EXISTS specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar especialidades iniciales
INSERT INTO specialties (name, code) VALUES 
('Médico infectólogo adultos', 'MIA'),
('Médico infectólogo pediátrico', 'MIP'),
('Médico experto', 'MEX'),
('Pediatría', 'PED'),
('Psiquiatría', 'PSQ'),
('Ginecología', 'GIN'),
('Ginecología gestantes', 'GIG'),
('Enfermería', 'ENF'),
('Psicología', 'PSI'),
('Nutrición', 'NUT'),
('Trabajo Social', 'TSO'),
('Químico', 'QUI'),
('Odontología', 'ODO'),
('Laboratorios', 'LAB')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Tabla de atenciones contratadas por EPS
CREATE TABLE IF NOT EXISTS contracted_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    specialty_id INT NOT NULL,
    yearly_qty INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id),
    FOREIGN KEY (specialty_id) REFERENCES specialties(id),
    UNIQUE KEY eps_specialty (eps_id, specialty_id)
);

-- Insertar datos iniciales de atenciones contratadas
INSERT INTO contracted_services (eps_id, specialty_id, yearly_qty) VALUES
-- Nueva EPS (id = 1)
(1, 1, 2),  -- Médico infectólogo adultos
(1, 2, 2),  -- Médico infectólogo pediátrico 
(1, 3, 10), -- Médico experto
(1, 5, 4),  -- Psiquiatría
(1, 6, 4),  -- Ginecología fértil
(1, 7, 8),  -- Ginecología gestantes
(1, 8, 12), -- Enfermería
(1, 9, 4),  -- Psicología
(1, 10, 4), -- Nutrición
(1, 11, 4), -- Trabajo Social
(1, 12, 12), -- Químico
(1, 13, 2),  -- Odontología
(1, 14, 4),  -- Laboratorios

-- Compensar (id = 2)
(2, 1, 1),  -- Médico infectólogo adultos
(2, 2, 1),  -- Médico infectólogo pediátrico 
(2, 3, 11), -- Médico experto
(2, 5, 4),  -- Psiquiatría
(2, 6, 4),  -- Ginecología fértil
(2, 7, 8),  -- Ginecología gestantes
(2, 8, 12), -- Enfermería
(2, 9, 4),  -- Psicología
(2, 10, 4), -- Nutrición
(2, 11, 4), -- Trabajo Social
(2, 12, 12), -- Químico
(2, 13, 2),  -- Odontología
(2, 14, 4),  -- Laboratorios

-- Familiar de Colombia (id = 3)
(3, 1, 1),  -- Médico infectólogo adultos
(3, 2, 1),  -- Médico infectólogo pediátrico 
(3, 3, 11), -- Médico experto
(3, 5, 4),  -- Psiquiatría
(3, 6, 4),  -- Ginecología fértil
(3, 7, 8),  -- Ginecología gestantes
(3, 8, 12), -- Enfermería
(3, 9, 4),  -- Psicología
(3, 10, 4), -- Nutrición
(3, 11, 4), -- Trabajo Social
(3, 12, 12), -- Químico
(3, 13, 2),  -- Odontología
(3, 14, 4),  -- Laboratorios

-- FOMAG (id = 4)
(4, 1, 1),  -- Médico infectólogo adultos
(4, 2, 1),  -- Médico infectólogo pediátrico 
(4, 3, 11), -- Médico experto
(4, 5, 4),  -- Psiquiatría
(4, 6, 4),  -- Ginecología fértil
(4, 7, 8),  -- Ginecología gestantes
(4, 8, 12), -- Enfermería
(4, 9, 4),  -- Psicología
(4, 10, 4), -- Nutrición
(4, 11, 4), -- Trabajo Social
(4, 12, 12), -- Químico
(4, 13, 2),  -- Odontología
(4, 14, 4)   -- Laboratorios
ON DUPLICATE KEY UPDATE yearly_qty=VALUES(yearly_qty);

-- Tabla de años de gestión
CREATE TABLE IF NOT EXISTS management_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_label VARCHAR(9) NOT NULL, -- Formato: 2023-2024
    start_date DATE NOT NULL, -- Fecha de inicio (febrero)
    end_date DATE NOT NULL, -- Fecha de fin (enero del siguiente año)
    active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (year_label)
);

-- Tabla de población
CREATE TABLE IF NOT EXISTS population (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    year_id INT NOT NULL,
    month INT NOT NULL, -- 1 a 12 (donde 1 es febrero y 12 es enero del siguiente año)
    total_population INT NOT NULL DEFAULT 0,
    active_population INT NOT NULL DEFAULT 0,
    fertile_women INT NOT NULL DEFAULT 0,
    pregnant_women INT NOT NULL DEFAULT 0,
    adults INT NOT NULL DEFAULT 0,
    pediatric_diagnosed INT NOT NULL DEFAULT 0,
    minors_follow_up INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id),
    FOREIGN KEY (year_id) REFERENCES management_years(id),
    UNIQUE KEY eps_year_month (eps_id, year_id, month)
);

-- Tabla de proyección de atenciones
CREATE TABLE IF NOT EXISTS projected_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    year_id INT NOT NULL,
    month INT NOT NULL, -- 1 a 12 (donde 1 es febrero y 12 es enero del siguiente año)
    specialty_id INT NOT NULL,
    projected_qty INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id),
    FOREIGN KEY (year_id) REFERENCES management_years(id),
    FOREIGN KEY (specialty_id) REFERENCES specialties(id),
    UNIQUE KEY eps_year_month_specialty (eps_id, year_id, month, specialty_id)
);

-- Tabla de atenciones realizadas
CREATE TABLE IF NOT EXISTS completed_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    year_id INT NOT NULL,
    month INT NOT NULL, -- 1 a 12 (donde 1 es febrero y 12 es enero del siguiente año)
    specialty_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id),
    FOREIGN KEY (year_id) REFERENCES management_years(id),
    FOREIGN KEY (specialty_id) REFERENCES specialties(id)
);

-- Tabla de configuración
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (setting_key)
);

-- Insertar configuraciones iniciales
INSERT INTO settings (setting_key, setting_value) VALUES 
('work_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'),
('distribution_percentage', '19,19,19,19,19,5'),
('compliance_threshold_red', '70'),
('compliance_threshold_yellow', '90')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
