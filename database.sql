-- NexusFlow Bütçe Yönetim Sistemi Veritabanı

-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS nexusflow_db;
USE nexusflow_db;

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bütçe tablosu
CREATE TABLE IF NOT EXISTS budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Gelirler tablosu
CREATE TABLE IF NOT EXISTS incomes (
    income_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_id INT NOT NULL,
    income_type ENUM('salary', 'bonus', 'investment', 'other') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE
);

-- Giderler tablosu
CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_id INT NOT NULL,
    expense_type ENUM('shopping', 'bills', 'rent', 'food', 'transport', 'other') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE
);

-- Örnek kullanıcı verisi
INSERT INTO users (first_name, last_name, email, password) VALUES
('Test', 'Kullanıcı', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- Şifre: password

-- Örnek bütçe verisi
INSERT INTO budgets (user_id, total_amount) VALUES
(1, 5000.00);

-- Örnek gelir verileri
INSERT INTO incomes (user_id, budget_id, income_type, amount, description) VALUES
(1, 1, 'salary', 4000.00, 'Aylık maaş'),
(1, 1, 'bonus', 1000.00, 'Yıl sonu primi');

-- Örnek gider verileri
INSERT INTO expenses (user_id, budget_id, expense_type, amount, description) VALUES
(1, 1, 'rent', 1500.00, 'Kira ödemesi'),
(1, 1, 'bills', 300.00, 'Elektrik faturası'),
(1, 1, 'food', 800.00, 'Market alışverişi');