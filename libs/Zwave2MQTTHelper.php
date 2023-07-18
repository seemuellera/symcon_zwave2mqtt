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

            switch ($topicConfiguration['extractor']) {

                case 'copyValue':
                    $this->extractorCopyValue('set', $topicConfiguration['topic'], $Value);
                    break;
                case 'divideBy1000':
                    $this->extractorDivideBy1000('set', $topicConfiguration['topic'], $Value);
                    break;
                case 'intToBoolean':
                    $this->extractorIntToBoolean('set', $topicConfiguration['topic'], $Value);
                    break;
                case 'rgbColor':
                    $this->extractorRgbColor('set', $topicConfiguration['topic'], $Value);
                    break;
                case 'dimIntensity':
                    $configDummy = $this->getConfigItemForTopic($topicConfiguration['topic'] . 'Dummy');
                    $this->extractorDimIntensity('set', $Ident, $configDummy['ident'], $Value);
                    break;    

                default:
                    $this->LogMessage('Receive Data: No handler defined for extractor' . $topicConfiguration['extractor'], KL_ERROR);
                    return;
            }
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

            $Payload = json_decode($Buffer['Payload'], true);

            if (is_array($Payload)) {

                $allConfiguredTopics = $this->getConfigTopics();

                if (in_array($Buffer['Topic'], $allConfiguredTopics, $true)) {

                    $config = $this->getConfigItemForTopic($Buffer['Topic']);

                    if ($config) {

                        switch ($config['extractor']) {

                            case 'copyValue':
                                $this->extractorCopyValue('get', $config['ident'], $Payload);
                                break;
                            case 'divideBy1000':
                                $this->extractorDivideBy1000('get', $config['ident'], $Payload);
                                break;
                            case 'intToBoolean':
                                $this->extractorIntToBoolean('get', $config['ident'], $Payload);
                                break;
                            case 'rgbColor':
                                $this->extractorRgbColor('get', $config['ident'], $Payload);
                                break;
                            case 'dimIntensity':
                                $configDummy = $this->getConfigItemForTopic($Buffer['Topic'] . 'Dummy');
                                $this->extractorDimIntensity('get', $config['ident'], $configDummy['ident'], $Payload);
                                break;

                            default:
                                $this->LogMessage('Receive Data: No handler defined for extractor' . $config['extractor'], KL_ERROR);
                                return;
                        }
                    }
                    else {
                        $this->LogMessage('Receive Data: Unable to get config item for topic ' . $Buffer['Topic'], KL_ERROR);
                        return;
                    }
                }
            }
        }
    }

    protected function extractorCopyValue($mode, $ident, $payload) {

        if ($mode == 'get') {

            if (array_key_exists('value', $payload)) {
                
                $this->SetValue($ident, $payload['value']);
            }
            else {

                $this->LogMessage('Extrator CopyValue: No value found in payload for ident ' . $ident, KL_ERROR);
            }
        }
        if ($mode == 'set') {

            $payloadArray['value'] = $payload;
            $payloadJSON = json_encode($payloadArray, JSON_UNESCAPED_SLASHES);
            $this->ZWAVE2M_Set($ident, $PayloadJSON);
        }
    }

    protected function extractorDivideBy1000($mode, $ident, $payload) {

        if ($mode == 'get') {

            if (array_key_exists('value', $payload)) {
                
                $this->SetValue($ident, ($payload['value']/1000));
            }
            else {

                $this->LogMessage('Extrator DivideBy1000: No value found in payload for ident ' . $ident, KL_ERROR);
            }
        }
        if ($mode == 'set') {

            $payloadArray['value'] = $payload * 1000;
            $payloadJSON = json_encode($payloadArray, JSON_UNESCAPED_SLASHES);
            $this->ZWAVE2M_Set($ident, $PayloadJSON);
        }
    }

    protected function extractorIntToBoolean($mode, $ident, $payload) {

        if ($mode == 'get') {
            
            if (array_key_exists('value', $payload)) {
                if ($payload['value'] == 0) {
                    $this->SetValue($ident, false);    
                }
                if ($payload['value'] == 1) {
                    $this->SetValue($ident, true);  
                }
            } 
            else {
                $this->LogMessage('Extrator IntToBoolean: No value found in payload for ident ' . $ident, KL_ERROR);
            }
        }
        if ($mode == 'set') {

            if ($payload) {
                $payloadArray['value'] = 1;    
            }
            else {
                $payloadArray['value'] = 0;
            }
            $payloadJSON = json_encode($payloadArray, JSON_UNESCAPED_SLASHES);
            $this->ZWAVE2M_Set($ident, $PayloadJSON);
        }
    }

    protected function extractorRgbColor($mode, $ident, $payload) {

        if ($mode == 'get') {
            
            if (array_key_exists('value', $payload)) {
                
                $this->SetValue($ident, $this->HexToInt($payload['value']));
            } 
            else {
                $this->LogMessage('Extrator IntToBoolean: No value found in payload for ident ' . $ident, KL_ERROR);
            }
        }
        if ($mode == 'set') {

            $payloadArray['value'] = $this->IntToHex($payload);
            $payloadJSON = json_encode($payloadArray, JSON_UNESCAPED_SLASHES);
            $this->ZWAVE2M_Set($ident, $PayloadJSON);
        }
    }

    protected function extractorDimIntensity($mode, $identIntensity, $identSwitch, $payload) {

        if ($mode == 'get') {
            
            if (array_key_exists('value', $payload)) {
                
                $this->SetValue($identIntensity, ($payload['value']+1));

                if ($payload['value'] == 0) {

                    $this->SetValue($identSwitch, false);
                }
                else {

                    $this->SetValue($identSwitch, true);
                }
            } 
            else {
                $this->LogMessage('Extrator IntToBoolean: No value found in payload for ident ' . $ident, KL_ERROR);
            }
        }
    }

    public function Z2MSet($topic, $payload)
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

    private function setColor(int $color, string $mode, string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToCIE($RGB[0], $RGB[1], $RGB[2]);
                if ($Z2MMode = 'color') {
                    $Payload['color'] = $cie;
                } elseif ($Z2MMode == 'color_rgb') {
                    $Payload['color_rgb'] = $cie;
                } else {
                    return;
                }

                $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
                $this->Z2MSet($PayloadJSON);
                break;
            default:
                $this->SendDebug('setColor', 'Invalid Mode ' . $mode, 0);
                break;
        }
    }

}
