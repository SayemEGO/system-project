-- ============================================================
-- STEP 1: DATABASE SETUP
-- File: database.sql
-- How to use: Open phpMyAdmin → Click "SQL" tab → Paste this → Click "Go"
-- ============================================================

CREATE DATABASE IF NOT EXISTS bakery_system;
USE bakery_system;

-- ── USERS TABLE ──────────────────────────────────────────────
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin','factory_manager','outlet_manager','delivery') NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,   -- plain text for demo; use password_hash() in production
    email VARCHAR(100),
    phone VARCHAR(20),
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── OUTLETS TABLE ─────────────────────────────────────────────
CREATE TABLE outlets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(20),
    manager_id INT,                   -- references users.id
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── CATEGORIES TABLE ──────────────────────────────────────────
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

-- ── PRODUCTS TABLE ────────────────────────────────────────────
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    min_stock INT DEFAULT 50,
    unit VARCHAR(20) DEFAULT 'piece',
    emoji VARCHAR(10) DEFAULT '🛍',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── ORDERS TABLE ──────────────────────────────────────────────
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT NOT NULL,
    placed_by INT,                    -- references users.id
    status ENUM('pending','processing','ready','delivered','cancelled') DEFAULT 'pending',
    notes TEXT,
    total DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── ORDER ITEMS TABLE ─────────────────────────────────────────
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL
);

-- ── DELIVERIES TABLE ─────────────────────────────────────────
CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    assigned_to INT,                  -- references users.id (delivery staff)
    status ENUM('assigned','in_transit','delivered','failed') DEFAULT 'assigned',
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME
);

-- ── INVENTORY LOG TABLE ───────────────────────────────────────
CREATE TABLE inventory_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('production','sale','adjustment','return') NOT NULL,
    change_qty INT NOT NULL,
    before_qty INT,
    after_qty INT,
    notes VARCHAR(255),
    done_by INT,                      -- references users.id
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- DEMO DATA
-- ============================================================

-- Demo Users (passwords are plain text for demo only)
INSERT INTO users (name, role, username, password, email, phone) VALUES
('Administrator',    'admin',           'admin',     'admin123',    'admin@bakery.com',   '01700000001'),
('Factory Manager',  'factory_manager', 'factory',   'factory123',  'factory@bakery.com', '01700000002'),
('Dhaka Outlet Mgr', 'outlet_manager',  'dhaka_mgr', 'outlet123',   'dhaka@bakery.com',   '01700000003'),
('Ctg Outlet Mgr',   'outlet_manager',  'ctg_mgr',   'outlet456',   'ctg@bakery.com',     '01700000004'),
('Delivery Staff',   'delivery',        'delivery',  'delivery123', 'del@bakery.com',     '01700000005');

-- Demo Outlets
INSERT INTO outlets (name, address, phone, manager_id) VALUES
('Dhaka Main Outlet',  'Gulshan-1, Dhaka',        '01711111111', 3),
('Chittagong Outlet',  'GEC Circle, Chittagong',  '01722222222', 4),
('Sylhet Outlet',      'Zindabazar, Sylhet',       '01733333333', NULL),
('Rajshahi Outlet',    'Shaheb Bazar, Rajshahi',   '01744444444', NULL);

-- Categories
INSERT INTO categories (name) VALUES
('Bread'), ('Cake'), ('Biscuit'), ('Snacks'), ('Drinks');

-- Products
INSERT INTO products (name, category_id, price, stock, min_stock, unit, emoji) VALUES
('White Bread Loaf',     1, 35.00, 500, 100, 'piece',  '🍞'),
('Whole Wheat Bread',    1, 45.00, 300,  80, 'piece',  '🥖'),
('Chocolate Cake Slice', 2, 80.00, 200,  50, 'piece',  '🎂'),
('Vanilla Cake Slice',   2, 75.00,  40,  50, 'piece',  '🍰'),
('Butter Biscuit Pack',  3, 30.00, 400,  80, 'pack',   '🍪'),
('Cream Biscuit Pack',   3, 35.00,  35,  80, 'pack',   '🥮'),
('Chips Packet',         4, 25.00, 600, 100, 'pack',   '🥨'),
('Peanuts Pack',         4, 20.00, 500, 100, 'pack',   '🥜'),
('Mineral Water 500ml',  5, 15.00, 800, 150, 'bottle', '💧'),
('Juice Pack 250ml',     5, 25.00,  90, 120, 'pack',   '🧃');

-- Demo Orders
INSERT INTO orders (outlet_id, placed_by, status, total, created_at) VALUES
(1, 3, 'delivered',  3750.00, '2025-01-15 09:30:00'),
(2, 4, 'delivered',  2200.00, '2025-01-15 10:00:00'),
(1, 3, 'processing', 1800.00, '2025-01-15 11:30:00'),
(3, NULL, 'pending', 2500.00, '2025-01-15 12:00:00'),
(4, NULL, 'pending', 1350.00, '2025-01-15 13:00:00'),
(2, 4, 'delivered',  3100.00, '2025-01-14 09:00:00');

-- Order Items
INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES
(1,1,50,35),(1,3,20,80),(1,9,30,15),
(2,5,40,30),(2,10,40,25),
(3,2,20,45),(3,4,10,75),
(4,7,60,25),(4,8,40,20),
(5,6,30,35),(5,9,20,15),
(6,1,60,35),(6,3,15,80);

-- Demo Deliveries
INSERT INTO deliveries (order_id, assigned_to, status) VALUES
(1, 5, 'delivered'),
(2, 5, 'delivered'),
(3, 5, 'in_transit'),
(6, 5, 'delivered');

-- Inventory Log
INSERT INTO inventory_log (product_id, type, change_qty, before_qty, after_qty, notes, done_by) VALUES
(1, 'production', 200, 300, 500, 'Morning batch', 2),
(3, 'sale',       -30, 230, 200, 'Order #1',       2);