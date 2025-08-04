DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Register`(IN `p_name` VARCHAR(255), IN `p_email` VARCHAR(255), IN `p_password` VARCHAR(255), IN `p_role` VARCHAR(50))
sp_login_label: BEGIN
    DECLARE v_profile_table VARCHAR(64);
    DECLARE v_login_id BIGINT;
    DECLARE isCount INT;

    -- Обработчик дубликата email
    DECLARE EXIT HANDLER FOR 1062
    BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email already exists', MYSQL_ERRNO = 1062;
    END;
    
	DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SELECT COUNT(*) INTO isCount
    FROM logins 
    WHERE email = p_email;
    
	IF isCount > 0 THEN
        -- SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email already verified', MYSQL_ERRNO = 45001;
        SELECT 
            FALSE as success,
            'Email already in our database. Please try again with other email' as message,
            'EMAIL_ALREADY_EXISTS' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;    
		LEAVE sp_login_label;
    END IF;

    -- Определяем таблицу профиля по роли
    IF p_role = 'User' THEN
        SET v_profile_table = 'user_profiles';
    ELSEIF p_role = 'Bussinsess' THEN
        SET v_profile_table = 'bussiness_profiles';
    ELSEIF p_role = 'Agent' THEN
        SET v_profile_table = 'agent_profiles';
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid role', MYSQL_ERRNO = 45002;
    END IF;

    -- Вставка в logins
    INSERT INTO logins (email, password, role)
    VALUES (p_email, p_password, p_role);

    SET v_login_id = LAST_INSERT_ID();

    -- Динамическая вставка в профиль
    SET @sql = CONCAT(
        'INSERT INTO ', v_profile_table, 
        ' (login_id, full_name, contact_email, nickname) VALUES (?, ?, ?, ?)'
    );
    SET @login_id = v_login_id;
    SET @full_name = p_name;
    SET @contact_email = p_email;
    SET @nickname = p_name;

    PREPARE stmt FROM @sql;
    EXECUTE stmt USING @login_id, @full_name, @contact_email, @nickname;
    DEALLOCATE PREPARE stmt;

    -- Возвращаем результат
    SELECT TRUE as success, v_login_id AS id, 'User registered successfully' AS message;
END$$
DELIMITER ;