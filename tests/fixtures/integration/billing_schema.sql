-- Clientes
CREATE TABLE customers (
    id INTEGER PRIMARY KEY,
    tax_id TEXT UNIQUE,    -- para búsquedas exactas
    name TEXT,             -- para LIKE/regexp
    type TEXT,             -- para IN/NOT IN (person, company)
    status TEXT,           -- para IN/NOT IN (active, inactive)
    created_at TEXT,       -- para operadores date
    deleted_at TEXT        -- para NULL tests
);

-- Productos
CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    code TEXT UNIQUE,      -- para búsquedas exactas
    name TEXT,             -- para LIKE/regexp
    price DECIMAL,         -- para comparaciones numéricas
    category TEXT,         -- para IN/NOT IN
    flags INTEGER,         -- para bitwise (características como taxable, importable, etc)
    created_at TEXT,
    deleted_at TEXT
);

-- Facturas
CREATE TABLE invoices (
    id INTEGER PRIMARY KEY,
    number TEXT UNIQUE,    -- para búsquedas exactas
    customer_id INTEGER,   -- para joins
    date TEXT,             -- para operadores date
    due_date TEXT,         -- para operadores date
    status TEXT,           -- para IN/NOT IN (draft, issued, paid, cancelled)
    total DECIMAL,         -- para operadores numéricos/range
    created_at TEXT,
    deleted_at TEXT,
    FOREIGN KEY(customer_id) REFERENCES customers(id)
);

-- Detalles de factura
CREATE TABLE invoice_details (
    id INTEGER PRIMARY KEY,
    invoice_id INTEGER,    -- para joins
    product_id INTEGER,    -- para joins
    quantity INTEGER,      -- para operadores numéricos
    price DECIMAL,         -- para operadores numéricos
    discount DECIMAL,      -- para operadores numéricos
    total DECIMAL,         -- para operadores numéricos
    FOREIGN KEY(invoice_id) REFERENCES invoices(id),
    FOREIGN KEY(product_id) REFERENCES products(id)
);

-- Pagos
CREATE TABLE payments (
    id INTEGER PRIMARY KEY,
    invoice_id INTEGER,    -- para joins
    date TEXT,             -- para operadores date
    amount DECIMAL,        -- para operadores numéricos
    method TEXT,           -- para IN/NOT IN (cash, transfer, check)
    status TEXT,           -- para IN/NOT IN (pending, completed, rejected)
    reference TEXT,        -- para LIKE/regexp
    created_at TEXT,
    FOREIGN KEY(invoice_id) REFERENCES invoices(id)
);
