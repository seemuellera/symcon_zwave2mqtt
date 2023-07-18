<?php

declare(strict_types=1);

trait Zwave2MQTTHelper
{
    public function RequestAction($Ident, $Value)
    {
        $variableID = $this->GetIDForIdent($Ident);
        $variableType = IPS_GetVariable($variableID)['VariableType'];

        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';

        switch ($Ident) {
            case 'ZWAVE2M_Intensity':
                if ($Value == 100) {
                    $Value = 99;
                }
                $Payload['value'] = $Value;
                $topic = $baseTopic . '38/1/targetValue';
                break;

            case 'ZWAVE2M_IntensityOnOff':
                if ($Value) {
                    $Payload['value'] = true;
                    $topic = $baseTopic . '38/1/restorePrevious';
                }
                else {
                    $Payload['value'] = 0;
                    $topic = $baseTopic . '38/1/targetValue';
                }
                break;

            case 'ZWAVE2M_Switch':
                $Payload['value'] = $Value;
                $topic = $baseTopic . '37/0/targetValue';
                break;

            case 'ZWAVE2M_Color':
                $Payload['value'] = $this->IntToHex($Value);
                $topic = $baseTopic . '51/0/hexColor';
                break;

            case 'ZWAVE2M_LockRF':
                $topic = $baseTopic . '117/0/rf';
                if ($Value) {
                    $Payload['value'] = 1;    
                }
                else {
                    $Payload['value'] = 0;
                }
                break;

            case 'ZWAVE2M_LockLocal':
                $topic = $baseTopic . '117/0/local';
                if ($Value) {
                    $Payload['value'] = 2;    
                }
                else {
                    $Payload['value'] = 0;
                }
                break;
            
            default:
                $this->SendDebug('Request Action', 'No Action defined: ' . $Ident, 0);
                return false;
        }

        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->ZWAVE2M_Set($topic, $PayloadJSON);
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

    public function setColorExt($color, string $mode, array $params = [], string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                $this->SendDebug(__FUNCTION__, $color, 0);
                $this->SendDebug(__FUNCTION__, $mode, 0);
                $this->SendDebug(__FUNCTION__, json_encode($params, JSON_UNESCAPED_SLASHES), 0);
                $this->SendDebug(__FUNCTION__, $Z2MMode, 0);
                if (preg_match('/^#[a-f0-9]{6}$/i', strval($color))) {
                    $color = ltrim($color, '#');
                    $color = hexdec($color);
                }
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToCIE($RGB[0], $RGB[1], $RGB[2]);
                if ($Z2MMode = 'color') {
                    $Payload['color'] = $cie;
                } elseif ($Z2MMode == 'color_rgb') {
                    $Payload['color_rgb'] = $cie;
                } else {
                    return;
                }

                foreach ($params as $key => $value) {
                    $Payload[$key] = $value;
                }

                $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
                $this->SendDebug(__FUNCTION__, $PayloadJSON, 0);
                $this->Z2MSet($PayloadJSON);
                break;
            default:
                $this->SendDebug('setColor', 'Invalid Mode ' . $mode, 0);
                break;
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

    private function OnOff(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'ON';
                break;
            case false:
                $state = 'OFF';
                break;
        }
        return $state;
    }

    private function ValveState(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'OPEN';
                break;
            case false:
                $state = 'CLOSED';
                break;
        }
        return $state;
    }

    private function LockUnlock(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'LOCK';
                break;
            case false:
                $state = 'UNLOCK';
                break;
        }
        return $state;
    }

    private function OpenClose(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'OPEN';
                break;
            case false:
                $state = 'CLOSE';
                break;
        }
        return $state;
    }

    private function AutoManual(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'AUTO';
                break;
            case false:
                $state = 'MANUAL';
                break;
        }
        return $state;
    }

}
