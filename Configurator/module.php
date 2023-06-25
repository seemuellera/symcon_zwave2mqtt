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

            $this->SendDebug('TOPIC', $Buffer['Topic'], 0);

            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer['Payload'] = utf8_decode($Buffer['Payload']);
            }
            // if (fnmatch($this->ReadPropertyString('MQTTBaseTopic'). "/_CLIENTS/ZWAVE-GATEWAY-zwave-js-ui/api/getNodes", $Buffer['Topic'])) {
            if ( $this->ReadPropertyString('MQTTBaseTopic'). "/_CLIENTS/ZWAVE-GATEWAY-zwave-js-ui/api/getNodes" == $Buffer['Topic']) {
                $this->SendDebug('BUFFER', $Buffer['Payload'], 0);
                $Payload = json_decode($Buffer['Payload'], true);
                file_put_contents('/tmp/zwave_mqtt.txt', $Payload);
                $this->SetBuffer('Devices', json_encode($Payload));
            }
        }
    }

    protected function getDevices()
    {
        $param = '{ "arg": [] }';

        $this->Command('_CLIENTS/ZWAVE-GATEWAY-zwave-js-ui/api/getNodes/set', $param);
    }

}