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

        $this->SetBuffer('Devices', '{}');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Setze Filter für ReceiveData
        $topic = $this->ReadPropertyString('MQTTBaseTopic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
        $this->getDevices();
        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        //$this->getDevices();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Devices
        $Devices = json_decode($this->GetBuffer('Devices'), true);
        $this->SendDebug('Buffer Devices', json_encode($Devices), 0);
        $ValuesDevices = [];

        foreach ($Devices as $device) {
            $instanceID = $this->getDeviceInstanceID($device['friendly_name']);
            $Value['name'] = $device['friendly_name'];
            $Value['node_id'] = $device['nodeId'];
            $Value['type'] = $device['type'];
            $Value['vendor'] = $device['vendor'];
            $Value['modelID'] = (array_key_exists('modelID', $device) == true ? $device['modelID'] : $this->Translate('Unknown'));
            $Value['description'] = $device['description'];
            $Value['power_source'] = (array_key_exists('powerSource', $device) == true ? $this->Translate($device['powerSource']) : $this->Translate('Unknown'));

            $Value['instanceID'] = $instanceID;

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

        return json_encode($Form);
    }

    private function getDeviceInstanceID($FriendlyName)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{8CFB9B1E-AB3D-417C-83EB-53C39BC59572}');
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'MQTTTopic') == $FriendlyName) {
                return $id;
            }
        }
        return 0;
    }

    public function ReceiveData($JSONString)
    {
        $Buffer = json_decode($JSONString, true);
        if (array_key_exists('Topic', $Buffer)) {

            if ($Buffer ['Topic'] == $this->ReadPropertyString('MQTTBaseTopic') . '/_CLIENTS/ZWAVE-GATEWAY-zwave-js-ui/api/getNodes') {
                $this->SendDebug('TOPIC', $Buffer['Topic'], 0);
            }
            else {

                return;
            }

            if(array_key_exists('Payload', $Buffer)) {

                $this->SendDebug('Payload', 'Received Payload with size ' . strlen($Buffer['Payload']), 0);
            }
            else{
               
                $this->SendDebug('Payload', 'No Payload found', 0);
                return;
            }

            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer['Payload'] = utf8_decode($Buffer['Payload']);
            }

            $this->SendDebug('Payload', $Buffer['Payload'], 0);
            $Payload = json_decode($Buffer['Payload'], true);

            if(array_key_exists('success', $Payload)) {

                $this->SendDebug('Payload', 'success indicator found', 0);
            }
            else{
               
                $this->SendDebug('Payload', 'success indicator NOT found', 0);
                return;
            }
            
            if ($Payload['success'] == true) {

                $this->SendDebug('ZWAPI', 'OK', 0);
            }
            else {

                $this->SendDebug('ZWAPI', 'NOT OK', 0);
            }

            $devices = $Payload['result'];
            $this->SendDebug('RESULT', 'Number of devices found: ' . count($devices), 0);

            $deviceDetails = Array();

            foreach ($devices as $currentDevice) {

                if (array_key_exists('name', $currentDevice)) {

                    if ($currentDevice['name']) {

                        $currentDeviceDetails['friendly_name'] = $currentDevice['name'];
                    }
                    else {

                        $currentDeviceDetails['friendly_name'] = 'nodeID_' . $currentDevice['id'];
                    }
                }
                else {
                
                    $currentDeviceDetails['friendly_name'] = 'nodeID_' . $currentDevice['id'];
                }
                $currentDeviceDetails['nodeId'] = $currentDevice['id'];
                $currentDeviceDetails['type'] = 'Unknown Type';
                $currentDeviceDetails['vendor'] = $currentDevice['manufacturer'];
                $currentDeviceDetails['modelID'] = $currentDevice['productLabel'];
                $currentDeviceDetails['description'] = $currentDevice['productDescription'];
                $currentDeviceDetails['powerSource'] = 'Unknown';
                if ($currentDevice['productType'] == 1) {

                    $currentDeviceDetails['powerSource'] = 'Mains';
                }
                if ($currentDevice['productType'] == 2) {

                    $currentDeviceDetails['powerSource'] = 'Battery';
                }
                
                array_push($deviceDetails, $currentDeviceDetails);
            }

            $this->SetBuffer('Devices', json_encode($deviceDetails));
        }
    }

    protected function getDevices()
    {
        $param = '{ "arg": [] }';

        $this->Command('_CLIENTS/ZWAVE-GATEWAY-zwave-js-ui/api/getNodes/set', $param);
    }

}