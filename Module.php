<?php

namespace Modules\ZabbixGraphTrees;

// Load version compatibility helpers
require_once __DIR__ . '/lib/ZabbixVersion.php';
use Modules\ZabbixGraphTrees\Lib\ZabbixVersion;
use Modules\ZabbixGraphTrees\Lib\LanguageManager;
use CMenu;
use CMenuItem;

// Choose the module base class depending on installed Zabbix version
// Zabbix 7.0+ Uses Zabbix\Core\CModule
// Zabbix 6.0 Uses Core\CModule
if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    // Fallback: define a no-op base class if CModule is unavailable
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {
	
	// Initialize module and register menu entries
    public function init(): void {
        $lm = new LanguageManager();
        
        // Register menu entry using APP component (Zabbix 6.x and 7.x compatible)
        try {
            // Use APP component API when available
            if (class_exists('APP')) {
                $app = class_exists('APP') ? new \ReflectionClass('APP') : null;
                
                if ($app && $app->hasMethod('Component')) {
                    // Zabbix 7.0+
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Monitoring'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem($lm->t('Graph Trees')))
                                ->setAction('graphtrees')
                        );
                }
            }
        } catch (\Exception $e) {
            // Log menu registration errors without interrupting module loading
            error_log('Graph Trees Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}