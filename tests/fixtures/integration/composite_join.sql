PRAGMA foreign_keys = ON;
CREATE TABLE orders (
    id INTEGER NOT NULL,
    branch_id INTEGER NOT NULL,
    PRIMARY KEY (id, branch_id)
);
CREATE TABLE order_items (
    id INTEGER PRIMARY KEY,
    order_id INTEGER NOT NULL,
    branch_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY (order_id, branch_id) REFERENCES orders (id, branch_id)
);
INSERT INTO orders VALUES (1, 10), (1, 20), (2, 10);
INSERT INTO order_items VALUES
    (101, 1, 10, 'First branch item'),
    (102, 1, 20, 'Second branch item'),
    (103, 2, 10, 'Other order item');
