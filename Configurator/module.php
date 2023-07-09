<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/MQTTHelper.php';

class Zwave2MQTTConfigurator extends IPSModule
{
    use MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zwave');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Devices
        $Devices = $this->getDeviceInfo();
        $this->SendDebug('Devices', json_encode($Devices), 0);
        $ValuesDevices = [];

        if (is_array($Devices)) {
        
            foreach ($Devices as $device) {
                $Value['name'] = $device['friendly_name'];
                $Value['node_id'] = $device['nodeId'];
                $Value['type'] = $device['type'];
                $Value['vendor'] = $device['vendor'];
                $Value['modelID'] = (array_key_exists('modelID', $device) == true ? $device['modelID'] : $this->Translate('Unknown'));
                $Value['description'] = $device['description'];
                $Value['power_source'] = (array_key_exists('powerSource', $device) == true ? $this->Translate($device['powerSource']) : $this->Translate('Unknown'));

                $Value['instanceID'] = $this->getDeviceInstanceID($device['friendly_name']);

                $Value['create'] =
                    [
                        'moduleID'      => '{27D3347F-8CC4-469B-866B-BE276BE6DA89}',
                        'configuration' => [
                            'MQTTTopic'    => $device['friendly_name'],
                            'MQTTBaseTopic' => $this->ReadPropertyString('MQTTBaseTopic')
                        ],
                        'location' => [
                            'Technik',
                            'Z-Wave'
                        ]
                    ];
                array_push($ValuesDevices, $Value);
            }

            $Form['actions'][0]['items'][0]['values'] = $ValuesDevices;
        }
        
        return json_encode($Form);
    }

    private function getDeviceInstanceID($FriendlyName)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{27D3347F-8CC4-469B-866B-BE276BE6DA89}');
        foreach ($InstanceIDs as $id) {

            if (IPS_GetProperty($id, 'MQTTTopic') == $FriendlyName) {
                return $id;
            }
        }
        return 0;
    }

    public function getDeviceInfo() {

        $allMqttServers = IPS_GetInstanceListByModuleID('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $mqttInstance = $allMqttServers[0];
        
        $allTopics = MQTT_GetRetainedMessageTopicList($mqttInstance);

        $regexDescriptions = '/^' . $this->ReadPropertyString('MQTTBaseTopic') . '\/.+\/nodeinfo/';

        $this->SendDebug('RETAINED MESSAGE TOPICS', "Found " . count($allTopics) . " retained messages topics",0);

        $allDeviceDesriptions = Array();
        foreach($allTopics as $currentTopic) {

            if (preg_match($regexDescriptions, $currentTopic)) {

                $this->SendDebug('NODE INFOS','Processing topic ' . $currentTopic,0);

                $currentDeviceDescription = $this->fetchRetainedData($currentTopic);

                if (! $currentDeviceDescription) {

                    $this->LogMessage('Unable to fetch details for node ' . $currentTopic, KL_ERROR);
                    return;
                }

                $currentNodeDetails = Array();

                if (array_key_exists('name', $currentDeviceDescription)) {

                    if ($currentDeviceDescription['name']) {

                        $currentNodeDetails['friendly_name'] = $currentDeviceDescription['name'];
                    }
                    else {

                        $currentNodeDetails['friendly_name'] = 'nodeID_' . $currentDeviceDescription['id'];
                    }
                }
                else {
                
                    $currentNodeDetails['friendly_name'] = 'nodeID_' . $currentDeviceDescription['id'];
                }
                $currentNodeDetails['nodeId'] = $currentDeviceDescription['id'];
                $currentNodeDetails['type'] = 'Unknown Type';
                $currentNodeDetails['vendor'] = $currentDeviceDescription['manufacturer'];
                $currentNodeDetails['modelID'] = $currentDeviceDescription['productLabel'];
                $currentNodeDetails['description'] = $currentDeviceDescription['productDescription'];
                $currentNodeDetails['powerSource'] = 'Unknown';
                if (array_key_exists('nodeType', $currentDeviceDescription)) {
                    if ($currentDeviceDescription['nodeType'] == 1) {

                        $currentNodeDetails['powerSource'] = 'Mains';
                    }
                    if ($currentDeviceDescription['nodeType'] == 2) {

                        $currentNodeDetails['powerSource'] = 'Battery';
                    }
                }
                else {
                    $currentNodeDetails['powerSource'] = 'Unknown';
                }
                
                $allDeviceDesriptions[] = $currentNodeDetails;
            }
        }

        return $allDeviceDesriptions;
    }

}