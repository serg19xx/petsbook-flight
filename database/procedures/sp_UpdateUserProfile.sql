DELIMITER //

CREATE DEFINER=`petsbook_serg`@`99.246.236.180` PROCEDURE `sp_UpdateUserProfile`(
    IN p_login_id BIGINT,
    IN p_full_name VARCHAR(100),
    IN p_nickname VARCHAR(50),
    IN p_gender ENUM('male', 'female', 'other'),
    IN p_birth_date DATE,
    IN p_phone VARCHAR(20),
    IN p_website VARCHAR(255),
    IN p_avatar VARCHAR(250),
    IN p_full_address VARCHAR(255),
    IN p_latitude DECIMAL(10,8),
    IN p_longitude DECIMAL(10,8),
    IN p_unit VARCHAR(15),
    IN p_street VARCHAR(100),
    IN p_house_number VARCHAR(20),
    IN p_city VARCHAR(50),
    IN p_district VARCHAR(100),
    IN p_region VARCHAR(50),
    IN p_region_code2 VARCHAR(5),
    IN p_region_code3 VARCHAR(6),
    IN p_postcode VARCHAR(10),
    IN p_country VARCHAR(50),
    IN p_country_code2 VARCHAR(2)
)
BEGIN
    DECLARE v_user_exists INT;
    DECLARE v_error VARCHAR(255);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error = MESSAGE_TEXT;
        SELECT 
            FALSE as success,
            'SQL_ERROR' as error_code,
            v_error as message,
            NULL as data;
        ROLLBACK;
    END;

    START TRANSACTION;
    
    SELECT COUNT(*) INTO v_user_exists 
    FROM user_profiles 
    WHERE login_id = p_login_id;
    
    IF v_user_exists = 0 THEN
        INSERT INTO user_profiles (
            login_id,
            full_name,
            nickname,
            gender,
            birth_date,
            phone,
            website,
            avatar,
            full_address,
            latitude,
            longitude,
            unit_numb,
            street_name,
            street_numb,
            city,
            district,
            region,
            region_code2,
            region_code3,
            postcode,
            country,
            country_code2,
            date_created,
            date_updated
        ) VALUES (
            p_login_id,
            p_full_name,
            p_nickname,
            p_gender,
            p_birth_date,
            p_phone,
            p_website,
            p_avatar,
            p_full_address,
            p_latitude,
            p_longitude,
            p_unit,
            p_street,
            p_house_number,
            p_city,
            p_district,
            p_region,
            p_region_code2,
            p_region_code3,
            p_postcode,
            p_country,
            p_country_code2,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        );
    ELSE
        UPDATE user_profiles 
        SET 
            full_name = p_full_name,
            nickname = p_nickname,
            gender = p_gender,
            birth_date = p_birth_date,
            phone = p_phone,
            website = p_website,
            avatar = p_avatar,
            full_address = p_full_address,
            latitude = p_latitude,
            longitude = p_longitude,
            unit_numb = p_unit,
            street_name = p_street,
            street_numb = p_house_number,
            city = p_city,
            district = p_district,
            region = p_region,
            region_code2 = p_region_code2,
            region_code3 = p_region_code3,
            postcode = p_postcode,
            country = p_country,
            country_code2 = p_country_code2,
            date_updated = CURRENT_TIMESTAMP
        WHERE login_id = p_login_id;
    END IF;
    
    -- Получение email и role пользователя
    SELECT 
        l.email, l.role INTO @user_email, @user_role
    FROM logins l
    WHERE l.id = p_login_id;

    -- Формирование ответа
    SELECT 
        TRUE as success,
        NULL as error_code,
        'Profile updated successfully' as message,
        JSON_OBJECT(
            'user', JSON_OBJECT(
                'id', p_login_id,
                'email', @user_email,
                'role', @user_role,
                'fullName', p_full_name,
                'nickname', p_nickname,
                'gender', p_gender,
                'birthDate', DATE_FORMAT(p_birth_date, '%Y-%m-%d'),
                'phone', p_phone,
                'website', p_website,
                'avatar', p_avatar,
                'location', JSON_OBJECT(
                    'fullAddress', p_full_address,
                    'coordinates', JSON_OBJECT(
                        'lat', p_latitude,
                        'lng', p_longitude
                    ),
                    'components', JSON_OBJECT(
                        'street', p_street,
                        'houseNumber', p_house_number,
                        'unitNumb', p_unit,
                        'city', p_city,
                        'district', p_district,
                        'region', p_region,
                        'postcode', p_postcode,
                        'country', p_country,
                        'countryCode', p_country_code2,
                        'subdivisionCode', JSON_OBJECT(
                            'alpha2', p_region_code2,
                            'alpha3', p_region_code3
                        )
                    )
                )
            )
        ) as data;

    COMMIT;
END //

DELIMITER ;
