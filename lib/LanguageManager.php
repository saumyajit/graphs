<?php

namespace Modules\ZabbixGraphTrees\Lib;

class LanguageManager {
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
     * Detect current language (simplified)
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }

        // Only English is supported now
        self::$currentLanguage = self::ZBX_DEFAULT_LANG;
        return self::$currentLanguage;
    }

    /**
     * Get translated text
     */
    public static function t($key) {
        $lang = self::detectLanguage();

        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        return $key;
    }

    /**
     * Get formatted translation
     */
    public static function tf($key, ...$args) {
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }

    /**
     * Get current language
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    /**
     * Reset language cache
     */
    public static function resetLanguage() {
        self::$currentLanguage = null;
    }
}