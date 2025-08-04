DELIMITER $$
CREATE DEFINER=`petsbook_serg`@`localhost` PROCEDURE `sp_Logout`(IN `p_token` VARCHAR(255))
BEGIN
    DECLARE v_token_exists INT;
    
    -- Проверяем, не находится ли токен уже в черном списке
    SELECT COUNT(*) INTO v_token_exists 
    FROM blacklisted_tokens 
    WHERE token = p_token;
    
    IF v_token_exists = 0 THEN
        -- Добавляем токен в черный список только если его там еще нет
        INSERT INTO blacklisted_tokens (token, blacklisted_at)
        VALUES (p_token, NOW());
    END IF;
    
    -- Удаляем активный токен из user_tokens в любом случае
    DELETE FROM user_tokens 
    WHERE token = p_token;
    
    SELECT TRUE as success, 'Logout successful' as message;
END$$
DELIMITER ;