<?php
/**
 * Logger class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    
    /**
     * Log file path
     *
     * @var string
     */
    private static $log_file = null;
    
    /**
     * Get log file path
     *
     * @return string
     */
    private static function get_log_file() {
        if (null === self::$log_file) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wc-email-verification-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            self::$log_file = $log_dir . '/verification.log';
        }
        
        return self::$log_file;
    }
    
    /**
     * Log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public static function log($level, $message, $context = array()) {
        if (!self::should_log($level)) {
            return;
        }
        
        $log_entry = self::format_log_entry($level, $message, $context);
        self::write_to_file($log_entry);
    }
    
    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    public static function debug($message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public static function info($message, $context = array()) {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    public static function warning($message, $context = array()) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public static function error($message, $context = array()) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Check if should log based on level
     *
     * @param string $level
     * @return bool
     */
    private static function should_log($level) {
        $log_levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        );
        
        $current_level = $log_levels[$level] ?? 0;
        $min_level = $log_levels[self::get_min_log_level()] ?? 0;
        
        return $current_level >= $min_level;
    }
    
    /**
     * Get minimum log level
     *
     * @return string
     */
    private static function get_min_log_level() {
        return defined('WP_DEBUG') && WP_DEBUG ? self::LEVEL_DEBUG : self::LEVEL_ERROR;
    }
    
    /**
     * Format log entry
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private static function format_log_entry($level, $message, $context) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        
        $log_entry = "[{$timestamp}] {$level_upper}: {$message}";
        
        if (!empty($context)) {
            $log_entry .= ' | Context: ' . json_encode($context);
        }
        
        $log_entry .= PHP_EOL;
        
        return $log_entry;
    }
    
    /**
     * Write to log file
     *
     * @param string $log_entry
     */
    private static function write_to_file($log_entry) {
        $log_file = self::get_log_file();
        
        // Rotate log file if it's too large (5MB)
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            self::rotate_log_file();
        }
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotate log file
     */
    private static function rotate_log_file() {
        $log_file = self::get_log_file();
        $backup_file = $log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
        
        if (file_exists($log_file)) {
            rename($log_file, $backup_file);
        }
        
        // Keep only last 5 backup files
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wc-email-verification-logs';
        $backup_files = glob($log_dir . '/*.bak');
        
        if (count($backup_files) > 5) {
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $files_to_delete = array_slice($backup_files, 0, count($backup_files) - 5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get log contents
     *
     * @param int $lines
     * @return string
     */
    public static function get_log_contents($lines = 100) {
        $log_file = self::get_log_file();
        
        if (!file_exists($log_file)) {
            return '';
        }
        
        $file_lines = file($log_file);
        $file_lines = array_reverse($file_lines);
        $file_lines = array_slice($file_lines, 0, $lines);
        $file_lines = array_reverse($file_lines);
        
        return implode('', $file_lines);
    }
    
    /**
     * Clear log file
     */
    public static function clear_log() {
        $log_file = self::get_log_file();
        
        if (file_exists($log_file)) {
            unlink($log_file);
        }
    }
    
    /**
     * Get log file size
     *
     * @return int
     */
    public static function get_log_file_size() {
        $log_file = self::get_log_file();
        
        if (!file_exists($log_file)) {
            return 0;
        }
        
        return filesize($log_file);
    }
    
    /**
     * Get log file size formatted
     *
     * @return string
     */
    public static function get_log_file_size_formatted() {
        $size = self::get_log_file_size();
        
        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1024 * 1024) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / (1024 * 1024), 2) . ' MB';
        }
    }
}
