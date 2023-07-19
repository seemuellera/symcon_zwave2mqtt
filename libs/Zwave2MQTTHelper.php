<?php

declare(strict_types=1);

trait Zwave2MQTTHelper
{
    public function RequestAction($Ident, $Value)
    {
        $variableID = $this->GetIDForIdent($Ident);
        $variableType = IPS_GetVariable($variableID)['VariableType'];

        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';

        $topicConfiguration = $this->getConfigForIdent($Ident);

        if ($topicConfiguration) {

            $this->SetMqttValue($topicConfiguration['topic'], $topicConfiguration['transformation'], $Value);
        }
        else {

            $this->LogError("Receiving data for unconfigure ident: " . $ident, KL_ERROR);
        }
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString, true);

            $this->SendDebug('MQTT Topic', $Buffer['Topic'], 0);
            $this->SendDebug('MQTT Payload', $Buffer['Payload'], 0);

            $this->SendDebug('Read data',$JSONString,0);

            $Payload = json_decode($Buffer['Payload'], true);

            if (is_array($Payload)) {

                $allConfiguredTopics = $this->getConfigTopics();

                if (in_array($Buffer['Topic'], $allConfiguredTopics)) {

                    $config = $this->getConfigItemForTopic($Buffer['Topic']);

                    if ($config) {

                        $this->SetVariableContentFromPayload($config['ident'], $config['transformation'], $Payload['value']);

                    }
                    else {
                        $this->LogMessage('Receive Data: Unable to get config item for topic ' . $Buffer['Topic'], KL_ERROR);
                        return;
                    }
                }
            }
        }
    }

    protected function SetVariableContent($ident, $transformation, $value) {

        switch($transformation) {
            
            case "copyValue":
                $this->SetValue($ident, $value);
                break;

            case "divideBy1000":
                $this->SetValue($ident, ($value/1000));
                break;

            case "intToBoolean":
                if ($value == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($value > 0) {
                    $this->SetValue($ident, true);  
                }
                break;
            
            case "rgbColor":
                $this->SetValue($ident, $this->HexToInt($value));
                break;

            case "dimIntensity":
                if ($value >= 99) {
                    $this->SetValue($ident, 100);
                }
                else {
                    $this->SetValue($ident, $value);
                }
                break;
            
            case "protectionRemote":
                if ($value == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($value == 1) {
                    $this->SetValue($ident, true);  
                }
                break;

            case "protectionLocal":
                if ($value == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($value == 2) {
                    $this->SetValue($ident, true);  
                }
                break;
        }
    }

    protected function SetMqttValue($topic, $transformation, $value) {

        switch($transformation) {
            
            case "copyValue":
                $this->Z2MSet($topic, Array('value' => $value));
                break;

            case "divideBy1000":
                $this->Z2MSet($topic, Array('value' => ($value * 1000)));
                break;

            case "intToBoolean":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => 1));    
                }
                else {
                    $this->Z2MSet($topic, Array('value' => 0));  
                }
                break;
            
            case "rgbColor":
                $this->Z2MSet($topic, Array('value' => $this->IntToHex($value)));
                break;

            case "dimIntensity":
                if ($value >= 100) {
                    $this->Z2MSet($topic, Array('value' => 99));  
                }
                else {
                    $this->Z2MSet($topic, Array('value' => $value));  
                }
                break;

            case "protectionRemote":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => 1));    
                }
                else {
                    $this->Z2MSet($topic, Array('value' => 0));  
                }
                break;

            case "protectionLocal":
                if ($value) {
                    $this->Z2MSet($topic, Array('value' => 1));    
                }
                else {
                    $this->Z2MSet($topic, Array('value' => 2));  
                }
                break;
            
        }
    }

    public function Z2MSet($topic, $payload)
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic . '/set';
        $Data['Payload'] = $payloadJson;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }

    public function ZWAVE2M_Set($topic, $payload)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $topic . '/set';
        $Data['Payload'] = $payload;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }

    protected function SetValue($Ident, $Value)
    {
        if (@$this->GetIDForIdent($Ident)) {
            $this->SendDebug('Info :: SetValue for ' . $Ident, 'Value: ' . $Value, 0);
            parent::SetValue($Ident, $Value);
        } else {
            $this->SendDebug('Error :: No Expose for Value', 'Ident: ' . $Ident, 0);
        }
    }
}
