-- Execute com um usuário administrador do MySQL
CREATE USER IF NOT EXISTS 'atendy_user'@'localhost' IDENTIFIED BY 'atendy123';
GRANT ALL PRIVILEGES ON atendy.* TO 'atendy_user'@'localhost';
FLUSH PRIVILEGES;


