CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, category TEXT, price INTEGER);
INSERT INTO products VALUES
    (1, 'Laptop', 'computing', 100),
    (2, 'Mouse', 'computing', 20),
    (3, 'Pen', 'office', 5),
    (4, 'Notebook', 'office', 3);
CREATE TABLE stock (product_id INTEGER, quantity INTEGER);
INSERT INTO stock VALUES (1, 3), (2, 0), (99, 1);
