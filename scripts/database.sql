-- =====================================================
-- BASE DE DONNÉES ALIPAYEMENT
-- Script de création complète de la structure
-- =====================================================

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS centre_formation;
USE centre_formation;

-- =====================================================
-- TABLE: users (Utilisateurs)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telephone VARCHAR(20) NOT NULL UNIQUE,
    adresse TEXT NOT NULL,
    activite VARCHAR(100) NOT NULL,
    login VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    cni_document VARCHAR(255),
    balance_xaf DECIMAL(15, 2) DEFAULT 0.00,
    balance_rmb DECIMAL(15, 2) DEFAULT 0.00,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at DATETIME,
    approved_by INT,
    rejected_at DATETIME,
    rejected_reason TEXT,
    rejected_by INT,
    INDEX idx_email (email),
    INDEX idx_login (login),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: admins (Administrateurs)
-- =====================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    login VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_login (login),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: transactions (Transactions)
-- =====================================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    type ENUM('send', 'receive', 'deposit', 'withdrawal') NOT NULL,
    amount_xaf DECIMAL(15, 2) NOT NULL,
    amount_rmb DECIMAL(15, 2),
    recipient_name VARCHAR(150),
    recipient_phone VARCHAR(20),
    recipient_account VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    description TEXT,
    reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: exchange_rates (Taux de Change)
-- =====================================================
CREATE TABLE IF NOT EXISTS exchange_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    currency_from VARCHAR(10) DEFAULT 'RMB',
    currency_to VARCHAR(10) DEFAULT 'XAF',
    rate DECIMAL(10, 4) NOT NULL,
    set_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (set_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_updated_at (updated_at),
    INDEX idx_currencies (currency_from, currency_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: user_documents (Documents des Utilisateurs)
-- =====================================================
CREATE TABLE IF NOT EXISTS user_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT,
    verified_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: audit_logs (Journaux d'Audit)
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(50),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: notifications (Notifications)
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    admin_id INT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: settings (Paramètres du Système)
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: password_resets (Réinitialisation de Mot de Passe)
-- =====================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: sessions (Sessions Utilisateur)
-- =====================================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(50),
    user_agent VARCHAR(255),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DONNÉES INITIALES
-- =====================================================

-- Admin par défaut (mot de passe: admin123 - À CHANGER EN PRODUCTION!)
INSERT INTO admins (nom, prenom, email, login, password, role, is_active) 
VALUES ('Admin', 'Super', 'admin@alipayement.com', 'admin', '$2y$12$3xVcJYZAI9bUpJXYKxZEJedMfGzBe7w.JjGkLoUCW0KVA4p3H9Fmu', 'super_admin', TRUE);

-- Taux de change initial
INSERT INTO exchange_rates (currency_from, currency_to, rate, set_by) 
VALUES ('RMB', 'XAF', 6.00, 1);

-- Paramètres du système
INSERT INTO settings (setting_key, setting_value, description) VALUES
('app_name', 'Alipayement', 'Nom de l\'application'),
('app_version', '1.0.0', 'Version de l\'application'),
('maintenance_mode', '0', 'Mode maintenance (0=disabled, 1=enabled)'),
('max_upload_size', '5242880', 'Taille maximale d\'upload en bytes (5MB par défaut)'),
('session_timeout', '3600', 'Délai d\'expiration de session en secondes (1 heure)'),
('max_login_attempts', '5', 'Nombre maximum de tentatives de connexion'),
('lockout_duration', '1800', 'Durée du verrouillage en secondes (30 minutes)');

-- =====================================================
-- VUES UTILES
-- =====================================================

-- Vue pour les comptes en attente d'approbation
CREATE OR REPLACE VIEW pending_accounts_view AS
SELECT 
    u.id,
    u.nom,
    u.prenom,
    u.email,
    u.telephone,
    u.adresse,
    u.activite,
    u.login,
    u.status,
    u.created_at as dateSubmission,
    u.cni_document
FROM users u
WHERE u.status = 'pending'
ORDER BY u.created_at DESC;

-- Vue pour les utilisateurs approuvés
CREATE OR REPLACE VIEW approved_users_view AS
SELECT 
    u.id,
    u.nom,
    u.prenom,
    u.email,
    u.telephone,
    u.activite,
    u.balance_xaf,
    u.balance_rmb,
    u.created_at,
    u.approved_at
FROM users u
WHERE u.status = 'approved'
ORDER BY u.created_at DESC;

-- Vue pour les transactions récentes
CREATE OR REPLACE VIEW recent_transactions_view AS
SELECT 
    t.transaction_id,
    t.type,
    t.amount_xaf,
    t.amount_rmb,
    t.recipient_name,
    t.status,
    t.created_at,
    u.nom,
    u.prenom,
    u.email
FROM transactions t
JOIN users u ON t.user_id = u.id
ORDER BY t.created_at DESC
LIMIT 50;

-- =====================================================
-- FIN DU SCRIPT
-- =====================================================