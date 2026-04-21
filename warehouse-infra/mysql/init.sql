-- Database Initialization Script for Warehouse Ecosystem
-- Automated provisioning of databases, users, and least-privilege grants.

-- 1. Create Databases
CREATE DATABASE IF NOT EXISTS warehouse_system;
CREATE DATABASE IF NOT EXISTS warehouse_qc;
CREATE DATABASE IF NOT EXISTS warehouse_inventory;

-- 2. Create Application Users (Strong, unique passwords)
CREATE USER IF NOT EXISTS 'warehouse_system_user'@'%' IDENTIFIED BY 'wh_sys_k8q2pL9zX_prod';
CREATE USER IF NOT EXISTS 'warehouse_qc_user'@'%' IDENTIFIED BY 'wh_qc_m5N1qB7yT_prod';
CREATE USER IF NOT EXISTS 'warehouse_inventory_user'@'%' IDENTIFIED BY 'wh_inv_r4T3vX6sW_prod';

-- 3. Grant Permissions (Least Privilege - Only access their own DB)
GRANT ALL PRIVILEGES ON warehouse_system.* TO 'warehouse_system_user'@'%';
GRANT ALL PRIVILEGES ON warehouse_qc.* TO 'warehouse_qc_user'@'%';
GRANT ALL PRIVILEGES ON warehouse_inventory.* TO 'warehouse_inventory_user'@'%';

-- 4. Apply Changes
FLUSH PRIVILEGES;
