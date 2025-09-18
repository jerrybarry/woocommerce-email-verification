<?php
/**
 * Database management class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification_Database {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Email verifications table
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            verification_code varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            verified tinyint(1) DEFAULT 0,
            verified_at datetime NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY email (email),
            KEY verification_code (verification_code),
            KEY expires_at (expires_at),
            KEY verified (verified)
        ) $charset_collate;";
        
        // Verification logs table
        $logs_table = $wpdb->prefix . 'wc_email_verification_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Rate limiting table
        $rate_limit_table = $wpdb->prefix . 'wc_email_verification_rate_limits';
        $rate_limit_sql = "CREATE TABLE $rate_limit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            attempts int(11) DEFAULT 1,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_action (identifier, action),
            KEY last_attempt (last_attempt)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($logs_sql);
        dbDelta($rate_limit_sql);
    }
    
    /**
     * Update database tables
     */
    public static function update_tables() {
        global $wpdb;
        
        $installed_version = get_option('wc_email_verification_version', '0.0.0');
        
        // Add new columns if they don't exist
        if (version_compare($installed_version, '1.0.0', '<')) {
            $table_name = $wpdb->prefix . 'wc_email_verifications';
            
            // Check if columns exist and add them if they don't
            $columns = array(
                'verified_at' => 'datetime NULL',
                'ip_address' => 'varchar(45) DEFAULT NULL',
                'user_agent' => 'text DEFAULT NULL',
                'attempts' => 'int(11) DEFAULT 0'
            );
            
            foreach ($columns as $column => $definition) {
                $column_exists = $wpdb->get_results($wpdb->prepare(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
                    $table_name,
                    $column
                ));
                
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column $definition");
                }
            }
        }
    }
    
    /**
     * Drop database tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wc_email_verifications',
            $wpdb->prefix . 'wc_email_verification_logs',
            $wpdb->prefix . 'wc_email_verification_rate_limits'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Clean up expired verification codes
     */
    public static function cleanup_expired_codes() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        
        $wpdb->query(
            "DELETE FROM $table_name 
             WHERE expires_at < NOW() AND verified = 0"
        );
    }
    
    /**
     * Clean up old logs (older than 30 days)
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'wc_email_verification_logs';
        
        $wpdb->query(
            "DELETE FROM $logs_table 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
    
    /**
     * Clean up old rate limit records (older than 24 hours)
     */
    public static function cleanup_old_rate_limits() {
        global $wpdb;
        
        $rate_limit_table = $wpdb->prefix . 'wc_email_verification_rate_limits';
        
        $wpdb->query(
            "DELETE FROM $rate_limit_table 
             WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
    
    /**
     * Get verification record
     *
     * @param string $email
     * @param string $code
     * @return object|null
     */
    public static function get_verification_record($email, $code = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        
        if ($code) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE email = %s AND verification_code = %s 
                 AND expires_at > NOW() AND verified = 0",
                $email,
                $code
            ));
        } else {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE email = %s AND expires_at > NOW() AND verified = 0
                 ORDER BY created_at DESC LIMIT 1",
                $email
            ));
        }
    }
    
    /**
     * Insert verification record
     *
     * @param array $data
     * @return int|false
     */
    public static function insert_verification_record($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        
        $defaults = array(
            'email' => '',
            'verification_code' => '',
            'expires_at' => '',
            'verified' => 0,
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'attempts' => 0
        );
        
        $data = array_merge($defaults, $data);
        
        return $wpdb->insert($table_name, $data);
    }
    
    /**
     * Update verification record
     *
     * @param int $id
     * @param array $data
     * @return int|false
     */
    public static function update_verification_record($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        
        return $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Delete verification records for email
     *
     * @param string $email
     * @return int|false
     */
    public static function delete_verification_records($email) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        
        return $wpdb->delete($table_name, array('email' => $email));
    }
    
    /**
     * Log verification action
     *
     * @param string $email
     * @param string $action
     * @param array $metadata
     * @return int|false
     */
    public static function log_action($email, $action, $metadata = array()) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'wc_email_verification_logs';
        
        $data = array(
            'email' => $email,
            'action' => $action,
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'metadata' => json_encode($metadata)
        );
        
        return $wpdb->insert($logs_table, $data);
    }
    
    /**
     * Check rate limit
     *
     * @param string $identifier
     * @param string $action
     * @param int $limit
     * @param int $window_hours
     * @return bool
     */
    public static function check_rate_limit($identifier, $action, $limit = 5, $window_hours = 1) {
        global $wpdb;
        
        $rate_limit_table = $wpdb->prefix . 'wc_email_verification_rate_limits';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $rate_limit_table 
             WHERE identifier = %s AND action = %s",
            $identifier,
            $action
        ));
        
        if (!$record) {
            // First attempt
            $wpdb->insert($rate_limit_table, array(
                'identifier' => $identifier,
                'action' => $action,
                'attempts' => 1,
                'last_attempt' => current_time('mysql')
            ));
            return true;
        }
        
        $time_diff = time() - strtotime($record->last_attempt);
        $window_seconds = $window_hours * 3600;
        
        if ($time_diff > $window_seconds) {
            // Reset counter
            $wpdb->update(
                $rate_limit_table,
                array(
                    'attempts' => 1,
                    'last_attempt' => current_time('mysql')
                ),
                array('id' => $record->id)
            );
            return true;
        }
        
        if ($record->attempts >= $limit) {
            return false;
        }
        
        // Increment counter
        $wpdb->update(
            $rate_limit_table,
            array(
                'attempts' => $record->attempts + 1,
                'last_attempt' => current_time('mysql')
            ),
            array('id' => $record->id)
        );
        
        return true;
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Get user agent
     *
     * @return string
     */
    private static function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
    }
}
