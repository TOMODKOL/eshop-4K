-- client.4kluciodkol.cz – struktura databáze (MySQL / MariaDB na Forpsi)
-- Spuštění: v phpMyAdmin → SQL → vložit celý soubor, nebo php bin/install.php

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- Objednávky z e-shopu (vydané faktury z Pohody, posílá pomocník na KANCL)
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NULL,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  invoice_date DATE NOT NULL,
  email VARCHAR(190) NOT NULL,
  customer_name VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  source VARCHAR(20) NOT NULL DEFAULT 'eshop',
  parcel_carrier VARCHAR(10) NOT NULL DEFAULT '',
  parcel_no VARCHAR(40) NOT NULL DEFAULT '',
  delivered_date DATE NULL,
  tracking_checked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY k_order_no (order_no),
  KEY k_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  line_no INT NOT NULL DEFAULT 0,
  code VARCHAR(60) NOT NULL DEFAULT '',
  stock_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  KEY k_order (order_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS returns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no VARCHAR(20) NOT NULL UNIQUE,
  token VARCHAR(64) NOT NULL UNIQUE,
  order_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  customer_name VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'novy',
  ship_method VARCHAR(20) NOT NULL,
  bank_account VARCHAR(60) NOT NULL DEFAULT '',
  note TEXT NULL,
  refund_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  postage DECIMAL(12,2) NULL,
  postage_applies TINYINT(1) NOT NULL DEFAULT 0,
  packeta_id VARCHAR(30) NULL,
  packeta_barcode VARCHAR(40) NULL,
  packeta_password VARCHAR(40) NULL,
  packeta_error TEXT NULL,
  claim_deadline DATE NULL,
  pohoda_state VARCHAR(20) NOT NULL DEFAULT '',
  pohoda_no VARCHAR(120) NULL,
  pohoda_status VARCHAR(60) NOT NULL DEFAULT '',
  pohoda_error TEXT NULL,
  notify_pending TINYINT(1) NOT NULL DEFAULT 0,
  ship_refund DECIMAL(12,2) NOT NULL DEFAULT 0,
  ship_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  swap_diff DECIMAL(12,2) NOT NULL DEFAULT 0,
  xorder_no VARCHAR(20) NULL,
  xorder_state VARCHAR(10) NOT NULL DEFAULT '',
  xorder_error TEXT NULL,
  zf_no VARCHAR(20) NULL,
  zf_paid TINYINT(1) NOT NULL DEFAULT 0,
  pay_method VARCHAR(20) NOT NULL DEFAULT '',
  cod_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  doc_state VARCHAR(20) NOT NULL DEFAULT '',
  doc_type VARCHAR(20) NOT NULL DEFAULT '',
  doc_no VARCHAR(30) NULL,
  doc_error TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY k_status (status),
  CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS return_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NULL,
  item_code VARCHAR(60) NOT NULL DEFAULT '',
  item_name VARCHAR(255) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  action VARCHAR(20) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  reason VARCHAR(190) NOT NULL DEFAULT '',
  size_to VARCHAR(40) NOT NULL DEFAULT '',
  defect_text TEXT NULL,
  pohoda_no VARCHAR(20) NULL,
  pohoda_status VARCHAR(60) NOT NULL DEFAULT '',
  new_code VARCHAR(60) NOT NULL DEFAULT '',
  new_name VARCHAR(255) NOT NULL DEFAULT '',
  new_price DECIMAL(12,2) NULL,
  new_url VARCHAR(500) NOT NULL DEFAULT '',
  new_qty INT NOT NULL DEFAULT 0,
  KEY k_return (return_id),
  CONSTRAINT fk_lines_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_lines_item FOREIGN KEY (item_id) REFERENCES order_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS return_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  line_id INT UNSIGNED NOT NULL,
  file VARCHAR(120) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY k_line (line_id),
  CONSTRAINT fk_photos_line FOREIGN KEY (line_id) REFERENCES return_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- Historie vratky: změny stavu a poznámky (public=1 vidí i zákazník)
CREATE TABLE IF NOT EXISTS return_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NULL,
  note TEXT NULL,
  public TINYINT(1) NOT NULL DEFAULT 0,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY k_return (return_id),
  CONSTRAINT fk_events_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS sync_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  received_at DATETIME NOT NULL,
  orders INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
