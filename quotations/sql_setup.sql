-- Tạo bảng quotations và quotation_items
-- Chạy 1 lần trong phpMyAdmin

CREATE TABLE IF NOT EXISTS `quotations` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `quotation_no`  VARCHAR(30) NOT NULL UNIQUE COMMENT 'Số báo giá, VD: BG-2026-001',
  `customer_id`   INT NOT NULL,
  `issue_date`    DATE NOT NULL,
  `valid_until`   DATE DEFAULT NULL,
  `currency`      ENUM('VND','USD','EUR') NOT NULL DEFAULT 'USD',
  `exchange_rate` DECIMAL(15,4) NOT NULL DEFAULT 1,
  `notes`         TEXT DEFAULT NULL,
  `status`        ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  `created_by`    INT DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `fk_quot_customer` (`customer_id`),
  CONSTRAINT `fk_quot_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quotation_items` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `quotation_id`    INT NOT NULL,
  `arrival_code_id` INT DEFAULT NULL COMMENT 'FK tới arrival_cost_codes',
  `cost_code`       VARCHAR(30) NOT NULL COMMENT 'Snapshot mã lúc tạo báo giá',
  `description`     TEXT NOT NULL,
  `currency`        ENUM('VND','USD','EUR') NOT NULL DEFAULT 'USD',
  `unit_price`      DECIMAL(18,4) NOT NULL DEFAULT 0,
  `quantity`        DECIMAL(10,2) NOT NULL DEFAULT 1,
  `amount`          DECIMAL(18,4) GENERATED ALWAYS AS (`unit_price` * `quantity`) STORED,
  `notes`           VARCHAR(255) DEFAULT NULL,
  `sort_order`      INT NOT NULL DEFAULT 0,
  KEY `fk_qi_quot` (`quotation_id`),
  KEY `fk_qi_arrival` (`arrival_code_id`),
  CONSTRAINT `fk_qi_quot`    FOREIGN KEY (`quotation_id`)    REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qi_arrival` FOREIGN KEY (`arrival_code_id`) REFERENCES `arrival_cost_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
