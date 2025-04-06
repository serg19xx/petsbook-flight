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
    
    -- Get user data
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
    
    -- Check if user exists
    IF v_user_id IS NULL THEN
        SELECT 
            FALSE as success,
            'Invalid credentials' as message,
            'INVALID_CREDENTIALS' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Check account status
    IF v_is_active = 0 THEN
        SELECT 
            FALSE as success,
            'Your account is blocked. Please contact support.' as message,
            'ACCOUNT_BLOCKED' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Check email verification
    IF v_email_verified = 0 THEN
        SELECT 
            FALSE as success,
            'Your email is not verified. Please check your inbox and follow the verification link.' as message,
            'EMAIL_NOT_VERIFIED' as error_code,
            NULL as id,
            NULL as stored_password,
            NULL as role;
        LEAVE sp_login_label;
    END IF;

    -- Return data for verification
    SELECT 
        TRUE as success,
        'Login successful' as message,
        NULL as error_code,
        v_user_id as id,
        v_stored_password as stored_password,
        v_role as role;

END //

DELIMITER ;
