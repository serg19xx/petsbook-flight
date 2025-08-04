DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ValidateToken`(IN `p_token` VARCHAR(255))
BEGIN
    DECLARE v_is_blacklisted BOOLEAN;
    DECLARE v_is_expired BOOLEAN;
    DECLARE v_user_id INT;
    
    -- Проверяем черный список
    SELECT EXISTS(
        SELECT 1 FROM blacklisted_tokens 
        WHERE token = p_token
    ) INTO v_is_blacklisted;
    
    IF v_is_blacklisted THEN
        SELECT 
            FALSE as valid,
            'Token is blacklisted' as message;
    ELSE
        -- Проверяем срок действия и получаем пользователя
        SELECT 
            ut.user_id,
            ut.expires_at < NOW()
        INTO v_user_id, v_is_expired
        FROM user_tokens ut
        WHERE ut.token = p_token;
        
        IF v_user_id IS NULL THEN
            SELECT 
                FALSE as valid,
                'Token not found' as message;
        ELSEIF v_is_expired THEN
            SELECT 
                FALSE as valid,
                'Token expired' as message;
        ELSE
            SELECT 
                TRUE as valid,
                'Token is valid' as message,
                u.id,
                u.email,
                u.role
            FROM logins u
            WHERE u.id = v_user_id;
        END IF;
    END IF;
END$$
DELIMITER ;