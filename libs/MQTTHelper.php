<?php

declare(strict_types=1);

if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string)
    {
        return preg_match('#^' . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . '$#i', $string);
    }
}

trait MQTTHelper
{
    public function Command(string $topic, string $value)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;    
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $topic;
        $Data['Payload'] = $value;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }

    private function fetchRetainedData($topic) {

        $allMqttServers = IPS_GetInstanceListByModuleID('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $mqttInstance = $allMqttServers[0];
        $retainedData = MQTT_GetRetainedMessage($mqttInstance, $topic);

        $this->LogMessage(json_encode($retainedData),KL_ERROR);

        if (array_key_exists('Payload', $retainedData)) {

            if (IPS_GetKernelDate() > 1670886000) {
                $retainedData['Payload'] = utf8_decode($retainedData['Payload']);
            }

            $Payload = json_decode($retainedData['Payload'], true);
            return $Payload;
        }
        else {

            return false;
        }
    }
}