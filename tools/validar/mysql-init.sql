-- Banco DESCARTÁVEL de validação (tools/validar). Dois bancos: erp_teste (app, usuário erp/erp — o mesmo que os
-- roteiros scripts/validar-*.sh já usam) e erp_comercial_test (phpunit.xml: erp_user/erp_password).
CREATE DATABASE IF NOT EXISTS erp_teste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS erp_comercial_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'erp'@'%' IDENTIFIED BY 'erp';
CREATE USER IF NOT EXISTS 'erp_user'@'%' IDENTIFIED BY 'erp_password';
GRANT ALL ON erp_teste.* TO 'erp'@'%';
GRANT ALL ON erp_comercial_test.* TO 'erp_user'@'%';
GRANT ALL ON erp_teste.* TO 'erp_user'@'%';
FLUSH PRIVILEGES;
