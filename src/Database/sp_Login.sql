DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_Login`(IN `p_email` VARCHAR(255))
sp_login_label: BEGIN
    DECLARE v_user_id INT;
    DECLARE v_stored_password VARCHAR(255);
    DECLARE v_role VARCHAR(50);
    DECLARE v_email_verified TINYINT;
    DECLARE v_is_active TINYINT;
    DECLARE v_full_name VARCHAR(50);
    
    -- Получаем данные пользователя
    SELECT 
        id, 
        password, 
        role, 
        email_verified,
        is_active
    INTO 
        v_user_id, 
        v_stored_password, 
        v_role, 
        v_email_verified,
        v_is_active
    FROM logins 
    WHERE email = p_email 
    LIMIT 1;
    
    -- Проверяем существование пользователя
    IF v_user_id IS NULL THEN
        SELECT 
            FALSE as success,
            'Invalid or missing credentials' as message,
            'MISSING_CREDENTIALS' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Проверяем активность аккаунта
    IF v_is_active = 0 THEN
        SELECT 
            FALSE as success,
            'Your account has been blocked. Please contact support.' as message,
            'ACCOUNT_BLOCKED' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Проверяем верификацию email
    IF v_email_verified = 0 THEN
        SELECT 
            FALSE as success,
            'Your email is not verified. Please check your email and follow the verification link.' as message,
            'EMAIL_NOT_VERIFIED' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    IF v_role = 'user' THEN
		SELECT full_name into v_full_name FROM user_profiles where login_id=v_user_id limit 1;
    end if;
    
    -- Возвращаем данные для проверки
    SELECT 
        TRUE as success,
        'Login successful' as message,
        NULL as error_code,
        v_user_id as id,
        v_full_name as full_name,
        v_stored_password as stored_password,
        v_role as role;

END$$
DELIMITER ;