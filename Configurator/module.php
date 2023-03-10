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
        $this->RegisterPropertyString('MQTTBaseTopic', 'zwave2mqtt');

        $this->SetBuffer('Devices', '{}');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Setze Filter für ReceiveData
        $topic = 'symcon/' . $this->ReadPropertyString('MQTTBaseTopic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
        $this->getDevices();
        $this->getGroups();
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

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        $Buffer = json_decode($JSONString, true);

        if (array_key_exists('Topic', $Buffer)) {
            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer['Payload'] = utf8_decode($Buffer['Payload']);
            }
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/devices', $Buffer['Topic'])) {
                $Payload = json_decode($Buffer['Payload'], true);
                $this->SetBuffer('Devices', json_encode($Payload));
            }
        }
    }

    private function getDevices()
    {
        $this->symconExtensionCommand('getDevices', '');
    }

    private function getGroups()
    {
        $this->symconExtensionCommand('getGroups', '');
    }

    private function getDeviceInstanceID($FriendlyName)
    {
        // HACK:
        return 12345;

        $InstanceIDs = IPS_GetInstanceListByModuleID('{ADD-GUID-OF-DEVICEMODULE}');
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'MQTTTopic') == $FriendlyName) {
                return $id;
            }
        }
        return 0;
    }

}