-- Database schema for Quimiosalud SAS system
-- This creates all the necessary tables for the application

-- Drop tables if they exist (for clean installation)
DROP TABLE IF EXISTS completed_appointments;
DROP TABLE IF EXISTS projected_appointments;
DROP TABLE IF EXISTS population;
DROP TABLE IF EXISTS contracted_services;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS specialties;
DROP TABLE IF EXISTS eps;
DROP TABLE IF EXISTS management_years;

-- Create management_years table
CREATE TABLE management_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_label VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create EPS (Entidades Promotoras de Salud) table
CREATE TABLE eps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create specialties table
CREATE TABLE specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    role VARCHAR(20) DEFAULT 'user',
    active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create settings table
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create contracted_services table
CREATE TABLE contracted_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    specialty_id INT NOT NULL,
    yearly_qty INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps (id),
    FOREIGN KEY (specialty_id) REFERENCES specialties (id),
    UNIQUE KEY unique_eps_specialty (eps_id, specialty_id)
);

-- Create population table
CREATE TABLE population (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    year_id INT NOT NULL,
    month INT NOT NULL, -- 1-12 (Feb to Jan)
    total_population INT NOT NULL DEFAULT 0,
    active_population INT NOT NULL DEFAULT 0,
    adults INT NOT NULL DEFAULT 0,
    pediatric_diagnosed INT NOT NULL DEFAULT 0,
    minors_follow_up INT NOT NULL DEFAULT 0,
    pregnant_women INT NOT NULL DEFAULT 0,
    fertile_women INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps (id),
    FOREIGN KEY (year_id) REFERENCES management_years (id),
    UNIQUE KEY unique_pop_eps_year_month (eps_id, year_id, month)
);

-- Create projected_appointments table
CREATE TABLE projected_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    year_id INT NOT NULL,
    month INT NOT NULL, -- 1-12 (Feb to Jan)
    specialty_id INT NOT NULL,
    projected_qty INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps (id),
    FOREIGN KEY (year_id) REFERENCES management_years (id),
    FOREIGN KEY (specialty_id) REFERENCES specialties (id),
    UNIQUE KEY unique_pa_eps_year_month_specialty (eps_id, year_id, month, specialty_id)
);

-- Create completed_appointments table
CREATE TABLE completed_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eps_id INT NOT NULL,
    year_id INT NOT NULL,
    month INT NOT NULL, -- 1-12 (Feb to Jan)
    specialty_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eps_id) REFERENCES eps (id),
    FOREIGN KEY (year_id) REFERENCES management_years (id),
    FOREIGN KEY (specialty_id) REFERENCES specialties (id)
);

-- Default data insertion

-- Insert initial admin user (username: admin, password: admin123)
INSERT INTO users (name, username, password, email, role) 
VALUES ('Administrador', 'admin', '$2y$10$hDTzLKHDpQTa/zDu1vCsj.TaF1zP8Nf/3XF2PwT043YhsTZI1WINi', 'admin@example.com', 'admin');

-- Insert default specialties
INSERT INTO specialties (name, code) VALUES
('Médico Infectólogo Adultos', 'MIA'),
('Médico Infectólogo Pediátrico', 'MIP'),
('Médico Experto', 'MEX'),
('Psiquiatría', 'PSQ'),
('Ginecología Fértil', 'GIN'),
('Ginecología Gestantes', 'GIG'),
('Enfermería', 'ENF'),
('Psicología', 'PSI'),
('Nutrición', 'NUT'),
('Trabajo Social', 'TSO'),
('Químico', 'QUI'),
('Odontología', 'ODO'),
('Laboratorios', 'LAB');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('work_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'),
('distribution_percentage', '19,19,19,19,19,5'),
('compliance_threshold_red', '70'),
('compliance_threshold_yellow', '90');

-- Insert initial management year (current year)
INSERT INTO management_years (year_label, start_date, end_date, active)
VALUES (
    '2025-2026', 
    '2025-02-01',
    '2026-01-31',
    TRUE
);

-- Insert initial EPS
INSERT INTO eps (name) VALUES ('EPS Modelo');

-- Set up initial contracted services for the default EPS
INSERT INTO contracted_services (eps_id, specialty_id, yearly_qty)
SELECT 1, id, 
    CASE 
        WHEN code = 'MIA' THEN 2
        WHEN code = 'MIP' THEN 2
        WHEN code = 'MEX' THEN 10
        WHEN code = 'PSQ' THEN 4
        WHEN code = 'GIN' THEN 4
        WHEN code = 'GIG' THEN 8
        WHEN code = 'ENF' THEN 12
        WHEN code = 'PSI' THEN 4
        WHEN code = 'NUT' THEN 4
        WHEN code = 'TSO' THEN 4
        WHEN code = 'QUI' THEN 12
        WHEN code = 'ODO' THEN 2
        WHEN code = 'LAB' THEN 4
        ELSE 0
    END
FROM specialties;