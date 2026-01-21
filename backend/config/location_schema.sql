-- Serviceable Areas Table
CREATE TABLE IF NOT EXISTS serviceable_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    area_name VARCHAR(255) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    radius_km DECIMAL(5, 2) NOT NULL DEFAULT 5.00,
    delivery_time_minutes INT DEFAULT 30,
    delivery_charge DECIMAL(10, 2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (lat, lng),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample serviceable areas for Bangalore
INSERT INTO serviceable_areas (area_name, lat, lng, radius_km, delivery_time_minutes, delivery_charge) VALUES
('Koramangala', 12.9352, 77.6245, 5, 25, 0),
('Indiranagar', 12.9716, 77.6412, 5, 30, 0),
('Whitefield', 12.9698, 77.7500, 7, 45, 20),
('HSR Layout', 12.9121, 77.6446, 5, 25, 0),
('BTM Layout', 12.9165, 77.6101, 5, 30, 0),
('Electronic City', 12.8456, 77.6603, 6, 40, 15),
('Jayanagar', 12.9250, 77.5937, 5, 30, 0),
('Marathahalli', 12.9591, 77.7011, 6, 35, 10);
