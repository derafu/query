-- This is a sample billing database schema with test data.
-- The data is designed to test various SQL operators and query scenarios.
-- All dates are in ISO format (YYYY-MM-DD) as text since SQLite doesn't have a date type.

-- -----------------------------------------------------
-- Customers sample data
-- - Mix of persons and companies
-- - Different status and dates
-- - Some with soft delete
-- -----------------------------------------------------
INSERT INTO customers (id, tax_id, name, type, status, created_at, deleted_at) VALUES
(1, '123456789', 'John Smith', 'person', 'active', '2024-01-01', NULL),
(2, '987654321', 'Acme Corp', 'company', 'active', '2024-01-15', NULL),
(3, '456789123', 'Jane Doe', 'person', 'inactive', '2024-02-01', '2024-03-01'),
(4, '789123456', 'Tech Solutions LLC', 'company', 'active', '2024-02-15', NULL),
(5, '321654987', 'Bob Wilson', 'person', 'inactive', '2024-03-01', '2024-03-15');

-- -----------------------------------------------------
-- Products sample data
-- - Different prices and categories
-- - Flags for various features (using bitwise):
--   1: taxable
--   2: importable
--   4: perishable
--   8: service
-- -----------------------------------------------------
INSERT INTO products (id, code, name, price, category, flags, created_at, deleted_at) VALUES
(1, 'LAPTOP01', 'Business Laptop', 1200.00, 'electronics', 3, '2024-01-01', NULL),
(2, 'PHONE01', 'Smartphone', 800.00, 'electronics', 3, '2024-01-01', NULL),
(3, 'CONS01', 'Consulting Hour', 150.00, 'services', 9, '2024-01-01', NULL),
(4, 'FOOD01', 'Organic Snack', 5.00, 'food', 5, '2024-01-01', NULL),
(5, 'TOOL01', 'Power Tool', 250.00, 'hardware', 1, '2024-01-01', NULL),
(6, 'SERV01', 'Tech Support', 100.00, 'services', 9, '2024-01-01', NULL),
(7, 'FOOD02', 'Premium Coffee', 20.00, 'food', 7, '2024-01-01', '2024-03-01'),
(8, 'SOFT01', 'Software License', 500.00, 'software', 8, '2024-01-01', NULL);

-- -----------------------------------------------------
-- Invoices sample data
-- Different states and dates to test various scenarios:
-- - Paid invoices
-- - Pending payments
-- - Cancelled
-- - Recent vs old
-- -----------------------------------------------------
INSERT INTO invoices (id, number, customer_id, date, due_date, status, total, created_at, deleted_at) VALUES
(1, 'INV-2024-001', 1, '2024-01-15', '2024-02-15', 'paid', 1200.00, '2024-01-15', NULL),
(2, 'INV-2024-002', 2, '2024-02-01', '2024-03-01', 'paid', 2400.00, '2024-02-01', NULL),
(3, 'INV-2024-003', 4, '2024-02-15', '2024-03-15', 'issued', 800.00, '2024-02-15', NULL),
(4, 'INV-2024-004', 1, '2024-03-01', '2024-04-01', 'draft', 150.00, '2024-03-01', NULL),
(5, 'INV-2024-005', 3, '2024-03-01', '2024-04-01', 'cancelled', 500.00, '2024-03-01', NULL),
(6, 'INV-2024-006', 2, '2024-03-15', '2024-04-15', 'issued', 1700.00, '2024-03-15', NULL);

-- -----------------------------------------------------
-- Invoice details sample data
-- Mix of:
-- - Single and multiple items per invoice
-- - Different quantities
-- - Some with discounts
-- -----------------------------------------------------
INSERT INTO invoice_details (id, invoice_id, product_id, quantity, price, discount, total) VALUES
(1, 1, 1, 1, 1200.00, 0.00, 1200.00),
(2, 2, 1, 2, 1200.00, 0.00, 2400.00),
(3, 3, 2, 1, 800.00, 0.00, 800.00),
(4, 4, 3, 1, 150.00, 0.00, 150.00),
(5, 5, 8, 1, 500.00, 0.00, 500.00),
(6, 6, 1, 1, 1200.00, 0.00, 1200.00),
(7, 6, 3, 4, 150.00, 100.00, 500.00);

-- -----------------------------------------------------
-- Payments sample data
-- Different scenarios:
-- - Full and partial payments
-- - Different payment methods
-- - Different status
-- -----------------------------------------------------
INSERT INTO payments (id, invoice_id, date, amount, method, status, reference, created_at) VALUES
(1, 1, '2024-01-20', 1200.00, 'transfer', 'completed', 'TRX-001', '2024-01-20'),
(2, 2, '2024-02-05', 1200.00, 'check', 'completed', 'CHK-001', '2024-02-05'),
(3, 2, '2024-02-10', 1200.00, 'check', 'completed', 'CHK-002', '2024-02-10'),
(4, 3, '2024-02-20', 400.00, 'cash', 'completed', 'CASH-001', '2024-02-20'),
(5, 3, '2024-03-01', 400.00, 'cash', 'pending', 'CASH-002', '2024-03-01'),
(6, 5, '2024-03-02', 500.00, 'transfer', 'rejected', 'TRX-002', '2024-03-02'),
(7, 6, '2024-03-15', 1000.00, 'transfer', 'completed', 'TRX-003', '2024-03-15'),
(8, 6, '2024-03-20', 700.00, 'transfer', 'pending', 'TRX-004', '2024-03-20');
