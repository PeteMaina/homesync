/* this code creates a new database homesync with table called 'tenants' with columns 
for id(pk), id_number, house_number, phone_number, rented_month, rented_year 
and created_at timestamp

anothertable for tenant_bills with columns for id(pk), id_number, house_number, bill_type, 
amount, due_date, status, created_at timestamp

for the bills there are 5 types: rent, wifi, water(ksh200per unit), garbage (ksh200), 

another table for visitors with columns for id_number, name, phone_number, visit_date, 
visit_time, time_out, id_number, house_number

another table for now the admins with columns for id, username, email, password, created_at timestamp

when you paste it on any sql code reader, it creates the table*/

CREATE DATABASE IF NOT EXISTS homesync;
USE homesync;
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    house_number VARCHAR(10) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    rented_month VARCHAR(20) NOT NULL,
    rented_year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS tenant_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    house_number VARCHAR(10) NOT NULL,
    bill_type ENUM('rent', 'wifi', 'water', 'garbage') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_number) REFERENCES tenants(id_number)
);
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    time_out TIME,
    id_number VARCHAR(20) NOT NULL,
    house_number VARCHAR(10) NOT NULL,
    FOREIGN KEY (id_number) REFERENCES tenants(id_number)
);
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- End of database creation code
/* the code below adds a numberplate column to the visitors table */
ALTER TABLE visitors ADD COLUMN numberplate VARCHAR(20);
-- End of adding numberplate column
/* the code below adds a column for payment_date to the tenant_bills table */
ALTER TABLE tenant_bills ADD COLUMN payment_date DATE;
-- End of adding payment_date column
/* the code below creates an index on the id_number column in the tenants table */
CREATE INDEX idx_id_number ON tenants(id_number);
-- End of creating index

