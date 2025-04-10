
DELIMITER //

DROP PROCEDURE IF EXISTS sp_GetUserData //

CREATE PROCEDURE sp_GetUserData(
    IN p_login_id BIGINT
)
BEGIN
    DECLARE v_user_exists INT;
    
    -- Error handler for SQL exceptions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 
            500 as status,
            'SYSTEM_ERROR' as error_code,
            'Database error occurred' as message,
            NULL as data;
    END;

    START TRANSACTION;
    
    -- Check if user exists and is active
    SELECT COUNT(*) INTO v_user_exists 
    FROM logins l
    WHERE l.id = p_login_id
    AND l.is_active = 1;
    
    IF v_user_exists = 0 THEN
        COMMIT;
        SELECT 
            404 as status,
            'USER_NOT_FOUND' as error_code,
            'User not found or inactive' as message,
            NULL as data;
    ELSE
        -- Get user data with proper type casting and null handling
        SELECT 
            200 as status,
            NULL as error_code,
            NULL as message,
            JSON_OBJECT(
                'user', JSON_OBJECT(
                    'id', CAST(l.id AS UNSIGNED),
                    'email', IFNULL(l.email, ''),
                    'role', IFNULL(l.role, 'user'),
                    'emailVerified', IF(l.email_verified = 1, true, false),
                    'isActive', IF(l.is_active = 1, true, false),
                    'fullName', IFNULL(up.full_name, ''),
                    'nickname', IFNULL(up.nickname, ''),
                    'gender', IFNULL(up.gender, ''),
                    'birthDate', IFNULL(DATE_FORMAT(up.birth_date, '%Y-%m-%d'), ''),
                    'phone', IFNULL(up.phone, ''),
                    'website', IFNULL(up.website, ''),
                    'avatar', IFNULL(up.avatar, ''),
                    'location', JSON_OBJECT(
                        'fullAddress', IFNULL(up.full_address, ''),
                        'coordinates', JSON_OBJECT(
                            'lat', IFNULL(CAST(up.latitude AS DECIMAL(10,6)), 0.000000),
                            'lng', IFNULL(CAST(up.longitude AS DECIMAL(10,6)), 0.000000)
                        )
                    ),
                    'createdAt', DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i:%s'),
                    'updatedAt', DATE_FORMAT(l.updated_at, '%Y-%m-%d %H:%i:%s')
                )
            ) as data
        FROM logins l
        LEFT JOIN user_profiles up ON l.id = up.login_id
        WHERE l.id = p_login_id;
        
        COMMIT;
    END IF;
END //

DELIMITER ;
