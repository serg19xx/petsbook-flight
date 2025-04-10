DELIMITER //

DROP PROCEDURE IF EXISTS sp_UpdateProfile //

CREATE PROCEDURE sp_UpdateProfile(
    IN p_login_id BIGINT,
    IN p_full_name VARCHAR(255),
    IN p_nickname VARCHAR(50),
    IN p_role VARCHAR(20),
    IN p_gender ENUM('male', 'female', 'other'),
    IN p_gender_private BOOLEAN,
    IN p_birth_date DATE,
    IN p_birth_date_private BOOLEAN,
    IN p_email VARCHAR(255),
    IN p_email_private BOOLEAN,
    IN p_phone VARCHAR(20),
    IN p_phone_private BOOLEAN,
    IN p_website VARCHAR(255),
    IN p_website_private BOOLEAN,
    IN p_address TEXT,
    IN p_address_private BOOLEAN,
    IN p_latitude DECIMAL(10, 8),
    IN p_longitude DECIMAL(11, 8),
    IN p_street VARCHAR(255),
    IN p_house_number VARCHAR(20),
    IN p_city VARCHAR(100),
    IN p_district VARCHAR(100),
    IN p_region VARCHAR(100),
    IN p_postcode VARCHAR(20),
    IN p_country VARCHAR(100)
)
BEGIN
    DECLARE v_user_table VARCHAR(50);
    DECLARE v_current_role VARCHAR(20);
    DECLARE v_error VARCHAR(255);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 
            FALSE as success,
            'SQL_ERROR' as error_code,
            v_error as message,
            NULL as data;
    END;

    START TRANSACTION;

    -- Проверяем существование пользователя и получаем текущую роль
    SELECT role INTO v_current_role
    FROM logins
    WHERE id = p_login_id;

    IF v_current_role IS NULL THEN
        SELECT 
            FALSE as success,
            'USER_NOT_FOUND' as error_code,
            'User not found' as message,
            NULL as data;
        LEAVE sp_UpdateProfile;
    END IF;

    -- Проверяем валидность новой роли
    IF p_role NOT IN ('petowner', 'business', 'producer') THEN
        SELECT 
            FALSE as success,
            'INVALID_ROLE' as error_code,
            'Invalid role specified' as message,
            NULL as data;
        LEAVE sp_UpdateProfile;
    END IF;

    -- Получаем имя таблицы для роли
    SELECT user_tbl INTO v_user_table 
    FROM role_table 
    WHERE role = p_role;

    -- Обновляем роль в таблице logins если она изменилась
    IF v_current_role != p_role THEN
        UPDATE logins 
        SET role = p_role 
        WHERE id = p_login_id;
    END IF;

    -- Обновляем профиль пользователя
    SET @sql = CONCAT('UPDATE ', v_user_table, ' 
        SET 
            full_name = ?,
            nickname = ?,
            gender = ?,
            gender_private = ?,
            birth_date = ?,
            birth_date_private = ?,
            email = ?,
            email_private = ?,
            phone = ?,
            phone_private = ?,
            website = ?,
            website_private = ?,
            address = ?,
            address_private = ?,
            latitude = ?,
            longitude = ?,
            street = ?,
            house_number = ?,
            city = ?,
            district = ?,
            region = ?,
            postcode = ?,
            country = ?,
            date_updated = NOW()
        WHERE login_id = ?');

    PREPARE stmt FROM @sql;
    EXECUTE stmt USING 
        p_full_name,
        p_nickname,
        p_gender,
        p_gender_private,
        p_birth_date,
        p_birth_date_private,
        p_email,
        p_email_private,
        p_phone,
        p_phone_private,
        p_website,
        p_website_private,
        p_address,
        p_address_private,
        p_latitude,
        p_longitude,
        p_street,
        p_house_number,
        p_city,
        p_district,
        p_region,
        p_postcode,
        p_country,
        p_login_id;
    DEALLOCATE PREPARE stmt;

    -- Получаем обновленные данные
    SET @sql = CONCAT('SELECT * FROM ', v_user_table, ' WHERE login_id = ?');
    PREPARE stmt FROM @sql;
    EXECUTE stmt USING p_login_id;
    DEALLOCATE PREPARE stmt;

    COMMIT;

    SELECT 
        TRUE as success,
        NULL as error_code,
        'Profile updated successfully' as message,
        JSON_OBJECT(
            'user', JSON_OBJECT(
                'fullName', p_full_name,
                'nickname', p_nickname,
                'role', p_role,
                'gender', p_gender,
                'genderPrivate', p_gender_private,
                'birthDate', p_birth_date,
                'birthDatePrivate', p_birth_date_private,
                'email', p_email,
                'emailPrivate', p_email_private,
                'phone', p_phone,
                'phonePrivate', p_phone_private,
                'website', p_website,
                'websitePrivate', p_website_private,
                'location', JSON_OBJECT(
                    'fullAddress', p_address,
                    'coordinates', JSON_OBJECT(
                        'lat', p_latitude,
                        'lng', p_longitude
                    ),
                    'components', JSON_OBJECT(
                        'street', p_street,
                        'houseNumber', p_house_number,
                        'city', p_city,
                        'district', p_district,
                        'region', p_region,
                        'postcode', p_postcode,
                        'country', p_country
                    ),
                    'isPrivate', p_address_private
                )
            )
        ) as data;
END //

DELIMITER ;