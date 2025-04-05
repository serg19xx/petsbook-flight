DELIMITER //

DROP PROCEDURE IF EXISTS `sp_Login` //

CREATE PROCEDURE `sp_Login`(
    IN p_email VARCHAR(255)
)
sp_login_label: BEGIN
    DECLARE v_user_id INT;
    DECLARE v_stored_password VARCHAR(255);
    DECLARE v_role VARCHAR(50);
    DECLARE v_email_verified TINYINT;
    DECLARE v_is_active TINYINT;
    
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
            'Неверные учетные данные' as message,
            'INVALID_CREDENTIALS' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Проверяем активность аккаунта
    IF v_is_active = 0 THEN
        SELECT 
            FALSE as success,
            'Ваш аккаунт заблокирован. Пожалуйста, обратитесь в службу поддержки.' as message,
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
            'Ваш email не подтвержден. Пожалуйста, проверьте вашу почту и пройдите по ссылке для подтверждения.' as message,
            'EMAIL_NOT_VERIFIED' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Возвращаем данные для проверки
    SELECT 
        TRUE as success,
        'Вход выполнен успешно' as message,
        NULL as error_code,
        v_user_id as id,
        v_stored_password as stored_password,
        v_role as role;

END //

DELIMITER ;
