CREATE TABLE `user_profiles` (
    `login_id` bigint(20) unsigned NOT NULL COMMENT 'Primary key',
    `full_name` varchar(100) NOT NULL COMMENT 'User full name',
    `nickname` varchar(50) NOT NULL COMMENT 'User nickname',
    `role` enum('petowner','business','producer') NOT NULL DEFAULT 'petowner' COMMENT 'User role',
    `gender` enum('male','female','other') NOT NULL COMMENT 'Gender',
    `birth_date` date NOT NULL COMMENT 'Date of birth',
    `email` varchar(255) NOT NULL COMMENT 'Email address',
    `phone` varchar(20) NOT NULL COMMENT 'Phone number',
    `website` varchar(255) DEFAULT NULL COMMENT 'Website URL',
    
    -- Location data
    `full_address` text NOT NULL COMMENT 'Full formatted address',
    `latitude` decimal(10,8) NOT NULL COMMENT 'Location latitude',
    `longitude` decimal(11,8) NOT NULL COMMENT 'Location longitude',
    
    -- Address components
    `street` varchar(255) NOT NULL COMMENT 'Street name',
    `house_number` varchar(20) NOT NULL COMMENT 'House number',
    `city` varchar(100) NOT NULL COMMENT 'City name',
    `district` varchar(100) DEFAULT NULL COMMENT 'District name',
    `region` varchar(100) NOT NULL COMMENT 'Region/State',
    `postcode` varchar(20) NOT NULL COMMENT 'Postal code',
    `country` varchar(100) NOT NULL COMMENT 'Country name',
    
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    
    PRIMARY KEY (`login_id`),
    KEY `idx_role` (`role`),
    KEY `idx_location` (`latitude`, `longitude`),
    CONSTRAINT `fk_user_profiles_login` FOREIGN KEY (`login_id`) REFERENCES `logins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User profiles';
