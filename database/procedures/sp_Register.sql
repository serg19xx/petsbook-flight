DELIMITER //

DROP PROCEDURE IF EXISTS sp_Register //

CREATE PROCEDURE sp_Register(
    IN p_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_password VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_role VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
)
BEGIN
    DECLARE v_user_table VARCHAR(45);
    DECLARE v_error VARCHAR(255);
    DECLARE v_login_id BIGINT;
    
    -- Обработчик должен быть в самом начале процедуры
    DECLARE EXIT HANDLER FOR 1062
    BEGIN
        SELECT 
            FALSE as success,
            NULL as id,
            'EMAIL_EXISTS' as error_code,
            'Email already exists' as message;
    END;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 
            FALSE as success,
            NULL as id,
            'SQL_ERROR' as error_code,
            v_error as message;
    END;

    START TRANSACTION;
    
    -- Установка кодировки для сессии
    SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
    
    SELECT user_tbl INTO v_user_table 
    FROM role_table 
    WHERE role = p_role;
    
    IF v_user_table IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid role specified';
    END IF;

    START TRANSACTION;

    INSERT INTO logins (email, password, role, is_active, email_verified, two_factor_enabled)
    VALUES (p_email, p_password, p_role, 1, 0, 0);
    
    SET v_login_id = LAST_INSERT_ID();
    
    SET @login_id = v_login_id;
    SET @user_name = p_name;
    SET @sql = CONCAT('INSERT INTO ', v_user_table, ' (login_id, name) VALUES (?, ?)');
    PREPARE stmt FROM @sql;
    EXECUTE stmt USING @login_id, @user_name;
    DEALLOCATE PREPARE stmt;
    
    COMMIT;
    
    SELECT TRUE as success, v_login_id as id, 'User registered successfully' as message;
END //

DELIMITER ;
