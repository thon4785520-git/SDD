CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('available', 'borrowed') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    borrower VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    borrowed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP NULL,
    status ENUM('active', 'returned') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_transactions_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id)
);
