DELIMITER //

CREATE OR REPLACE PROCEDURE sp_UpdateUserProfile(
    IN p_login_id BIGINT,
    IN p_full_name VARCHAR(100),
    IN p_nickname VARCHAR(50),
    IN p_gender ENUM('male', 'female', 'other'),
    IN p_birth_date DATE,
    IN p_about_me VARCHAR(500),
    IN p_email VARCHAR(255),
    IN p_phone VARCHAR(20),
    IN p_website VARCHAR(255),
    IN p_avatar VARCHAR(255),
    -- Location fields
    IN p_full_address TEXT,
    IN p_latitude DECIMAL(10, 8),
    IN p_longitude DECIMAL(11, 8),
    IN p_street VARCHAR(255),
    IN p_house_number VARCHAR(20),
    IN p_unit_numb VARCHAR(20),
    IN p_city VARCHAR(100),
    IN p_district VARCHAR(100),
    IN p_region VARCHAR(100),
    IN p_region_code VARCHAR(10),
    IN p_postcode VARCHAR(20),
    IN p_country VARCHAR(100),
    IN p_country_code CHAR(2)
)
proc_label: BEGIN
    DECLARE v_user_exists INT DEFAULT 0;
    DECLARE v_current_email VARCHAR(255);
    DECLARE v_email_changed BOOLEAN DEFAULT FALSE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 
            FALSE as success,
            'DATABASE_ERROR' as error_code,
            'Database error occurred' as message,
            NULL as data;
    END;

    START TRANSACTION;

    -- Validate required fields
    IF p_full_name IS NULL OR p_nickname IS NULL OR p_gender IS NULL OR 
       p_birth_date IS NULL OR p_email IS NULL OR p_phone IS NULL THEN
        SELECT 
            FALSE as success,
            'MISSING_REQUIRED_FIELDS' as error_code,
            'Required fields are missing' as message,
            NULL as data;
        LEAVE proc_label;
    END IF;

    -- Check if user exists and get current email
    SELECT COUNT(*), email 
    INTO v_user_exists, v_current_email
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

    -- Check if email is being changed
    IF p_email != v_current_email THEN
        SET v_email_changed = TRUE;
        
        -- Check if new email already exists
        IF EXISTS (SELECT 1 FROM logins WHERE email = p_email AND id != p_login_id) THEN
            SELECT 
                FALSE as success,
                'EMAIL_EXISTS' as error_code,
                'Email already registered' as message,
                NULL as data;
            LEAVE proc_label;
        END IF;

        -- Update email in logins table
        UPDATE logins 
        SET 
            email = p_email,
            email_verified = 0,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_login_id;

        -- Create verification token
        INSERT INTO email_verification_tokens (
            user_id,
            token,
            expires_at
        ) VALUES (
            p_login_id,
            UUID(),
            DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
        );
    END IF;

    -- Update user profile
    INSERT INTO user_profiles (
        login_id,
        full_name,
        nickname,
        gender,
        birth_date,
        about_me,
        phone,
        website,
        avatar,
        full_address,
        latitude,
        longitude,
        street,
        house_number,
        unit_numb,
        city,
        district,
        region,
        region_code,
        postcode,
        country,
        country_code,
        date_updated
    ) VALUES (
        p_login_id,
        p_full_name,
        p_nickname,
        p_gender,
        p_birth_date,
        p_about_me,
        p_phone,
        p_website,
        p_avatar,
        p_full_address,
        p_latitude,
        p_longitude,
        p_street,
        p_house_number,
        p_unit_numb,
        p_city,
        p_district,
        p_region,
        p_region_code,
        p_postcode,
        p_country,
        p_country_code,
        CURRENT_TIMESTAMP
    ) ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        nickname = VALUES(nickname),
        gender = VALUES(gender),
        birth_date = VALUES(birth_date),
        about_me = VALUES(about_me),
        phone = VALUES(phone),
        website = VALUES(website),
        avatar = COALESCE(VALUES(avatar), avatar),
        full_address = VALUES(full_address),
        latitude = VALUES(latitude),
        longitude = VALUES(longitude),
        street = VALUES(street),
        house_number = VALUES(house_number),
        unit_numb = VALUES(unit_numb),
        city = VALUES(city),
        district = VALUES(district),
        region = VALUES(region),
        region_code = VALUES(region_code),
        postcode = VALUES(postcode),
        country = VALUES(country),
        country_code = VALUES(country_code),
        date_updated = CURRENT_TIMESTAMP;

    -- Get user role
    SELECT role INTO @user_role FROM logins WHERE id = p_login_id;

    -- Return success response with updated data
    SELECT 
        TRUE as success,
        NULL as error_code,
        'Profile updated successfully' as message,
        JSON_OBJECT(
            'user', JSON_OBJECT(
                'id', p_login_id,
                'email', p_email,
                'role', @user_role,
                'emailVerified', IF(v_email_changed, false, true),
                'fullName', p_full_name,
                'nickname', p_nickname,
                'gender', p_gender,
                'birthDate', DATE_FORMAT(p_birth_date, '%Y-%m-%d'),
                'aboutMe', IFNULL(p_about_me, ''),
                'phone', p_phone,
                'website', IFNULL(p_website, ''),
                'avatar', IFNULL(p_avatar, ''),
                'location', JSON_OBJECT(
                    'fullAddress', p_full_address,
                    'coordinates', JSON_OBJECT(
                        'lat', p_latitude,
                        'lng', p_longitude
                    ),
                    'components', JSON_OBJECT(
                        'street', p_street,
                        'houseNumber', p_house_number,
                        'unitNumb', IFNULL(p_unit_numb, ''),
                        'city', p_city,
                        'district', IFNULL(p_district, ''),
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
END //

DELIMITER ;
