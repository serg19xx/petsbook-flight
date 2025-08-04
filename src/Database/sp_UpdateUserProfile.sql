DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_UpdateUserProfile`(IN `p_login_id` BIGINT, IN `p_full_name` VARCHAR(100), IN `p_nickname` VARCHAR(50), IN `p_gender` ENUM('Male','Female','Other'), IN `p_birth_date` DATE, IN `p_about_me` TEXT, IN `p_contact_email` VARCHAR(145), IN `p_phone` VARCHAR(20), IN `p_website` VARCHAR(255), IN `p_full_address` VARCHAR(255), IN `p_latitude` DECIMAL(10,8), IN `p_longitude` DECIMAL(10,8), IN `p_street_name` VARCHAR(20), IN `p_street_numb` VARCHAR(20), IN `p_unit_numb` VARCHAR(15), IN `p_city` VARCHAR(50), IN `p_district` VARCHAR(100), IN `p_region` VARCHAR(50), IN `p_region_code` VARCHAR(6), IN `p_postcode` VARCHAR(10), IN `p_country` VARCHAR(50), IN `p_country_code` VARCHAR(3))
proc_label: BEGIN
    DECLARE v_user_exists INT DEFAULT 0;
    DECLARE v_login_email VARCHAR(255);
    DECLARE v_error_msg TEXT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 
            v_error_msg = MESSAGE_TEXT;
        ROLLBACK;
        SELECT 
            FALSE as success,
            'DATABASE_ERROR' as error_code,
            v_error_msg as message,
            NULL as data;
    END;

    START TRANSACTION;

    -- Проверяем существование пользователя и получаем login email
    SELECT COUNT(*), email 
    INTO v_user_exists, v_login_email
    FROM logins 
    WHERE id = p_login_id AND is_active = 1;

    IF v_user_exists = 0 THEN
        SELECT 
            FALSE as success,
            'USER_NOT_FOUND' as error_code,
            'User not found or inactive' as message,
            NULL as data;
        LEAVE proc_label;
    END IF;

    -- Добавим проверку формата region_code
    IF p_region_code IS NOT NULL AND p_country_code IS NOT NULL THEN
        -- Если код региона не начинается с кода страны, добавим его
        IF NOT p_region_code LIKE CONCAT(p_country_code, '-%') THEN
            SET p_region_code = CONCAT(p_country_code, '-', p_region_code);
        END IF;
    END IF;

    -- Обновляем существующий профиль
    UPDATE user_profiles 
    SET 
        full_name = p_full_name,
        nickname = p_nickname,
        gender = p_gender,
        birth_date = p_birth_date,
        about_me = p_about_me,
        contact_email = p_contact_email,
        phone = p_phone,
        website = p_website,
        full_address = p_full_address,
        latitude = p_latitude,
        longitude = p_longitude,
        street_name = p_street_name,
        street_numb = p_street_numb,
        unit_numb = p_unit_numb,
        city = p_city,
        district = p_district,
        region = p_region,
        region_code = p_region_code,
        postcode = p_postcode,
        country = p_country,
        country_code = p_country_code,
        date_updated = CURRENT_TIMESTAMP
    WHERE login_id = p_login_id;

    -- Получаем роль пользователя
    SELECT role INTO @user_role FROM logins WHERE id = p_login_id;

    -- Возвращаем обновленные данные
    SELECT 
        TRUE as success,
        NULL as error_code,
        'Profile updated successfully' as message,
        JSON_OBJECT(
			"user",
            JSON_OBJECT(
                'id', p_login_id,
                'loginEmail', v_login_email,
                'contactEmail', p_contact_email,
                'role', @user_role,
                'fullName', p_full_name,
                'nickname', p_nickname,
                'gender', p_gender,
                'birthDate', DATE_FORMAT(p_birth_date, '%Y-%m-%d'),
                'aboutMe', IFNULL(p_about_me, ''),
                'phone', p_phone,
                'website', IFNULL(p_website, ''),
                'location', JSON_OBJECT(
                    'fullAddress', p_full_address,
                    'coordinates', JSON_OBJECT(
                        'lat', p_latitude,
                        'lng', p_longitude
                    ),
                    'components', JSON_OBJECT(
                        'streetName', p_street_name,
                        'streetNumber', p_street_numb,
                        'unitNumber', p_unit_numb,
                        'city', p_city,
                        'district', p_district,
                        'region', p_region,
                        'regionCode', p_region_code,
                        'postcode', p_postcode,
                        'country', p_country,
                        'countryCode', p_country_code
                    )
                )
			)
        ) as data;

    COMMIT;
END$$
DELIMITER ;