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

-- Tabla de EPS
CREATE TABLE IF NOT EXISTS eps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    status BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de especialidades
CREATE TABLE IF NOT EXISTS specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de periodos anuales
CREATE TABLE IF NOT EXISTS annual_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description VARCHAR(255),
    active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(year)
);

-- Tabla de población por EPS
CREATE TABLE IF NOT EXISTS population (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    period_id INT NOT NULL,
    month INT NOT NULL,
    total_population INT NOT NULL DEFAULT 0,
    active_population INT NOT NULL DEFAULT 0,
    fertile_women INT NOT NULL DEFAULT 0,
    pregnant_women INT NOT NULL DEFAULT 0,
    adults INT NOT NULL DEFAULT 0,
    pediatric_diagnosed INT NOT NULL DEFAULT 0,
    monitored_minors INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES annual_periods(id) ON DELETE CASCADE,
    UNIQUE (eps_id, period_id, month)
);

-- Tabla de atenciones contratadas por EPS
CREATE TABLE IF NOT EXISTS contracted_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    period_id INT NOT NULL,
    specialty_id INT NOT NULL,
    appointments_per_patient INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES annual_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE CASCADE,
    UNIQUE (eps_id, period_id, specialty_id)
);

-- Tabla de proyección de atenciones mensuales
CREATE TABLE IF NOT EXISTS monthly_projections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    period_id INT NOT NULL,
    specialty_id INT NOT NULL,
    month INT NOT NULL,
    projected_appointments INT NOT NULL DEFAULT 0,
    actual_appointments INT NOT NULL DEFAULT 0,
    working_days INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES annual_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE CASCADE,
    UNIQUE (eps_id, period_id, specialty_id, month)
);

-- Tabla de registro diario de atenciones
CREATE TABLE IF NOT EXISTS daily_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    period_id INT NOT NULL,
    specialty_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointments_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES annual_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE CASCADE,
    UNIQUE (eps_id, period_id, specialty_id, appointment_date)
);

-- Insertar usuario administrador por defecto
INSERT INTO users (username, password, name, role)
VALUES ('admin', '$2y$10$1qAz2wSx3eDc4rFv5tDaFud/MUoF/GRHitwYfgl.WTE831Y//4.lG', 'Administrador', 'admin');

-- Insertar EPS iniciales
INSERT INTO eps (name) VALUES 
('Nueva EPS'),
('Compensar'),
('Familiar de Colombia'),
('FOMAG');

-- Insertar especialidades
INSERT INTO specialties (name, description) VALUES 
('Médico infectólogo adultos', 'Atención de infectología para pacientes adultos'),
('Médico infectólogo pediátrico', 'Atención de infectología para pacientes pediátricos'),
('Médico experto', 'Atención médica especializada'),
('Psiquiatría', 'Atención de salud mental y psiquiatría'),
('Ginecología fértil', 'Atención ginecológica para mujeres en edad fértil'),
('Ginecología gestantes', 'Atención ginecológica para mujeres gestantes'),
('Enfermería', 'Servicios de enfermería'),
('Psicología', 'Atención psicológica'),
('Nutrición', 'Servicios de nutrición'),
('Trabajo Social', 'Servicios de trabajo social'),
('Químico', 'Servicios de química farmacéutica'),
('Odontología', 'Servicios odontológicos'),
('Laboratorios', 'Servicios de laboratorio clínico');

-- Crear un periodo anual por defecto
INSERT INTO annual_periods (year, start_date, end_date, description, active)
VALUES (2023, '2023-02-01', '2024-01-31', 'Periodo 2023-2024', TRUE);
