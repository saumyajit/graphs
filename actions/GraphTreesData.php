<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

/**
 * Graph Trees data controller
 * Returns historical item data for graph rendering
 * Compatible with Zabbix 6.x and 7.x
 */
class GraphTreesData extends CController {

    public function init(): void {
        // Zabbix 6.x / 7.x compatibility handling: disable request validation
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7.x
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6.x
        }
    }

    protected function checkInput(): bool {
        // Validate request parameters
        $fields = [
            'itemids' => 'string',
            'time_from' => 'int32',
            'time_to' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        return $ret;
    }

    protected function checkPermissions(): bool {
        // Allow access for any authenticated Zabbix user
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $itemidsJson = $this->getInput('itemids', '[]');
        $itemids = json_decode($itemidsJson, true);
        if (!is_array($itemids)) {
            $itemids = [];
        }

        $timeFrom = $this->getInput('time_from', time() - 3600); // Default: last 1 hour
        $timeTo = $this->getInput('time_to', time());

        $graphData = [];

        if (!empty($itemids)) {
            try {
                // Get item metadata
                $items = API::Item()->get([
                    'output' => ['itemid', 'name', 'key_', 'value_type', 'units'],
                    'itemids' => $itemids
                ]);

                foreach ($items as $item) {
                    // Get historical data (history type depends on item value_type)
                    $historyType = (int) $item['value_type'];

                    $history = API::History()->get([
                        'output' => 'extend',
                        'itemids' => [$item['itemid']],
                        'history' => $historyType,
                        'time_from' => $timeFrom,
                        'time_till' => $timeTo,
                        'sortfield' => 'clock',
                        'sortorder' => 'ASC',
                        'limit' => 10000
                    ]);

                    $dataPoints = [];
                    foreach ($history as $point) {
                        $dataPoints[] = [
                            'clock' => $point['clock'],
                            'value' => $point['value']
                        ];
                    }

                    $graphData[] = [
                        'itemid' => $item['itemid'],
                        'name' => $item['name'],
                        'units' => $item['units'],
                        'data' => $dataPoints
                    ];
                }
            } catch (\Exception $e) {
                error_log(
                    "GraphTreesData: Failed to get history data - " .
                    $e->getMessage()
                );
            }
        }

        // Output JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $graphData
        ]);
        exit;
    }
}