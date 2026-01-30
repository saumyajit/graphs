<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

/**
 * Graph Trees controller
 * Builds host-group tree, tag filters, and item lists
 * Compatible with Zabbix 6.x and 7.x
 */
class GraphTrees extends CController {

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
            'hostid' => 'int32',
            'tag' => 'string',
            'tag_value' => 'string',
            'time_from' => 'int32',
            'time_to' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(
                new CControllerResponseData([
                    'error' => _('Invalid input parameters.')
                ])
            );
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        // Allow access for any authenticated Zabbix user
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $hostid = $this->getInput('hostid', 0);
        $tag = $this->getInput('tag', '');
        $tagValue = $this->getInput('tag_value', '');
        $timeFrom = $this->getInput('time_from', time() - 3600); // Default: last 1 hour
        $timeTo = $this->getInput('time_to', time());

        // Get all host groups that contain hosts
		$hostGroups = [];
		try {
			$hostGroups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'with_hosts' => true,
				'sortfield' => 'name',
				'sortorder' => 'ASC'
			]);
		
			// Filter groups that start with CUSTOMER/ or PRODUCT/
			// $allowedPrefixes = ['CUSTOMER/', 'PRODUCT/'];
			$allowedPrefixes = ['CUSTOMER/'];
		
			$hostGroups = array_filter($hostGroups, function($group) use ($allowedPrefixes) {
				foreach ($allowedPrefixes as $prefix) {
					if (strpos($group['name'], $prefix) === 0) {
						return true;
					}
				}
				return false;
			});
		
			// Make sure indexes are numeric for foreach
			$hostGroups = array_values($hostGroups);
		
		} catch (\Exception $e) {
			error_log("GraphTrees: Failed to get host groups - " . $e->getMessage());
		}

        // Build host group → host tree structure
        $treeData = [];
        foreach ($hostGroups as $group) {
            try {
                $hosts = API::Host()->get([
                    'output' => ['hostid', 'host', 'name', 'status'],
                    'groupids' => [$group['groupid']],
                    'filter' => ['status' => HOST_STATUS_MONITORED],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);

                if (!empty($hosts)) {
                    $treeData[] = [
                        'groupid' => $group['groupid'],
                        'groupname' => $group['name'],
                        'hosts' => $hosts
                    ];
                }
            } catch (\Exception $e) {
                error_log(
                    "GraphTrees: Failed to get hosts for group {$group['groupid']} - " .
                    $e->getMessage()
                );
            }
        }

        // Collect all available tags from hosts and items (for filtering)
        $availableTags = [];
        try {
            $tagMap = [];

            // Get tags from hosts
            $hostsWithTags = API::Host()->get([
                'output' => ['hostid'],
                'selectTags' => ['tag', 'value'],
                'filter' => ['status' => HOST_STATUS_MONITORED]
            ]);

            foreach ($hostsWithTags as $host) {
                if (!empty($host['tags']) && is_array($host['tags'])) {
                    foreach ($host['tags'] as $hostTag) {
                        $tagName = $hostTag['tag'];
                        $tagVal = $hostTag['value'] ?? '';

                        if (!isset($tagMap[$tagName])) {
                            $tagMap[$tagName] = [];
                        }
                        if ($tagVal !== '' && !in_array($tagVal, $tagMap[$tagName], true)) {
                            $tagMap[$tagName][] = $tagVal;
                        }
                    }
                }
            }

            // Get tags from items (no limit set, may be time-consuming)
            $itemsWithTags = API::Item()->get([
                'output' => ['itemid'],
                'selectTags' => ['tag', 'value'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'monitored' => true
            ]);

            foreach ($itemsWithTags as $item) {
                if (!empty($item['tags']) && is_array($item['tags'])) {
                    foreach ($item['tags'] as $itemTag) {
                        $tagName = $itemTag['tag'];
                        $tagVal = $itemTag['value'] ?? '';

                        if (!isset($tagMap[$tagName])) {
                            $tagMap[$tagName] = [];
                        }
                        if ($tagVal !== '' && !in_array($tagVal, $tagMap[$tagName], true)) {
                            $tagMap[$tagName][] = $tagVal;
                        }
                    }
                }
            }

            // Sort by tag name
            ksort($tagMap);
            foreach ($tagMap as $tagName => $values) {
                sort($values);
                $availableTags[] = [
                    'tag' => $tagName,
                    'values' => $values
                ];
            }
        } catch (\Exception $e) {
            error_log("GraphTrees: Failed to get tags - " . $e->getMessage());
        }

        // If a host is selected, load its monitoring items
        $items = [];
        $selectedHost = null;
        if ($hostid > 0) {
            try {
                $hosts = API::Host()->get([
                    'output' => ['hostid', 'host', 'name'],
                    'hostids' => [$hostid],
                    'limit' => 1
                ]);

                if (!empty($hosts)) {
                    $selectedHost = $hosts[0];

                    // Get all monitoring items (with tag information)
                    $itemParams = [
                        'output' => ['itemid', 'name', 'key_', 'value_type', 'units'],
                        'hostids' => [$hostid],
                        'filter' => ['status' => ITEM_STATUS_ACTIVE],
                        'selectTags' => ['tag', 'value'],
                        'sortfield' => 'name',
                        'sortorder' => 'ASC'
                    ];

                    $allItems = API::Item()->get($itemParams);

                    // If a tag filter is specified, manually filter items in PHP
                    if (!empty($tag)) {
                        $filteredItems = [];
                        foreach ($allItems as $item) {
                            $matched = false;
                            if (!empty($item['tags']) && is_array($item['tags'])) {
                                foreach ($item['tags'] as $itemTag) {
                                    if ($itemTag['tag'] === $tag) {
                                        if (empty($tagValue) || $itemTag['value'] === $tagValue) {
                                            $matched = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            if ($matched) {
                                $filteredItems[] = $item;
                            }
                        }
                        $allItems = $filteredItems;
                    }

                    // Limit the number of returned items
                    $items = array_slice($allItems, 0, 100);
                }
            } catch (\Exception $e) {
                error_log(
                    "GraphTrees: Failed to get items for host {$hostid} - " .
                    $e->getMessage()
                );
            }
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Graph Trees'),
            'tree_data' => $treeData,
            'available_tags' => $availableTags,
            'selected_hostid' => $hostid,
            'selected_host' => $selectedHost,
            'selected_tag' => $tag,
            'selected_tag_value' => $tagValue,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'items' => $items,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese()
        ]);

        $response->setTitle(LanguageManager::t('Graph Trees'));
        $this->setResponse($response);
    }
}
