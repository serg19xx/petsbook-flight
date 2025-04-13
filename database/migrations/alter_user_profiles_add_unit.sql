ALTER TABLE user_profiles
ADD COLUMN unit_numb VARCHAR(20) DEFAULT NULL AFTER house_number COMMENT 'Apartment/Unit number';