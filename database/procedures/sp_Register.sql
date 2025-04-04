DELIMITER //

DROP PROCEDURE IF EXISTS sp_Register //

CREATE PROCEDURE sp_Register(
    IN p_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_password VARCHAR(255),
    IN p_role VARCHAR(50)
)
BEGIN
    DECLARE v_user_table VARCHAR(45);
    DECLARE v_error VARCHAR(255);
    DECLARE v_login_id BIGINT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
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
