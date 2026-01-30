<?php

namespace Modules\ZabbixGraphTrees\Lib;

class LanguageManager {
    /**
     * Keep identifiers and defaults consistent with Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'en_US' => [
            'Graph Trees' => 'Graph Trees',
            'Resource Tree' => 'Resource Tree',
            'Host Groups' => 'Host Groups',
            'Hosts' => 'Hosts',
            'Tags' => 'Tags',
            'Tag Value' => 'Tag Value',
            'Time Range' => 'Time Range',
            'Select Tag' => 'Select Tag',
            'Select Tag Value' => 'Select Tag Value',
            'Select Time Range' => 'Select Time Range',
            'Last 10 Minutes' => 'Last 10 Minutes',
            'Last 30 Minutes' => 'Last 30 Minutes',
            'Last Hour' => 'Last Hour',
            'Last 3 Hours' => 'Last 3 Hours',
            'Last 6 Hours' => 'Last 6 Hours',
            'Last 12 Hours' => 'Last 12 Hours',
            'Last 24 Hours' => 'Last 24 Hours',
            'Last 7 Days' => 'Last 7 Days',
            'Last 30 Days' => 'Last 30 Days',
            'Custom' => 'Custom',
            'From' => 'From',
            'To' => 'To',
            'Apply' => 'Apply',
            'Refresh' => 'Refresh',
            'No data available' => 'No data available',
            'No data' => 'No data',
            'No valid data' => 'No valid data',
            'Loading...' => 'Loading...',
            'Select a host to view graphs' => 'Select a host to view graphs',
            'No items found for this host' => 'No items found for this host',
            'Monitoring Graphs' => 'Monitoring Graphs',
            'All Tags' => 'All Tags',
            'All Values' => 'All Values',
            'Search...' => 'Search...',
            'Expand All' => 'Expand All',
            'Collapse All' => 'Collapse All',
            'Auto Refresh' => 'Auto Refresh',
            'Off' => 'Off',
            'seconds' => 'seconds',
            'Custom time range selection coming soon' => 'Custom time range selection coming soon',
            'Failed to load data' => 'Failed to load data',
            'Zoom In' => 'Zoom In',
            'Close' => 'Close',
            'Items' => 'Items',
            'All Items' => 'All Items',
            'selected' => 'selected',
            'Select All' => 'Select All',
            'Deselect All' => 'Deselect All',
            'Quick Select' => 'Quick Select',
            'Custom Range' => 'Custom Range',
            'Cancel' => 'Cancel'
        ]
    ];
    
    /**
     * Detect current language (aligned with Zabbix core logic)
     * Priority:
     * 1) User language (users.lang); if 'default' then inherit system default
     * 2) System default language (settings.default_lang); on failure fall back to Zabbix default
     * 3) Zabbix default language (en_US)
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }
        
        // 1) User language
        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            // 'default' means inherit system default
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        // 2) System default language
        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        // 3) Zabbix default language
        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    /**
     * Try to get current user's language from Zabbix
     */
    private static function getUserLanguageFromZabbix() {
        // Method 0: Prefer official Zabbix wrapper CWebUser
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                // Static get method (newer versions)
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                // Old versions static data container
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore and try other methods
        }

        // Method 1: Try to get CWebUser info from global variable
        try {
            // Check CWebUser-related info in $GLOBALS
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (\Throwable $e) {
            // Continue to other methods
        }
        
        // Method 2: Try to get from global cache (installer / page init cache)
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
            // Continue to other methods
        }
        
        // Method 3: Get from session (Zabbix frontend sets this after login)
        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        // Method 4: Try to read user language directly from database
        return self::getUserLanguageFromDatabase();
    }
    
    /**
     * Get user language via API
     */
    private static function getUserLanguageByAPI($userid) {
        try {
            $apiClass = null;
            if (class_exists('API')) {
                $apiClass = 'API';
            } elseif (class_exists('\API')) {
                $apiClass = '\API';
            }
            
            if ($apiClass && method_exists($apiClass, 'User')) {
                $users = $apiClass::User()->get([
                    'output' => ['lang'],
                    'userids' => $userid,
                    'limit' => 1
                ]);
                
                if (!empty($users) && isset($users[0]['lang'])) {
                    return $users[0]['lang'];
                }
            }
        } catch (\Throwable $e) {
            // API not available or error
        }
        
        return null;
    }
    
    /**
     * Try to read current user's language directly from database
     */
    private static function getUserLanguageFromDatabase() {
        try {
            // Get current user ID
            
            $userid = null;
            
            // Get user ID from session
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }
            
            if (!$userid) {
                return null;
            }
            
            // Try to connect database (requires Zabbix DB config)
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
                $stmt->execute([$userid]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['lang'])) {
                    return $result['lang'];
                }
            }
        } catch (\Throwable $e) {
            // DB connection failed or other errors
        }
        
        return null;
    }

    /**
     * Read system default language (settings.default_lang or config.default_lang)
     */
    private static function getSystemDefaultLanguage() {
        try {
            // Method 0: Prefer official Zabbix helper CSettingsHelper
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                if (method_exists('CSettingsHelper', 'get')) {
                    $val = \CSettingsHelper::get('default_lang');
                    if (!empty($val)) {
                        return $val;
                    }
                }
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                // First check settings table (used in Zabbix 6/7)
                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }

                // Backward compatible: config table
                $stmt2 = $pdo->query("SHOW TABLES LIKE 'config'");
                $hasConfig = $stmt2 && $stmt2->fetch();
                if ($hasConfig) {
                    $stmt3 = $pdo->query("SELECT default_lang FROM config LIMIT 1");
                    $row2 = $stmt3 ? $stmt3->fetch(\PDO::FETCH_ASSOC) : false;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore and fall back
        }

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Map Zabbix language code to our language code
     */
    private static function mapZabbixLangToOurs($zabbixLang) {
        // Normalize to lowercase to handle inconsistent casing
        $lowerLang = strtolower(trim($zabbixLang));
        
        $langMap = [
            // English variants
            'en_us' => 'en_US',
            'en-us' => 'en_US',
            'en_gb' => 'en_US',
            'en-gb' => 'en_US',
            'en' => 'en_US',
            'english' => 'en_US',
            'us' => 'en_US',
            'gb' => 'en_US',
            
            // Default
            'default' => self::LANG_DEFAULT
        ];
        
        // Direct mapping
        if (isset($langMap[$lowerLang])) {
            return $langMap[$lowerLang];
        }
        
        if (strpos($lowerLang, 'en') === 0 || strpos($lowerLang, 'english') !== false) {
            return 'en_US';
        }
        
        // Use English by default
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Ensure language is supported or fall back
     */
    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }
        // Only en_US is supported
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Only treat languages we have translations for as available
     */
    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }
    
    /**
     * Get translation text
     */
    public static function t($key) {
        $lang = self::detectLanguage();
        
        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }
        
        // If current language has no translation, fall back to English
        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }
        
        // If everything fails, return the original key
        return $key;
    }
    
    /**
     * Get formatted translation text with parameters
     */
    public static function tf($key, ...$args) {
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }
    
    /**
     * Get current language code
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }
    
    /**
     * Reset language cache (mainly for testing)
     */
    public static function resetLanguage() {
        self::$currentLanguage = null;
    }
    
    /**
     * Check if current language is Chinese
     */
    public static function isChinese() {
        return false;
    }
}
