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
        $this->getDevices();
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
                    'moduleID'      => '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}',
                    'configuration' => [
                        'MQTTTopic'    => $device['friendly_name']
                    ]
                ];
            array_push($ValuesDevices, $Value);
        }
        $Form['actions'][0]['items'][0]['values'] = $ValuesDevices;

        return json_encode($Form);
    }

    protected function getDeviceInstanceID($gaga) {

        return 12345;
    }

    public function ReceiveData($JSONString)
    {
        $Buffer = json_decode($JSONString, true);
        if (array_key_exists('Topic', $Buffer)) {

            if ($Buffer ['Topic'] == 'zwave/_CLIENTS/ZWAVE-GATEWAY-zwave-js-ui/api/getNodes') {
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

                $currentDeviceDetails['name'] = 'Unknown Device';
                $currentDeviceDetails['node_id'] = 666;
                $currentDeviceDetails['type'] = 'Unknown Type';
                $currentDeviceDetails['modelID'] = 'Unknown Model';
                $currentDeviceDetails['description'] = 'Dummy Entry';
                $currentDeviceDetails['power_source'] = 'Unknown';
                $currentDeviceDetails['instanceID'] = '12345';  

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