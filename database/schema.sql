CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    phone VARCHAR(50) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(120) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    status ENUM('available', 'borrowed', 'maintenance') NOT NULL DEFAULT 'available',
    detail TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS borrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    borrowed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP NULL,
    status ENUM('borrowed', 'returned') NOT NULL DEFAULT 'borrowed',
    CONSTRAINT fk_borrow_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    CONSTRAINT fk_borrow_user FOREIGN KEY (user_id) REFERENCES users(id)
);
