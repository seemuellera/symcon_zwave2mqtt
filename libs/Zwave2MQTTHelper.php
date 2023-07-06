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
    
    public function getDeviceInfo()
    {

        $allMqttServers = IPS_GetInstanceListByModuleID('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $mqttInstance = $allMqttServers[0];
        // $this->SendDebug('Parent Instance', $mqttInstance, 0);
        $allTopics = MQTT_GetRetainedMessageTopicList($mqttInstance);
        
        $deviceTopics = Array();
        foreach ($allTopics as $currentTopic) {

            $filterRegex = '/^' . $this->ReadPropertyString('MQTTBaseTopic') . '\/' . $this->ReadPropertyString('MQTTTopic') . '\/*/';
            if (preg_match($filterRegex, $currentTopic) ) {
            
                $deviceTopics[] = $currentTopic;
            }
        }

        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';
        foreach ($deviceTopics as $currentDeviceTopic) {

            switch($currentDeviceTopic) {

                case $baseTopic . 'lastActive':
                    $this->RegisterVariableInteger('ZWAVE2M_LastActive', $this->Translate('Last Activity'), '~UnixTimestamp');
                    $data = $this->fetchRetainedData($baseTopic . 'lastActive');
                    if (array_key_exists('value',$data)) {
                        $this->SetValue('ZWAVE2M_LastActive', $data['value']);
                    }
                    break;

                case $baseTopic . 'status':
                    $this->RegisterVariableBoolean('ZWAVE2M_DeviceStatus', $this->Translate('Device Health'), '~Alert.Reversed');
                    $data = $this->fetchRetainedData($baseTopic . 'status');
                    if (array_key_exists('value',$data)) {
                        $this->SetValue('ZWAVE2M_DeviceStatus', $data['value']);
                    }
                    break;

                case $baseTopic . '38/1/currentValue':
                    $this->RegisterVariableInteger('ZWAVE2M_Intensity', $this->Translate('Intensity'), '~Intensity.100');
                    $this->EnableAction('ZWAVE2M_Intensity');
                    $this->RegisterVariableBoolean('ZWAVE2M_IntensityOnOff', $this->Translate('Status'), '~Switch');
                    $this->EnableAction('ZWAVE2M_IntensityOnOff');
                    $data = $this->fetchRetainedData($baseTopic . '38/1/currentValue');
                    if (array_key_exists('value',$data)) {
                        $this->SetValue('ZWAVE2M_Intensity', $data['value']);
                        if ($data['value'] == 0) {
                            $this->SetValue('ZWAVE2M_IntensityOnOff', false);
                        }
                        else {
                            $this->SetValue('ZWAVE2M_IntensityOnOff', true);
                        }
                    }
                    break;

                case $baseTopic . '37/0/currentValue':
                    $this->RegisterVariableBoolean('ZWAVE2M_Switch', $this->Translate('Status'), '~Switch');
                    $this->EnableAction('ZWAVE2M_Switch');
                    $data = $this->fetchRetainedData($baseTopic . '37/0/currentValue');
                    if (array_key_exists('value',$data)) {
                        $this->SetValue('ZWAVE2M_Switch', $data['value']);
                    }
                    break;

                case $baseTopic . '51/0/hexColor':
                    $this->RegisterVariableInteger('ZWAVE2M_Color', $this->Translate('Color RGB'), '~HexColor');
                    $this->EnableAction('ZWAVE2M_Color');
                    $data = $this->fetchRetainedData($baseTopic . '51/0/hexColor');
                    if (array_key_exists('value',$data)) {
                        $this->SetValue('ZWAVE2M_Color', $data['value']);
                    }
                    break;

                case $baseTopic . '117/0/rf':
                    $this->RegisterVariableBoolean('ZWAVE2M_LockRF', $this->Translate('Lock Remote Operations'), '~Lock');
                    $this->EnableAction('ZWAVE2M_LockRF');
                    $data = $this->fetchRetainedData($baseTopic . '117/0/rf');
                    if (array_key_exists('value',$data)) {
                        if ($data['value'] == 0) {
                            $this->SetValue('ZWAVE2M_LockRF', false);
                        }
                        else {
                            $this->SetValue('ZWAVE2M_LockRF', true);
                        }
                    }
                    break;

                case $baseTopic . '117/0/local':
                    $this->RegisterVariableBoolean('ZWAVE2M_LockLocal', $this->Translate('Lock Local Operations'), '~Lock');
                    $this->EnableAction('ZWAVE2M_LockLocal');
                    $data = $this->fetchRetainedData($baseTopic . '117/0/local');
                    if (array_key_exists('value',$data)) {
                        if ($data['value'] == 0) {
                            $this->SetValue('ZWAVE2M_LockLocal', false);
                        }
                        else {
                            $this->SetValue('ZWAVE2M_LockLocal', true);
                        }
                    }
                    break;

                case $baseTopic . '91/0/scene/001':
                    $this->RegisterVariableInteger('ZWAVE2M_SceneID1', $this->Translate('Scene ID 1'));
                    // no retained value has to be retrieved as the scene IDs only exist during the key presses
                    break;

                case $baseTopic . '91/0/scene/002':
                    $this->RegisterVariableInteger('ZWAVE2M_SceneID2', $this->Translate('Scene ID 2'));
                    // no retained value has to be retrieved as the scene IDs only exist during the key presses
                    break;
            }
            
        }
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString, true);

            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer['Payload'] = utf8_decode($Buffer['Payload']);
            }

            $this->SendDebug('MQTT Topic', $Buffer['Topic'], 0);
            $this->SendDebug('MQTT Payload', $Buffer['Payload'], 0);

            $Payload = json_decode($Buffer['Payload'], true);

            if (is_array($Payload)) {

                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/lastActive', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_LastActive', ($Payload['value'] / 1000));
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/status', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_DeviceStatus', $Payload['value']);
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/38/1/currentValue', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_Intensity', $Payload['value']);
                        if ($Payload['value'] == 0) {
                        
                            $this->SetValue('ZWAVE2M_IntensityOnOff', false);
                        }
                        else {

                            $this->SetValue('ZWAVE2M_IntensityOnOff', true);
                        }
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/37/0/currentValue', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_Switch', $Payload['value']);
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/51/0/hexColor', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_Color', $this->HexToInt($Payload['value']));
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/117/0/rf', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        if ($Payload['value'] == 0) {
                            $this->SetValue('ZWAVE2M_LockRF', false);    
                        }
                        if ($Payload['value'] == 1) {
                            $this->SetValue('ZWAVE2M_LockRF', true);    
                        }
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/117/0/local', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        if ($Payload['value'] == 0) {
                            $this->SetValue('ZWAVE2M_LockLocal', false);    
                        }
                        if ($Payload['value'] == 2) {
                            $this->SetValue('ZWAVE2M_LockLocal', true);    
                        }
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/91/0/scene/001', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_SceneID1', $Payload['value']);
                    }
                }
                if (fnmatch($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/91/0/scene/002', $Buffer['Topic'])) {
                
                    if (array_key_exists('value', $Payload)) {
                        $this->SetValue('ZWAVE2M_SceneID2', $Payload['value']);
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

    protected function createVariableProfiles()
    {
        /**
         * if (!IPS_VariableProfileExists('Z2M.Sensitivity')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Medium'), '', -1];
         * $Associations[] = [2, $this->Translate('Low'), '', -1];
         * $Associations[] = [3, $this->Translate('High'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.Sensitivity', '', '', '', $Associations);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.Intensity.254')) {
         * $this->RegisterProfileInteger('Z2M.Intensity.254', 'Intensity', '', '%', 0, 254, 1);
         * }
         */
        if (!IPS_VariableProfileExists('Z2M.RadarSensitivity')) {
            $this->RegisterProfileInteger('Z2M.RadarSensitivity', 'Intensity', '', '', 0, 10, 1);
        }

        /**
         * if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
         * $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
         * }
         */

        /**
         * if (!IPS_VariableProfileExists('Z2M.RadarScene')) {
         * $this->RegisterProfileStringEx('Z2M.RadarScene', 'Menu', '', '', [
         * ['default', $this->Translate('Default'), '', 0xFFFFFF],
         * ['area', $this->Translate('Area'), '', 0x0000FF],
         * ['toilet', $this->Translate('Toilet'), '', 0x0000FF],
         * ['bedroom', $this->Translate('Bedroom'), '', 0x0000FF],
         * ['parlour', $this->Translate('Parlour'), '', 0x0000FF],
         * ['office', $this->Translate('Office'), '', 0x0000FF],
         * ['hotel', $this->Translate('Hotel'), '', 0x0000FF]
         * ]);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.SystemMode')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Off'), '', -1];
         * $Associations[] = [2, $this->Translate('Auto'), '', -1];
         * $Associations[] = [3, $this->Translate('Heat'), '', -1];
         * $Associations[] = [4, $this->Translate('Cool'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.SystemMode', '', '', '', $Associations);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.PowerOutageMemory')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Off'), '', -1];
         * $Associations[] = [2, $this->Translate('On'), '', -1];
         * $Associations[] = [3, $this->Translate('Restore'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.PowerOutageMemory', '', '', '', $Associations);
         * }
         */

        /**
         * if (!IPS_VariableProfileExists('Z2M.ThermostatPreset')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Manual'), '', -1];
         * $Associations[] = [2, $this->Translate('Boost'), '', -1];
         * $Associations[] = [3, $this->Translate('Complexes Program'), '', -1];
         * $Associations[] = [4, $this->Translate('Comfort'), '', -1];
         * $Associations[] = [5, $this->Translate('Eco'), '', -1];
         * $Associations[] = [6, $this->Translate('Heat'), '', -1];
         * $Associations[] = [7, $this->Translate('Schedule'), '', -1];
         * $Associations[] = [8, $this->Translate('Away'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.ThermostatPreset', '', '', '', $Associations);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.ColorTemperature')) {
         * IPS_CreateVariableProfile('Z2M.ColorTemperature', 1);
         * }
         * IPS_SetVariableProfileDigits('Z2M.ColorTemperature', 0);
         * IPS_SetVariableProfileIcon('Z2M.ColorTemperature', 'Bulb');
         * IPS_SetVariableProfileText('Z2M.ColorTemperature', '', ' Mired');
         * IPS_SetVariableProfileValues('Z2M.ColorTemperature', 50, 500, 1);
         */

        /**
         * if (!IPS_VariableProfileExists('Z2M.ConsumerConnected')) {
         * $this->RegisterProfileBooleanEx('Z2M.ConsumerConnected', 'Plug', '', '', [
         * [false, $this->Translate('not connected'),  '', 0xFF0000],
         * [true, $this->Translate('connected'),  '', 0x00FF00]
         * ]);
         * }
         */
        if (!IPS_VariableProfileExists('Z2M.DeviceStatus')) {
            $this->RegisterProfileBooleanEx('Z2M.DeviceStatus', 'Network', '', '', [
                [false, 'Offline',  '', 0xFF0000],
                [true, 'Online',  '', 0x00FF00]
            ]);
        }
        if (!IPS_VariableProfileExists('Z2M.AutoLock')) {
            $this->RegisterProfileBooleanEx('Z2M.AutoLock', 'Key', '', '', [
                [false, $this->Translate('Manual'),  '', 0xFF0000],
                [true, $this->Translate('Auto'),  '', 0x00FF00]
            ]);
        }
        if (!IPS_VariableProfileExists('Z2M.ValveState')) {
            $this->RegisterProfileBooleanEx('Z2M.ValveState', 'Radiator', '', '', [
                [false, $this->Translate('Valve Closed'),  '', 0xFF0000],
                [true, $this->Translate('Valve Open'),  '', 0x00FF00]
            ]);
        }
        if (!IPS_VariableProfileExists('Z2M.WindowOpenInternal')) {
            $Associations = [];
            $Associations[] = [0, $this->Translate('Quarantine'), '', -1];
            $Associations[] = [1, $this->Translate('Windows are closed'), '', -1];
            $Associations[] = [2, $this->Translate('Hold'), '', -1];
            $Associations[] = [3, $this->Translate('Open window detected'), '', -1];
            $Associations[] = [4, $this->Translate('In window open state from external but detected closed locally'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.WindowOpenInternal', '', '', '', $Associations);
        }
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

    private function registerVariableProfile($expose)
    {
        $ProfileName = 'Z2M.' . $expose['name'];
        $tmpProfileName = '';

        switch ($expose['type']) {
            case 'binary':
                switch ($expose['property']) {
                    case 'consumer_connected':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileBooleanEx($ProfileName, 'Plug', '', '', [
                                [false, $this->Translate('not connected'),  '', 0xFF0000],
                                [true, $this->Translate('connected'),  '', 0x00FF00]
                            ]);
                        }
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                        break;
                }
                break;
            case 'enum':
                if (array_key_exists('values', $expose)) {
                    //Sortieren, damit der Hash auch dann passt, wenn die Values von Z2M in einer anderen Reihenfolge geliefert werden.
                    sort($expose['values']);
                    $tmpProfileName = implode('', $expose['values']);
                    $ProfileName .= '.';
                    $ProfileName .= dechex(crc32($tmpProfileName));
                    switch ($ProfileName) {
                        case 'Z2M.motor_direction.cf88002f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Shuffle', '', '', [
                                    ['back', $this->Translate('Back'), '', 0x00FF00],
                                    ['forward', $this->Translate('Forward'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.color_power_on_behavior.ae76ffdc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['initial', $this->Translate('Initial'), '', 0x00FF00],
                                    ['previous', $this->Translate('Medium'), '', 0x00FF00],
                                    ['cutomized', $this->Translate('Customized'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.displayed_temperature.f31d1694':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['target', $this->Translate('Target'), '', 0x00FF00],
                                    ['measured', $this->Translate('Medium'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.battery_state.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Battery', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0x00FF00],
                                    ['high', $this->Translate('High'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.temperature_unit.abf8ba6a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['celsius', $this->Translate('Celsius'), '', 0x00FF00],
                                    ['fahrenheit', $this->Translate('Fahrenheit'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.selftest.e0cc684':
                        case 'Z2M.selftest.784dd132':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['Test', $this->Translate('Test'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.mute_buzzer.6c8bdc62':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['Mute', $this->Translate('Mute'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.adaptation_run_control.e596b9f2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['initiate_adaptation', $this->Translate('Initiate Adaptation'), '', 0x00FF00],
                                    ['cancel_adaptation', $this->Translate('Cancel Adaptation'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.adaptation_run_status.cc98878f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['in_progress', $this->Translate('In Progress'), '', 0x00FF00],
                                    ['found', $this->Translate('Found'), '', 0x00FF00],
                                    ['lost', $this->Translate('Lost'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.day_of_week.87770221':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['sunday', $this->Translate('Sunday'), '', 0x00FF00],
                                    ['monday', $this->Translate('Monday'), '', 0x00FF00],
                                    ['tuesday', $this->Translate('Tuesday'), '', 0x00FF00],
                                    ['wednesday', $this->Translate('Wednesday'), '', 0x00FF00],
                                    ['thursday', $this->Translate('Thursday'), '', 0x00FF00],
                                    ['Friday', $this->Translate('Friday'), '', 0x00FF00],
                                    ['saturday', $this->Translate('Saturday'), '', 0x00FF00],
                                    ['away_or_vacation', $this->Translate('Away Or Vacation'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.setpoint_change_source.2b697f02':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x00FF00],
                                    ['externally', $this->Translate('Externally'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.programming_operation_mode.5dfa482f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['setpoint', $this->Translate('Setpoint'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x00FF00],
                                    ['eco', $this->Translate('Eco'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.keypad_lockout.84f3d9b9':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['unlock', $this->Translate('Unlock'), '', 0x00FF00],
                                    ['lock1', $this->Translate('Lock 1'), '', 0x00FF00],
                                    ['lock2', $this->Translate('Lock 2'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.buzzer.cd21c09a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['mute', $this->Translate('Mute'), '', 0x00FF00],
                                    ['alarm', $this->Translate('Alarm'), '', 0x00FF00]
                                ]);
                            }
                            break;
                            case 'Z2M.display_orientation.d6fc8316':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['normal', $this->Translate('Normal'), '', 0x00FF00],
                                    ['flipped', $this->Translate('Flipped'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.dc7fd161':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['press_1', $this->Translate('Press 1'), '', 0x00FF00],
                                    ['press_2', $this->Translate('Press 2'), '', 0x00FF00],
                                    ['press_3', $this->Translate('Press 3'), '', 0x00FF00],
                                    ['press_4', $this->Translate('Press 4'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.869d1272':
                        case 'Z2M.action.ec8cf04f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on_press', $this->Translate('On Press'), '', 0x00FF00],
                                    ['on_hold', $this->Translate('On Hold'), '', 0x00FF00],
                                    ['on_hold_release', $this->Translate('On Hold Release'), '', 0x00FF00],
                                    ['up_press', $this->Translate('Up Press'), '', 0x00FF00],
                                    ['up_hold', $this->Translate('Up Hold'), '', 0x00FF00],
                                    ['up_hold_release', $this->Translate('Up Hold Release'), '', 0x00FF00],
                                    ['down_press', $this->Translate('Down Press'), '', 0x00FF00],
                                    ['down_hold', $this->Translate('Down Hold'), '', 0x00FF00],
                                    ['down_hold_release', $this->Translate('Down Hold Release'), '', 0x00FF00],
                                    ['off_press', $this->Translate('Off Press'), '', 0x00FF00],
                                    ['off_hold', $this->Translate('Off Hold'), '', 0x00FF00],
                                    ['off_hold_release', $this->Translate('Off Hold Release'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.712e126b':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['dots_1_double_press', $this->Translate('Dots 1 Double Press'), '', 0x00FF00],
                                    ['dots_1_initial_press', $this->Translate('Dots 1 Initial Press'), '', 0x00FF00],
                                    ['dots_1_long_press', $this->Translate('Dots 1 Long Press'), '', 0x00FF00],
                                    ['dots_1_long_release', $this->Translate('Dots 1 Long Release'), '', 0x00FF00],
                                    ['dots_1_short_release', $this->Translate('Dots 1 Short Release'), '', 0x00FF00],
                                    ['dots_2_double_press', $this->Translate('Dots 2 Double Press'), '', 0x00FF00],
                                    ['dots_2_initial_press', $this->Translate('Dots 2 Initial Press'), '', 0x00FF00],
                                    ['dots_2_long_press', $this->Translate('Dots 2 Long Press'), '', 0x00FF00],
                                    ['dots_2_long_release', $this->Translate('Dots 2 Long Release'), '', 0x00FF00],
                                    ['dots_2_short_release', $this->Translate('Dots 2 Short Release'), '', 0x00FF00],
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['track_next', $this->Translate('Next Track'), '', 0x00FF00],
                                    ['track_previous', $this->Translate('Previous Track'), '', 0x00FF00],
                                    ['volume_down', $this->Translate('Volume Down'), '', 0x00FF00],
                                    ['volume_down_hold', $this->Translate('Volume Down Hold'), '', 0x00FF00],
                                    ['volume_up', $this->Translate('Volume Up'), '', 0x00FF00],
                                    ['volume_up_hold', $this->Translate('Volume Up Hold'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.bdac7927':
                        case 'Z2M.action.301a3bd1':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['arrow_left_click', $this->Translate('Arrow Left Click'), '', 0x00FF00],
                                    ['arrow_left_hold', $this->Translate('Arrow Left Hold'), '', 0x00FF00],
                                    ['arrow_left_release', $this->Translate('Arrow Left Release'), '', 0x00FF00],
                                    ['arrow_right_click', $this->Translate('Arrow Right click'), '', 0x00FF00],
                                    ['arrow_right_hold', $this->Translate('Arrow Right Hold'), '', 0x00FF00],
                                    ['arrow_right_release', $this->Translate('Arrow Right Release'), '', 0x00FF00],
                                    ['brightness_down_hold', $this->Translate('Brightness Down Hold'), '', 0x00FF00],
                                    ['brightness_down_release', $this->Translate('Brightness Down Release'), '', 0x00FF00],
                                    ['brightness_down_click', $this->Translate('Brightness Down click'), '', 0x00FF00],
                                    ['brightness_up_click', $this->Translate('Brightness Up click'), '', 0x00FF00],
                                    ['brightness_up_hold', $this->Translate('Brightness Up Hold'), '', 0x00FF00],
                                    ['brightness_up_release', $this->Translate('Brightness Up Release'), '', 0x00FF00],
                                    ['brightness_move_down', $this->Translate('Brightness Move Down'), '', 0x00FF00],
                                    ['brightness_move_up', $this->Translate('Brightness Move Up'), '', 0x00FF00],
                                    ['brightness_stop', $this->Translate('Brightness Stop'), '', 0x00FF00],
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00]
                                ]);
                            }
                            break;
                            case 'Z2M.action.f200af18':
                                if (!IPS_VariableProfileExists($ProfileName)) {
                                    $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                        ['double', $this->Translate('Double'), '', 0x00FF00],
                                        ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                        ['release', $this->Translate('Release'), '', 0x00FF00],
                                        ['shake', $this->Translate('Shake'), '', 0x00FF00],
                                        ['single', $this->Translate('Single'), '', 0x00FF00]
                                    ]);
                                }
                            break;
                            case 'Z2M.action.bdac7927':
                                if (!IPS_VariableProfileExists($ProfileName)) {
                                    $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                        ['arrow_left_click', $this->Translate('Arrow Left Click'), '', 0x00FF00],
                                        ['arrow_left_hold', $this->Translate('Arrow Left Hold'), '', 0x00FF00],
                                        ['arrow_left_release', $this->Translate('Arrow Left Release'), '', 0x00FF00],
                                        ['arrow_right_click', $this->Translate('Arrow Right click'), '', 0x00FF00],
                                        ['arrow_right_hold', $this->Translate('Arrow Right Hold'), '', 0x00FF00],
                                        ['arrow_right_release', $this->Translate('Arrow Right Release'), '', 0x00FF00],
                                        ['brightness_down_hold', $this->Translate('Brightness Down Hold'), '', 0x00FF00],
                                        ['brightness_down_release', $this->Translate('Brightness Down Release'), '', 0x00FF00],
                                        ['brightness_down_click', $this->Translate('Brightness Down click'), '', 0x00FF00],
                                        ['brightness_up_click', $this->Translate('Brightness Up click'), '', 0x00FF00],
                                        ['brightness_up_hold', $this->Translate('Brightness Up Hold'), '', 0x00FF00],
                                        ['brightness_up_release', $this->Translate('Brightness Up Release'), '', 0x00FF00],
                                        ['toggle', $this->Translate('Toggle'), '', 0x00FF00]
                                    ]);
                                }
                            break;
                        case 'Z2M.action.29611a11':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['fall', $this->Translate('Fall'), '', 0x00FF00],
                                    ['flip180', $this->Translate('Flip 180'), '', 0x00FF00],
                                    ['flip90', $this->Translate('Flip 90'), '', 0x00FF00],
                                    ['rotate_left', $this->Translate('Rotate Left'), '', 0x00FF00],
                                    ['rotate_right', $this->Translate('Rotate Right'), '', 0x00FF00],
                                    ['shake', $this->Translate('Shake'), '', 0x00FF00],
                                    ['slide', $this->Translate('Slide'), '', 0x00FF00],
                                    ['tap', $this->Translate('Tap'), '', 0x00FF00],
                                    ['throw', $this->Translate('Throw'), '', 0x00FF00],
                                    ['wakeup', $this->Translate('Wakeup'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.47d59fde':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['1_min_inactivity', $this->Translate('1 minute inactivity'), '', 0x00FF00],
                                    ['flip180', $this->Translate('Flip 180'), '', 0x00FF00],
                                    ['flip90', $this->Translate('Flip 90'), '', 0x00FF00],
                                    ['flip_to_side', $this->Translate('Flip to side'), '', 0x00FF00],
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['rotate_left', $this->Translate('Rotate Left'), '', 0x00FF00],
                                    ['rotate_right', $this->Translate('Rotate Right'), '', 0x00FF00],
                                    ['shake', $this->Translate('Shake'), '', 0x00FF00],
                                    ['side_up', $this->Translate('Side up'), '', 0x00FF00],
                                    ['slide', $this->Translate('Slide'), '', 0x00FF00],
                                    ['tap', $this->Translate('Tap'), '', 0x00FF00],
                                    ['throw', $this->Translate('Throw'), '', 0x00FF00]
                                ]);
                            }
                            break;

                        case 'Z2M.action.85b816e8':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['single', $this->Translate('Single'), '', 0x00FF00],
                                    ['double', $this->Translate('Double'), '', 0x00FF00],
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['many', $this->Translate('Many'), '', 0x00FF00],
                                    ['quadruple', $this->Translate('Quadruple'), '', 0x00FF00],
                                    ['release', $this->Translate('Release'), '', 0x00FF00],
                                    ['triple', $this->Translate('Triple'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.33dbe026':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['double_both', $this->Translate('Double Both'), '', 0x00FF00],
                                    ['double_left', $this->Translate('Double Left'), '', 0x00FF00],
                                    ['double_right', $this->Translate('Double Right'), '', 0x00FF00],
                                    ['hold_both', $this->Translate('Hold Both'), '', 0x00FF00],
                                    ['hold_left', $this->Translate('Hold Left'), '', 0x00FF00],
                                    ['hold_right', $this->Translate('Hold Right'), '', 0x00FF00],
                                    ['single_both', $this->Translate('Single Both'), '', 0x00FF00],
                                    ['single_left', $this->Translate('Single Left'), '', 0x00FF00],
                                    ['single_right', $this->Translate('Single Right'), '', 0x00FF00],
                                    ['triple_both', $this->Translate('Triple Both'), '', 0x00FF00],
                                    ['triple_left', $this->Translate('Triple Left'), '', 0x00FF00],
                                    ['triple_right', $this->Translate('Triple Right'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.14fac83':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['brightness_move_down', $this->Translate('Brightness move down'), '', 0x00FF00],
                                    ['brightness_move_up', $this->Translate('Brightness move up'), '', 0x00FF00],
                                    ['brightness_stop', $this->Translate('Brightness Stop'), '', 0x00FF00],
                                    ['brightness_move_to_level', $this->Translate('Brightness Move To Level'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.bdac7927':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['arrow_left_click', $this->Translate('Arrow Left Click'), '', 0x00FF00],
                                    ['arrow_left_hold', $this->Translate('Arrow Left Hold'), '', 0x00FF00],
                                    ['arrow_left_release', $this->Translate('Arrow Left Release'), '', 0x00FF00],
                                    ['arrow_right_click', $this->Translate('Arrow Right Click'), '', 0x00FF00],
                                    ['arrow_right_hold', $this->Translate('Arrow Right Hold'), '', 0x00FF00],
                                    ['arrow_right_release', $this->Translate('Arrow Right Release'), '', 0x00FF00],
                                    ['brightness_down_click', $this->Translate('Brightness Down Click'), '', 0x00FF00],
                                    ['brightness_down_hold', $this->Translate('Brightness DownHold'), '', 0x00FF00],
                                    ['brightness_down_release', $this->Translate('Brightness Down Release'), '', 0x00FF00],
                                    ['brightness_up_click', $this->Translate('Brightness Up Click'), '', 0x00FF00],
                                    ['brightness_up_hold', $this->Translate('Brightness Up Hold'), '', 0x00FF00],
                                    ['brightness_up_release', $this->Translate('Brightness Up Release'), '', 0x00FF00],
                                    ['toggle', $this->Translate('toggle'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.5a39b546':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['open', $this->Translate('Open'), '', 0x00FF00],
                                    ['stop', $this->Translate('Stop'), '', 0xFF0000],
                                    ['close', $this->Translate('Close'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.action.c1844f92':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['button_1_hold', $this->Translate('Button 1 Hold'), '', 0x00FF00],
                                    ['button_1_release', $this->Translate('Button 1 Release'), '', 0x00FF00],
                                    ['button_1_single', $this->Translate('Button 1 Single'), '', 0x00FF00],
                                    ['button_1_double', $this->Translate('Button 1 Double'), '', 0x00FF00],
                                    ['button_1_triple', $this->Translate('Button 1 Tripple'), '', 0x00FF00],
                                    ['button_2_hold', $this->Translate('Button 2 Hold'), '', 0x00FF00],
                                    ['button_2_release', $this->Translate('Button 2 Release'), '', 0x00FF00],
                                    ['button_2_single', $this->Translate('Button 2 Single'), '', 0x00FF00],
                                    ['button_2_double', $this->Translate('Button 2 Double'), '', 0x00FF00],
                                    ['button_2_triple', $this->Translate('Button 2 Tripple'), '', 0x00FF00],
                                    ['button_3_hold', $this->Translate('Button 3 Hold'), '', 0x00FF00],
                                    ['button_3_release', $this->Translate('Button 3 Release'), '', 0x00FF00],
                                    ['button_3_single', $this->Translate('Button 3 Single'), '', 0x00FF00],
                                    ['button_3_double', $this->Translate('Button 3 Double'), '', 0x00FF00],
                                    ['button_3_triple', $this->Translate('Button 3 Tripple'), '', 0x00FF00],
                                    ['button_4_hold', $this->Translate('Button 4 Hold'), '', 0x00FF00],
                                    ['button_4_release', $this->Translate('Button 4 Release'), '', 0x00FF00],
                                    ['button_4_single', $this->Translate('Button 4 Single'), '', 0x00FF00],
                                    ['button_4_double', $this->Translate('Button 4 Double'), '', 0x00FF00],
                                    ['button_4_triple', $this->Translate('Button 4 Tripple'), '', 0x00FF00],
                                    ['button_5_hold', $this->Translate('Button 5 Hold'), '', 0x00FF00],
                                    ['button_5_release', $this->Translate('Button 5 Release'), '', 0x00FF00],
                                    ['button_5_single', $this->Translate('Button 5 Single'), '', 0x00FF00],
                                    ['button_5_double', $this->Translate('Button 5 Double'), '', 0x00FF00],
                                    ['button_5_triple', $this->Translate('Button 5 Tripple'), '', 0x00FF00],
                                    ['button_6_hold', $this->Translate('Button 6 Hold'), '', 0x00FF00],
                                    ['button_6_release', $this->Translate('Button 6 Release'), '', 0x00FF00],
                                    ['button_6_single', $this->Translate('Button 6 Single'), '', 0x00FF00],
                                    ['button_6_double', $this->Translate('Button 6 Double'), '', 0x00FF00],
                                    ['button_6_triple', $this->Translate('Button 6 Tripple'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.5e7f11cc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['vibration', $this->Translate('Vibration'), '', 0x00FF00],
                                    ['tilt', $this->Translate('Tilt'), '', 0xFFFF00],
                                    ['drop', $this->Translate('Drop'), '', 0xFF9900]
                                ]);
                            }
                            break;
                        case 'Z2M.gradient_scene.da30b2e':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Light', '', '', [
                                    ['blossom', $this->Translate('Blossom'), '', 0x00FF00],
                                    ['crocus', $this->Translate('Crocus'), '', 0x00FF00],
                                    ['precious', $this->Translate('Precious'), '', 0x00FF00],
                                    ['narcissa', $this->Translate('Narcissa'), '', 0x00FF00],
                                    ['beginnings', $this->Translate('Beginnings'), '', 0x00FF00],
                                    ['first_light', $this->Translate('First Light'), '', 0x00FF00],
                                    ['horizon', $this->Translate('Horizon'), '', 0x00FF00],
                                    ['valley_dawn', $this->Translate('Valley Down'), '', 0x00FF00],
                                    ['sunflare', $this->Translate('Sunflare'), '', 0x00FF00],
                                    ['emerald_flutter', $this->Translate('Emerald Flutter'), '', 0x00FF00],
                                    ['memento', $this->Translate('Memento'), '', 0x00FF00],
                                    ['resplendent', $this->Translate('Resplendent'), '', 0x00FF00],
                                    ['scarlet_dream', $this->Translate('Scarlet Dream'), '', 0x00FF00],
                                    ['lovebirds', $this->Translate('Lovebirds'), '', 0x00FF00],
                                    ['smitten', $this->Translate('Smitten'), '', 0x00FF00],
                                    ['glitz_and_glam', $this->Translate('Glitz and Glam'), '', 0x00FF00],
                                    ['promise', $this->Translate('Promise'), '', 0x00FF00],
                                    ['ruby_romance', $this->Translate('Ruby Romance'), '', 0x00FF00],
                                    ['city_of_love', $this->Translate('City of Love'), '', 0x00FF00],
                                    ['honolulu', $this->Translate('Honolulu'), '', 0x00FF00],
                                    ['savanna_sunset', $this->Translate('Savanna Sunset'), '', 0x00FF00],
                                    ['golden_pond', $this->Translate('Golden Pond'), '', 0x00FF00],
                                    ['runy_glow', $this->Translate('Runny Glow'), '', 0x00FF00],
                                    ['tropical_twilight', $this->Translate('Tropical Twilight'), '', 0x00FF00],
                                    ['miami', $this->Translate('Miami'), '', 0x00FF00],
                                    ['cancun', $this->Translate('Cancun'), '', 0x00FF00],
                                    ['rio', $this->Translate('Rio'), '', 0x00FF00],
                                    ['chinatown', $this->Translate('Chinatown'), '', 0x00FF00],
                                    ['ibiza', $this->Translate('Ibiza'), '', 0x00FF00],
                                    ['osaka', $this->Translate('Osaka'), '', 0x00FF00],
                                    ['tokyo', $this->Translate('Tokyo'), '', 0x00FF00],
                                    ['motown', $this->Translate('Motown'), '', 0x00FF00],
                                    ['fairfax', $this->Translate('Fairfax'), '', 0x00FF00],
                                    ['galaxy', $this->Translate('Galaxy'), '', 0x00FF00],
                                    ['starlight', $this->Translate('Starlight'), '', 0x00FF00],
                                    ['blood moon', $this->Translate('Blood Moon'), '', 0x00FF00],
                                    ['artic_aurora', $this->Translate('Artic Aurora'), '', 0x00FF00],
                                    ['moonlight', $this->Translate('Moonlight'), '', 0x00FF00],
                                    ['nebula', $this->Translate('Nebula'), '', 0x00FF00],
                                    ['sundown', $this->Translate('Sundown'), '', 0x00FF00],
                                    ['blue_lagoon', $this->Translate('Blue Lagoon'), '', 0x00FF00],
                                    ['palm_beach', $this->Translate('Palm Beach'), '', 0x00FF00],
                                    ['lake_placid', $this->Translate('Lake Placid'), '', 0x00FF00],
                                    ['mountain_breeze', $this->Translate('Mountain Breeze'), '', 0x00FF00],
                                    ['lake_mist', $this->Translate('Lake Mist'), '', 0x00FF00],
                                    ['ocean_dawn', $this->Translate('Ocean Dawn'), '', 0x00FF00],
                                    ['frosty_dawn', $this->Translate('Frosty Dawn'), '', 0x00FF00],
                                    ['sunday_morning', $this->Translate('Sunday Morning'), '', 0x00FF00],
                                    ['emerald_isle', $this->Translate('Emerald Isle'), '', 0x00FF00],
                                    ['spring_blossom', $this->Translate('Spring Blossom'), '', 0x00FF00],
                                    ['midsummer_sun', $this->Translate('Midsummer Sun'), '', 0x00FF00],
                                    ['autumn_gold', $this->Translate('Autumn Gold'), '', 0x00FF00],
                                    ['spring_lake', $this->Translate('Spring Lake'), '', 0x00FF00],
                                    ['winter_mountain', $this->Translate('Winter Mountain'), '', 0x00FF00],
                                    ['midwinter', $this->Translate('Midwinter'), '', 0x00FF00],
                                    ['amber_bloom', $this->Translate('Amber Bloom'), '', 0x00FF00],
                                    ['lily', $this->Translate('Lily'), '', 0x00FF00],
                                    ['painted_sky', $this->Translate('Painted Sky'), '', 0x00FF00],
                                    ['winter_beauty', $this->Translate('Winter Beauty'), '', 0x00FF00],
                                    ['orange_fields', $this->Translate('Orange Fields'), '', 0x00FF00],
                                    ['forest_adventure', $this->Translate('Forest Adventure'), '', 0x00FF00],
                                    ['blue_planet', $this->Translate('Blue Planet'), '', 0x00FF00],
                                    ['soho', $this->Translate('Soho'), '', 0x00FF00],
                                    ['vapor_wave', $this->Translate('Vapor Wave'), '', 0x00FF00],
                                    ['magneto', $this->Translate('Magneto'), '', 0x00FF00],
                                    ['tyrell', $this->Translate('Tyrell'), '', 0x00FF00],
                                    ['disturbia', $this->Translate('Disturbia'), '', 0x00FF00],
                                    ['hal', $this->Translate('Hal'), '', 0x00FF00],
                                    ['golden_star', $this->Translate('Golden Star'), '', 0x00FF00],
                                    ['under_the_tree', $this->Translate('Under the Tree'), '', 0x00FF00],
                                    ['silent_night', $this->Translate('Silent Night'), '', 0x00FF00],
                                    ['rosy_sparkle', $this->Translate('Rosy Sparkle'), '', 0x00FF00],
                                    ['festive_fun', $this->Translate('Festive Fun'), '', 0x00FF00],
                                    ['colour_burst', $this->Translate('Colour Burst'), '', 0x00FF00],
                                    ['crystalline', $this->Translate('Crystalline'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.system_mode.ba44e6f8':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['heat', $this->Translate('Heat'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.switch_type.7c047117':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['state', $this->Translate('State'), '', 0xFFFF00],
                                    ['momentary', $this->Translate('Momentary'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.indicator_mode.c2a87bbe':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['off_on', $this->Translate('Off/On'), '', 0xFFFF00],
                                    ['on_off', $this->Translate('On/Off'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.indicator_mode.593418f7':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['off/on', $this->Translate('Off/On'), '', 0xFFFF00],
                                    ['on/off', $this->Translate('On/Off'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.indicator_mode.45cba34f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0xFF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['off/on', $this->Translate('Off/On'), '', 0xFFFF00],
                                    ['on/off', $this->Translate('On/Off'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.melody.a0adcd38':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Speaker', '', '', [
                                    ['0', $this->Translate('0'), '', 0x000000],
                                    ['1', $this->Translate('1'), '', 0x000000],
                                    ['2', $this->Translate('2'), '', 0x000000],
                                    ['3', $this->Translate('3'), '', 0x000000],
                                    ['4', $this->Translate('4'), '', 0x000000],
                                    ['5', $this->Translate('5'), '', 0x000000],
                                    ['6', $this->Translate('6'), '', 0x000000],
                                    ['7', $this->Translate('7'), '', 0x000000],
                                    ['8', $this->Translate('8'), '', 0x000000],
                                    ['9', $this->Translate('9'), '', 0x000000],
                                    ['10', $this->Translate('10'), '', 0x000000],
                                    ['11', $this->Translate('11'), '', 0x000000],
                                    ['12', $this->Translate('12'), '', 0x000000],
                                    ['13', $this->Translate('13'), '', 0x000000],
                                    ['14', $this->Translate('14'), '', 0x000000],
                                    ['15', $this->Translate('15'), '', 0x000000],
                                    ['16', $this->Translate('16'), '', 0x000000],
                                    ['17', $this->Translate('17'), '', 0x000000],
                                    ['18', $this->Translate('18'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.power_type.6557c94':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Plug', '', '', [
                                    ['battery_full', $this->Translate('Battery Full'), '', 0x00FF00],
                                    ['battery_high', $this->Translate('Battery High'), '', 0xFFFF00],
                                    ['battery_medium', $this->Translate('Battery Medium'), '', 0xFF9900],
                                    ['battery_low', $this->Translate('Battery Low'), '', 0xFF0000],
                                    ['usb', $this->Translate('USB'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.volume.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Speaker', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFFFF00],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.backlight_mode.9e0e16e4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Light', '', '', [
                                    ['LOW', $this->Translate('Low'), '', 0xFFA500],
                                    ['MEDIUM', $this->Translate('Medium'), '', 0xFF0000],
                                    ['HIGH', $this->Translate('High'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.system_mode.3aabe70a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFA500],
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['off', $this->Translate('Off'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.system_mode.e9feae72':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['off', $this->Translate('Off'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.9fca219c':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.879ced8a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['programming', $this->Translate('Programming'), '', 0x8800FF],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['temporary_manual', $this->Translate('Temporary Manual'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.72d7acf2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFA500],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.400bed67':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['hold', $this->Translate('Hold'), '', 0xFFA500],
                                    ['programm', $this->Translate('Program'), '', 0xFFa500],

                                ]);
                            }
                            break;
                        case 'Z2M.preset.1d99b46a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['comfort', $this->Translate('Comfort'), '', 0xFFFF00],
                                    ['complex', $this->Translate('Complex'), '', 0x0000FF],
                                    ['eco', $this->Translate('Eco'), '', 0x00FF00],
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.e1df23ef':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['comfort', $this->Translate('Comfort'), '', 0xFFFF00],
                                    ['complex', $this->Translate('Complex'), '', 0x0000FF],
                                    ['eco', $this->Translate('Eco'), '', 0x00FF00],
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000],
                                    ['away', $this->Translate('Away'), '', 0xFFa500]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.e4c8988a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFFF00],
                                    ['manual', $this->Translate('Manual'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.running_state.8d38f7dc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['idle', $this->Translate('Idle'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.running_state.95941f91':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['cool', $this->Translate('Cool'), '', 0x0000FF],
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['idle', $this->Translate('Idle'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.sensor.183d8cee':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['AL', $this->Translate('AL'), '', 0xFF0000],
                                    ['IN', $this->Translate('IN'), '', 0x00FF00],
                                    ['OU', $this->Translate('OU'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.effect.988c295e':
                        case 'Z2M.effect.fe70ca86':
                        case 'Z2M.effect.efbfc77e':
                        case 'Z2M.effect.dd503500':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['blink', $this->Translate('Blink'), '', 0x0000FF],
                                    ['breathe', $this->Translate('Breathe'), '', 0x0000FF],
                                    ['okay', $this->Translate('Okay'), '', 0x0000FF],
                                    ['channel_change', $this->Translate('Channel Change'), '', 0x0000FF],
                                    ['candle', $this->Translate('Candle'), '', 0x0000FF],
                                    ['fireplace', $this->Translate('Fireplace'), '', 0x0000FF],
                                    ['colorloop', $this->Translate('Colorloop'), '', 0x0000FF],
                                    ['sunrise', $this->Translate('Sunrise'), '', 0x0000FF],
                                    ['stop_hue_effect', $this->Translate('Stop Hue Effect'), '', 0x0000FF],
                                    ['finish_effect', $this->Translate('Finish Effect'), '', 0x0000FF],
                                    ['stop_effect', $this->Translate('Stop Effect'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.sensitivity.848c69b5':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.power_outage_memory.201b7646':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['restore', $this->Translate('Restore'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.power_outage_memory.198b1127':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['restore', $this->Translate('Restore'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.power_on_behavior.b0d55aad':
                        case 'Z2M.power_on_behavior.8a599b04':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['previous', $this->Translate('Previous'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.power_on_behavior.420a27e2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['previous', $this->Translate('Previous'), '', 0x0000FF],
                                    ['toggle', $this->Translate('Toggle'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.backlight_mode':
                        case 'Z2M.motion_sensitivity.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.motion_sensitivity.848c69b5':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.motion_direction.1440af33':
                        case 'Z2M.motion_direction.c4d8a6f1':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Move', '', '', [
                                    ['moving_backward', $this->Translate('moving backward'), '', 0x00FF00],
                                    ['moving_forward', $this->Translate('moving forward'), '', 0xFF0000],
                                    ['standing_still', $this->Translate('standing still'), '', 0xFFFF00]
                                ]);
                            }
                            break;
                        case 'Z2M.force.85dac8d5':
                        case 'Z2M.force.2bd28f19':
                        case 'Z2M.force.a420d592':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['normal', $this->Translate('Normal'), '', 0x00FF00],
                                    ['open', $this->Translate('Open'), '', 0xFF8800],
                                    ['close', $this->Translate('Close'), '', 0xFF0000],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['standard', $this->Translate('Standard'), '', 0xFF0000],
                                    ['very_high', $this->Translate('Very High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.moving.fe5886c':
                        case 'Z2M.moving.7ac27aed':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Move', '', '', [
                                    ['UP', $this->Translate('Up'), '', 0x00FF00],
                                    ['STOP', $this->Translate('Stop'), '', 0xFF8800],
                                    ['DOWN', $this->Translate('Down'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.moving_left':
                        case 'Z2M.moving_right':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Move', '', '', [
                                    ['UP', $this->Translate('Up'), '', 0x00FF00],
                                    ['STOP', $this->Translate('Stop'), '', 0xFF8800],
                                    ['DOWN', $this->Translate('Down'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.trv_mode.4f5344cd':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Climate', '', '', [
                                    ['1', $this->Translate('Manual (Valve Position)'), '', 0x00FF00],
                                    ['2', $this->Translate('Automatic'), '', 0xFF8800],
                                ]);
                            }
                            break;
                        case 'Z2M.sensitivity.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.state.7c75b7a3':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Shutter', '', '', [
                                    ['OPEN', $this->Translate('Open'), '', 0x00FF00],
                                    ['STOP', $this->Translate('Stop'), '', 0xFF0000],
                                    ['CLOSE', $this->Translate('Close'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.mode.fecb2e2f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['burglar', $this->Translate('Burglar'), '', 0xFFC0CB],
                                    ['emergency', $this->Translate('Emergency'), '', 0xFFFF00],
                                    ['emergency_panic', $this->Translate('Emergency Panic'), '', 0xFF8800],
                                    ['fire', $this->Translate('Fire'), '', 0xFF0000],
                                    ['fire_panic', $this->Translate('Fire Panic'), '', 0x880000],
                                    ['Police_panic', $this->Translate('Police Panic'), '', 0x4169E1],
                                    ['stop', $this->Translate('Stop'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.mode.be3d8da4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['morning', $this->Translate('Morning'), '', 0xFFC0CB],
                                    ['night', $this->Translate('Night'), '', 0xFFFF00]
                                ]);
                            }
                            break;
                        case 'Z2M.week.4e05e759':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Calendar', '', '', [
                                    ['5+2', $this->Translate('5+2'), '', 0x00FF00],
                                    ['6+1', $this->Translate('6+1'), '', 0xFF8800],
                                    ['7', $this->Translate('7'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.level.ae420ac':
                        case 'Z2M.strobe_level.ae420ac':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Gear', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['very_high', $this->Translate('Very High'), '', 0xFF8800],
                                ]);
                            }
                            break;
                        case 'Z2M.radar_scene.b071d907':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['area', $this->Translate('Area'), '', 0xFF0000],
                                    ['bedroom', $this->Translate('Bedroom'), '', 0x8800FF],
                                    ['default', $this->Translate('Default'), '', 0xFFFFFF],
                                    ['hotel', $this->Translate('Hotel'), '', 0xFFFF00],
                                    ['office', $this->Translate('Office'), '', 0x008800],
                                    ['parlour', $this->Translate('Parlour'), '', 0x0000FF],
                                    ['toilet', $this->Translate('Toilet'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.motor_working_mode.12bc841d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['continuous', $this->Translate('Continuous'), '', 0xFF0000],
                                    ['intermittently', $this->Translate('Intermittently'), '', 0x8800FF]
                                ]);
                            }
                            break;
                        case 'Z2M.control.a0c4f29e':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['close', $this->Translate('Close'), '', 0xFF8800],
                                    ['continue', $this->Translate('Continue'), '', 0xFFFF00],
                                    ['open', $this->Translate('Open'), '', 0x00FF00],
                                    ['stop', $this->Translate('Stop'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.control_back_mode.cf88002f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['back', $this->Translate('Back'), '', 0xFF8800],
                                    ['forward', $this->Translate('Forward'), '', 0xFFFF00]
                                ]);
                            }
                            break;
                        case 'Z2M.border.8e25e2eb':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['down', $this->Translate('Down'), '', 0xFF8800],
                                    ['down_delete', $this->Translate('Down Delete'), '', 0xFFFF00],
                                    ['up', $this->Translate('Up'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.brightness_state.95110215':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['middle', $this->Translate('Middle'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['strong', $this->Translate('Strong'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.self_test.f4bae49d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['checking', $this->Translate('Checking'), '', 0xFFFF00],
                                    ['check_success', $this->Translate('Check Success'), '', 0x00FF00],
                                    ['check_failure', $this->Translate('Check Failure'), '', 0xFF0000],
                                    ['others', $this->Translate('Others'), '', 0xFFFF00],
                                    ['comm_fault', $this->Translate('Comm Fault'), '', 0xFF0000],
                                    ['radar_fault', $this->Translate('Radar Fault'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.brightness_level.9e0e16e4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['LOW', $this->Translate('Low'), '', 0x00FF00],
                                    ['MEDIUM', $this->Translate('Medium'), '', 0xFF8800],
                                    ['HIGH', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        default:
                            $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                            $this->SendDebug(__FUNCTION__ . ':: ProfileName Values', json_encode($expose['values']), 0);
                            return false;
                    }
                }
                break;
            case 'numeric':
                switch ($expose['property']) {
                    case 'soil_moisture':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Drops', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'regulation_setpoint_offset':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'load_estimate':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'load_room_mean':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'algorithm_scale_factor':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'trigger_time':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' Minutes', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'external_measured_room_sensor':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'smoke_density_dbm':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Factory', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'display_brightness':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'display_ontime':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Sleep', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'side':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Shuffle', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'angle_x':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Shuffle', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'angle_y':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Shuffle', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'angle_z':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Shuffle', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                            break;
                    case 'boost_heating_countdown_time_set':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' s', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'min_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'max_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'eco_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'power_outage_count':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Information', '', ' ', 0, 0, 0);
                        }
                        break;
                    case 'duration':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' S', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'humidity_max':
                    case 'humidity_min':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Gauge', '', ' %', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'temperature_max':
                    case 'temperature_min':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'duration_of_absence':
                    case 'duration_of_attendance':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'brightness':
                    case 'brightness_rgb':
                    case 'brightness_white':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', '%', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'color_temp':
                    case 'color_temp_rgb':
                    case 'color_temp_startup':
                    case 'color_temp_startup_rgb':
                    case 'action_color_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Bulb', '', ' mired', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'valve_position':
                    case 'percent_state':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' %', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'remote_temperature':
                    case 'current_heating_setpoint_auto':
                    case 'current_heating_setpoint':
                    case 'occupied_heating_setpoint':
                    case 'occupied_heating_setpoint_scheduled':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], $expose['value_step'], 1);
                        }
                        break;
                    case 'linkquality':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' lqi', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'co2':
                    case 'voc':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Leaf', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'pm25':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Leaf', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step']);
                        }
                        break;
                    case 'occupancy_timeout':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $this->Translate('Seconds'), $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'boost_heating_countdown':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $this->Translate('Minutes'), 0, 0, 0);
                        }
                        break;
                    case 'boost_time':
                    case 'boost_timeset_countdown':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'overload_protection':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Electricity', '', ' ' . $this->Translate('Watt'), $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'strobe_duty_cycle':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ', $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'action_duration':
                    case 'action_transition_time':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'radar_sensitivity':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], $expose['value_step']);
                        }
                        break;
                    case 'target_distance':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Move', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'minimum_range':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'maximum_range':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'deadzone_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'max_temperature_limit':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'detection_delay':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'detection_interval':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 1);
                        }
                        break;
                    case 'fading_time':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'detfading_timeection_delay':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 1);
                        }
                        break;
                    case 'max_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'intensity', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'calibration_time':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'calibration_time_left':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'calibration_time_right':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                        $this->SendDebug(__FUNCTION__ . ':: ProfileName Values', json_encode($expose['values']), 0);
                        break;
                }
                break;
            default:
                # code...
                break;
        }
        return $ProfileName;
    }

    private function mapExposesToVariables(array $exposes)
    {
        $missedVariables = [];
        $missedVariables['light'] = [];
        $missedVariables['switch'] = [];
        $missedVariables['climate'] = [];
        $missedVariables['lock'] = [];

        $this->SendDebug(__FUNCTION__ . ':: All Exposes', json_encode($exposes), 0);

        foreach ($exposes as $key => $expose) {
            switch ($expose['type']) {
                case 'switch':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'state':
                                            $this->RegisterVariableBoolean('ZWAVE2M_State', $this->Translate('State'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_State');
                                            break;
                                        case 'state_l1':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel1', $this->Translate('State 1'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel1');
                                            break;
                                        case 'state_l2':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel2', $this->Translate('State 2'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel2');
                                            break;
                                        case 'state_l3':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel3', $this->Translate('State 3'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel3');
                                            break;
                                        case 'state_l4':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel4', $this->Translate('State 4'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel4');
                                            break;
                                        case 'state_l5':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel5', $this->Translate('State 5'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel5');
                                            break;
                                        case 'state_l6':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel6', $this->Translate('State 6'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel6');
                                            break;
                                        case 'state_l7':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel7', $this->Translate('State 7'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel7');
                                            break;
                                        case 'state_l8':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Statel8', $this->Translate('State 8'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Statel8');
                                            break;
                                        case 'window_detection':
                                            $this->RegisterVariableBoolean('ZWAVE2M_WindowDetection', $this->Translate('Window Detection'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_WindowDetection');
                                            break;
                                        case 'valve_detection':
                                            $this->RegisterVariableBoolean('ZWAVE2M_ValveDetection', $this->Translate('Valve Detection'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_ValveDetection');
                                            break;
                                        case 'auto_lock':
                                            $this->RegisterVariableBoolean('ZWAVE2M_AutoLock', $this->Translate('Auto Lock'), 'Z2M.AutoLock');
                                            $this->EnableAction('ZWAVE2M_AutoLock');
                                            break;
                                        case 'away_mode':
                                            $this->RegisterVariableBoolean('ZWAVE2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_AwayMode');
                                            break;
                                        case 'state_left':
                                            $this->RegisterVariableBoolean('ZWAVE2M_state_left', $this->Translate('State Left'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_state_left');
                                            break;
                                        case 'state_right':
                                            $this->RegisterVariableBoolean('ZWAVE2M_state_right', $this->Translate('State Right'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_state_right');
                                            break;
                                        default:
                                            // Default Switch binary
                                            $missedVariables['switch'][] = $feature;
                                            break;
                                    }
                                    break; //Switch binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        default:
                                            // Default Switch binary
                                            $missedVariables['switch'][] = $feature;
                                            break;
                                    }
                                    break; //Switch numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        default:
                                            // Default Switch enum
                                            $missedVariables['switch'][] = $feature;
                                            break;
                                    }
                                    break; //Switch enum break;
                            }
                        }
                    }
                    break; //Switch break

                case 'light':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'state':
                                            //Variable with Profile ~Switch
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('ZWAVE2M_State', $this->Translate('State'), '~Switch');
                                                $this->EnableAction('ZWAVE2M_State');
                                            }
                                            break;
                                        case 'state_rgb':
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('ZWAVE2M_StateRGB', $this->Translate('State RGB'), '~Switch');
                                                $this->EnableAction('ZWAVE2M_StateRGB');
                                            }
                                            break;
                                        case 'state_white':
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('ZWAVE2M_StateWhite', $this->Translate('State White'), '~Switch');
                                                $this->EnableAction('ZWAVE2M_StateWhite');
                                            }
                                            break;
                                        default:
                                            // Default light binary
                                            $missedVariables['light'][] = $feature;
                                            break;
                                    }
                                    break; //Light binary break
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'brightness':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_Brightness', $this->Translate('Brightness'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_Brightness');
                                            }
                                            break;
                                        case 'brightness_rgb':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_BrightnessRGB', $this->Translate('Brightness RGB'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_BrightnessRGB');
                                            }
                                            break;
                                        case 'brightness_white':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_BrightnessWhite', $this->Translate('Brightness White'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_BrightnessWhite');
                                            }
                                            break;
                                        case 'color_temp':
                                            //Color Temperature Mired
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_ColorTemp', $this->Translate('Color Temperature'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_ColorTemp');
                                            }
                                            //TODO: Color Temp Presets
                                            // Color Temperature in Kelvin nicht automatisiert, deswegen nicht über die Funktion registerVariableProfile
                                            if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
                                                $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
                                            }
                                            $this->RegisterVariableInteger('ZWAVE2M_ColorTempKelvin', $this->Translate('Color Temperature Kelvin'), 'Z2M.ColorTemperatureKelvin');
                                            $this->EnableAction('ZWAVE2M_ColorTempKelvin');
                                            break;
                                        case 'color_temp_rgb':
                                            //Color Temperature Mired
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_ColorTempRGB', $this->Translate('Color Temperature RGB'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_ColorTempRGB');
                                            }
                                            //TODO: Color Temp Presets
                                            // Color Temperature in Kelvin nicht automatisiert, deswegen nicht über die Funktion registerVariableProfile
                                            if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
                                                $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
                                            }
                                            $this->RegisterVariableInteger('ZWAVE2M_ColorTempRGBKelvin', $this->Translate('Color Temperature RGB Kelvin'), 'Z2M.ColorTemperatureKelvin');
                                            $this->EnableAction('ZWAVE2M_ColorTempRGBKelvin');
                                            break;
                                        case 'color_temp_startup_rgb':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_ColorTempStartupRGB', $this->Translate('Color Temperature Startup RGB'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_ColorTempStartupRGB');
                                            }
                                            break;
                                        case 'color_temp_startup':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_ColorTempStartup', $this->Translate('Color Temperature Startup RGB'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_ColorTempStartup');
                                            }
                                            break;
                                        default:
                                            // Default light numeric
                                            $missedVariables['light'][] = $feature;
                                    }
                                    break; //Light numeric break
                                case 'composite':
                                    switch ($feature['property']) {
                                        case 'color':
                                            if ($feature['name'] == 'color_xy') {
                                                $this->RegisterVariableInteger('ZWAVE2M_Color', $this->Translate('Color'), 'HexColor');
                                                $this->EnableAction('ZWAVE2M_Color');
                                            }
                                            break;
                                        case 'color_rgb':
                                            if ($feature['name'] == 'color_xy') {
                                                $this->RegisterVariableInteger('ZWAVE2M_ColorRGB', $this->Translate('Color'), 'HexColor');
                                                $this->EnableAction('ZWAVE2M_ColorRGB');
                                            }
                                            break;
                                        default:
                                            // Default light composite
                                            $missedVariables['light'][] = $feature;
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                    break; //Light break;
                case 'climate':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'away_mode':
                                            $this->RegisterVariableBoolean('ZWAVE2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_AwayMode');
                                            break;
                                        default:
                                            // Default climate binary
                                            $missedVariables['climate'][] = $feature;
                                            break;
                                    }
                                    break; //Climate binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'current_heating_setpoint':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableFloat('ZWAVE2M_CurrentHeatingSetpoint', $this->Translate('Current Heating Setpoint'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_CurrentHeatingSetpoint');
                                            }
                                            break;
                                        case 'local_temperature':
                                            $this->RegisterVariableFloat('ZWAVE2M_LocalTemperature', $this->Translate('Local Temperature'), '~Temperature');
                                            break;
                                        case 'local_temperature_calibration':
                                            $this->RegisterVariableFloat('ZWAVE2M_LocalTemperatureCalibration', $this->Translate('Local Temperature Calibration'), '~Temperature');
                                            $this->EnableAction('ZWAVE2M_LocalTemperatureCalibration');
                                            break;
                                        case 'occupied_heating_setpoint':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableFloat('ZWAVE2M_OccupiedHeatingSetpoint', $this->Translate('Occupied Heating Setpoint'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_OccupiedHeatingSetpoint');
                                            }
                                            break;
                                        case 'pi_heating_demand':
                                            $this->RegisterVariableInteger('ZWAVE2M_Pi_Heating_Demand', $this->Translate('Valve Position (Heating Demand)'), '~Intensity.100');
                                            $this->EnableAction('ZWAVE2M_Pi_Heating_Demand');
                                            break;
                                        default:
                                            // Default Climate binary
                                            $missedVariables['climate'][] = $feature;
                                            break;
                                    }
                                    break; //Climate numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'system_mode':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_SystemMode', $this->Translate('Mode'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_SystemMode');
                                            }
                                            break;
                                        case 'preset':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_Preset', $this->Translate('Preset'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_Preset');
                                            }
                                            break;
                                        case 'running_state':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_RunningState', $this->Translate('Running State'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_RunningState');
                                            }
                                            break;
                                        case 'sensor':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_Sensor', $this->Translate('Sensor'), $ProfileName);
                                                $this->EnableAction('ZWAVE2M_Sensor');
                                            }
                                            break;
                                        default:
                                            // Default Climate enum
                                            $missedVariables['climate'][] = $feature;
                                            break;
                                    }
                                    break; //Climate enum break;
                            }
                        }
                    }
                    break; //Climate break
                case 'lock':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'child_lock':
                                            $this->RegisterVariableBoolean('ZWAVE2M_ChildLock', $this->Translate('Child Lock'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_ChildLock');
                                            break;
                                        default:
                                            // Default lock binary
                                            $missedVariables['lock'][] = $feature;
                                            break;
                                    }
                                    break; //Lock binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        default:
                                            // Default lock binary
                                            $missedVariables['lock'][] = $feature;
                                            break;
                                    }
                                    break; //Lock numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        default:
                                            // Default lock enum
                                            $missedVariables['lock'][] = $feature;
                                            break;
                                    }
                                    break; //Lock enum break;
                            }
                        }
                    }
                    break; //Lock break
                case 'binary':
                    switch ($expose['property']) {
                        case 'do_not_disturb':
                            $this->RegisterVariableBoolean('ZWAVE2M_DoNotDisturb', $this->Translate('Do Not Disturb'), '~Switch');
                            $this->EnableAction('ZWAVE2M_DoNotDisturb');
                            break;
                        case 'button_lock':
                            $this->RegisterVariableBoolean('ZWAVE2M_ButtonLock', $this->Translate('Button Lock'), '~Switch');
                            break;
                        case 'mute':
                            $this->RegisterVariableBoolean('ZWAVE2M_Mute', $this->Translate('Mute'), '~Switch');
                            break;
                        case 'adaptation_run_settings':
                            $this->RegisterVariableBoolean('ZWAVE2M_AdaptationRunSettings', $this->Translate('Adaptation Run Settings'), '~Switch');
                            $this->EnableAction('ZWAVE2M_AdaptationRunSettings');
                            break;
                        case 'preheat_status':
                            $this->RegisterVariableBoolean('ZWAVE2M_PreheatStatus', $this->Translate('Preheat Status'), '~Switch');
                            $this->EnableAction('ZWAVE2M_PreheatStatus');
                            break;
                        case 'load_balancing_enable':
                            $this->RegisterVariableBoolean('ZWAVE2M_LoadBalancingEnable', $this->Translate('Load Balancing Enable'), '~Switch');
                            $this->EnableAction('ZWAVE2M_LoadBalancingEnable');
                            break;
                        case 'window_open_external':
                            $this->RegisterVariableBoolean('ZWAVE2M_WindowOpenExternal', $this->Translate('Window Open External'), '~Switch');
                            $this->EnableAction('ZWAVE2M_WindowOpenExternal');
                            break;
                        case 'window_open_feature':
                            $this->RegisterVariableBoolean('ZWAVE2M_Window_OpenFeature', $this->Translate('Window Open Feature'), '~Switch');
                            $this->EnableAction('ZWAVE2M_Window_OpenFeature');
                            break;
                        case 'radiator_covered':
                            $this->RegisterVariableBoolean('ZWAVE2M_RadiatorCovered', $this->Translate('Radiator Covered'), '~Switch');
                            $this->EnableAction('ZWAVE2M_RadiatorCovered');
                            break;
                        case 'heat_required':
                            $this->RegisterVariableBoolean('ZWAVE2M_HeatRequired', $this->Translate('Heat Required'), '~Switch');
                            break;
                        case 'heat_available':
                            $this->RegisterVariableBoolean('ZWAVE2M_HeatAvailable', $this->Translate('Heat Available'), '~Switch');
                            $this->EnableAction('ZWAVE2M_HeatAvailable');
                            break;
                        case 'viewing_direction':
                            $this->RegisterVariableBoolean('ZWAVE2M_ViewingDirection', $this->Translate('Viewing Direction'), '~Switch');
                            $this->EnableAction('ZWAVE2M_ViewingDirection');
                            break;
                        case 'thermostat_vertical_orientation':
                            $this->RegisterVariableBoolean('ZWAVE2M_ThermostatVerticalOrientation', $this->Translate('Thermostat VerticalOrientation'), '~Switch');
                            $this->EnableAction('ZWAVE2M_ThermostatVerticalOrientation');
                            break;
                        case 'mounted_mode_control':
                            $this->RegisterVariableBoolean('ZWAVE2M_MountedModeControl', $this->Translate('Mounted Mode Control'), '~Switch');
                            $this->EnableAction('ZWAVE2M_MountedModeControl');
                            break;
                        case 'mounted_mode_active':
                            $this->RegisterVariableBoolean('ZWAVE2M_MountedModeActive', $this->Translate('Mounted Mode Active'), '~Switch');
                            break;
                        case 'linkage_alarm_state':
                            $this->RegisterVariableBoolean('ZWAVE2M_LinkageAlarmState', $this->Translate('Linkage Alarm State'), '~Switch');
                            break;
                        case 'linkage_alarm':
                            $this->RegisterVariableBoolean('ZWAVE2M_LinkageAlarm', $this->Translate('Linkage Alarm'), '~Switch');
                            $this->EnableAction('ZWAVE2M_LinkageAlarm');
                            break;
                        case 'heartbeat_indicator':
                            $this->RegisterVariableBoolean('ZWAVE2M_HeartbeatIndicator', $this->Translate('Heartbeat Indicator'), '~Switch');
                            $this->EnableAction('ZWAVE2M_HeartbeatIndicator');
                            break;
                        case 'buzzer_manual_mute':
                            $this->RegisterVariableBoolean('ZWAVE2M_BuzzerManualMute', $this->Translate('Buzzer Manual Mute'), '~Switch');
                            break;
                        case 'buzzer_manual_alarm':
                            $this->RegisterVariableBoolean('ZWAVE2M_BuzzerManualAlarm', $this->Translate('Buzzer Manual Alarm'), '~Switch');
                            break;
                        case 'boost':
                            $this->RegisterVariableBoolean('ZWAVE2M_Boost', $this->Translate('Boost'), '~Switch');
                            $this->EnableAction('ZWAVE2M_Boost');
                            break;
                        case 'valve_state':
                            $this->RegisterVariableBoolean('ZWAVE2M_ValveState', $this->Translate('Valve State'), 'Z2M.ValveState');
                            break;
                        case 'eco_mode':
                            $this->RegisterVariableBoolean('ZWAVE2M_EcoMode', $this->Translate('Eco Mode'), '~Switch');
                            $this->EnableAction('ZWAVE2M_EcoMode');
                            break;
                        case 'temperature_alarm':
                            $this->RegisterVariableBoolean('ZWAVE2M_TemperatureAlarm', $this->Translate('Temperature Alarm'), '~Switch');
                            $this->EnableAction('ZWAVE2M_TemperatureAlarm');
                            break;
                        case 'humidity_alarm':
                            $this->RegisterVariableBoolean('ZWAVE2M_HumidityAlarm', $this->Translate('Humidity Alarm'), '~Switch');
                            $this->EnableAction('ZWAVE2M_HumidityAlarm');
                            break;
                        case 'alarm':
                            $this->RegisterVariableBoolean('ZWAVE2M_Alarm', $this->Translate('Alarm'), '~Switch');
                            $this->EnableAction('ZWAVE2M_Alarm');
                            break;
                        case 'state':
                            //Variable with Profile ~Switch
                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                $this->RegisterVariableBoolean('ZWAVE2M_State', $this->Translate('State'), '~Switch');
                            }
                            break;
                        case 'led_state':
                            $this->RegisterVariableBoolean('ZWAVE2M_LedState', $this->Translate('LED State'), '~Switch');
                            $this->EnableAction('ZWAVE2M_LedState');
                            break;
                        case 'vibration':
                            $this->RegisterVariableBoolean('ZWAVE2M_Vibration', $this->Translate('Vibration'), '~Alert');
                            break;
                        case 'occupancy':
                            $this->RegisterVariableBoolean('ZWAVE2M_Occupancy', $this->Translate('Occupancy'), '~Motion');
                            break;
                        case 'presence':
                            $this->RegisterVariableBoolean('ZWAVE2M_Presence', $this->Translate('Presence'), '~Presence');
                            break;
                        case 'motion':
                            $this->RegisterVariableBoolean('ZWAVE2M_Motion', $this->Translate('Motion'), '~Motion');
                            break;
                        case 'battery_low':
                            $this->RegisterVariableBoolean('ZWAVE2M_Battery_Low', $this->Translate('Battery Low'), '~Battery');
                            break;
                        case 'tamper':
                            $this->RegisterVariableBoolean('ZWAVE2M_Tamper', $this->Translate('Tamper'), '~Alert');
                            break;
                        case 'water_leak':
                            $this->RegisterVariableBoolean('ZWAVE2M_WaterLeak', $this->Translate('Water Leak'), '~Alert');
                            break;
                        case 'contact':
                            $this->RegisterVariableBoolean('ZWAVE2M_Contact', $this->Translate('Contact'), '~Window.Reversed');
                            break;
                        case 'window':
                            $this->RegisterVariableBoolean('ZWAVE2M_Window', $this->Translate('Window'), '~Window.Reversed');
                            break;
                        case 'smoke':
                            $this->RegisterVariableBoolean('ZWAVE2M_Smoke', $this->Translate('Smoke'), '~Alert');
                            break;
                        case 'carbon_monoxide':
                            $this->RegisterVariableBoolean('ZWAVE2M_CarbonMonoxide', $this->Translate('Carbon Monoxide'), '~Alert');
                            break;
                        case 'heating':
                            $this->RegisterVariableBoolean('ZWAVE2M_Heating', $this->Translate('Heating'), '~Switch');
                            break;
                        case 'boost_heating':
                            $this->RegisterVariableBoolean('ZWAVE2M_BoostHeating', $this->Translate('Boost Heating'), '~Switch');
                            $this->EnableAction('ZWAVE2M_BoostHeating');
                            break;
                        case 'away_mode':
                            $this->RegisterVariableBoolean('ZWAVE2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                            $this->EnableAction('ZWAVE2M_AwayMode');
                            break;
                        case 'consumer_connected':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableBoolean('ZWAVE2M_Consumer_Connected', $this->Translate('Consumer connected'), $ProfileName);
                            }
                            break;
                        case 'led_disabled_night':
                            $this->RegisterVariableBoolean('ZWAVE2M_LEDDisabledNight', $this->Translate('LED disabled night'), '~Switch');
                            break;
                        case 'power_outage_memory':
                            $this->RegisterVariableBoolean('ZWAVE2M_PowerOutageMemory', $this->Translate('Power Outage Memory'), '~Switch');
                            $this->EnableAction('ZWAVE2M_PowerOutageMemory');
                            break;
                        case 'auto_off':
                            $this->RegisterVariableBoolean('ZWAVE2M_AutoOff', $this->Translate('Auto Off'), '~Switch');
                            $this->EnableAction('ZWAVE2M_AutoOff');
                            break;
                        case 'calibration':
                            $this->RegisterVariableBoolean('ZWAVE2M_Calibration', $this->Translate('Calibration'), '~Switch');
                            $this->EnableAction('ZWAVE2M_Calibration');
                            break;
                        case 'calibration_left':
                            $this->RegisterVariableBoolean('ZWAVE2M_CalibrationLeft', $this->Translate('Calibration Left'), '~Switch');
                            $this->EnableAction('ZWAVE2M_CalibrationLeft');
                            break;
                        case 'calibration_right':
                            $this->RegisterVariableBoolean('ZWAVE2M_CalibrationRight', $this->Translate('Calibration Right'), '~Switch');
                            $this->EnableAction('ZWAVE2M_CalibrationRight');
                            break;
                        case 'motor_reversal':
                            $this->RegisterVariableBoolean('ZWAVE2M_MotorReversal', $this->Translate('Motor Reversal'), '~Switch');
                            $this->EnableAction('ZWAVE2M_MotorReversal');
                            break;
                        case 'motor_reversal_left':
                            $this->RegisterVariableBoolean('ZWAVE2M_MotorReversalLeft', $this->Translate('Motor Reversal Left'), '~Switch');
                            $this->EnableAction('ZWAVE2M_MotorReversalLeft');
                            break;
                        case 'motor_reversal_right':
                            $this->RegisterVariableBoolean('ZWAVE2M_MotorReversalRight', $this->Translate('Motor Reversal Right'), '~Switch');
                            $this->EnableAction('ZWAVE2M_MotorReversalRight');
                            break;
                        case 'open_window':
                            $this->RegisterVariableBoolean('ZWAVE2M_OpenWindow', $this->Translate('Open Window'), '~Window');
                            $this->EnableAction('ZWAVE2M_OpenWindow');
                            break;
                        case 'window_open':
                            $this->RegisterVariableBoolean('ZWAVE2M_WindowOpen', $this->Translate('Open Window'), '~Window');
                            $this->EnableAction('ZWAVE2M_WindowOpen');
                            break;
                        case 'frost_protection':
                            $this->RegisterVariableBoolean('ZWAVE2M_FrostProtection', $this->Translate('Frost Protection'), '~Switch');
                            $this->EnableAction('ZWAVE2M_FrostProtection');
                            break;
                        case 'heating_stop':
                            $this->RegisterVariableBoolean('ZWAVE2M_HeatingStop', $this->Translate('Heating Stop'), '~Switch');
                            $this->EnableAction('ZWAVE2M_HeatingStop');
                            break;
                        case 'test':
                            $this->RegisterVariableBoolean('ZWAVE2M_Test', $this->Translate('Test'), '~Switch');
                            break;
                        case 'trigger':
                            $this->RegisterVariableBoolean('ZWAVE2M_GarageTrigger', $this->Translate('Garage Trigger'), '~Switch');
                            $this->EnableAction('ZWAVE2M_GarageTrigger');
                            break;
                        case 'garage_door_contact':
                            $this->RegisterVariableBoolean('ZWAVE2M_GarageDoorContact', $this->Translate('Garage Door Contact'), '~Window.Reversed');
                            break;
                        case 'trigger_indicator':
                            $this->RegisterVariableBoolean('ZWAVE2M_TriggerIndicator', $this->Translate('Trigger Indicator'), '~Switch');
                            $this->EnableAction('ZWAVE2M_TriggerIndicator');
                            break;
                        default:
                            $missedVariables[] = $expose;
                            break;
                    }
                    break; //binary break
                case 'enum':
                    switch ($expose['property']) {
                        case 'do_not_disturb':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_DoNotDisturb', $this->Translate('Do not Disturb'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DoNotDisturb');
                            }
                            break;
                        case 'color_power_on_behavior':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_ColorPowerOnBehavior', $this->Translate('Color Power On Behavior'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_ColorPowerOnBehavior');
                            }
                            break;
                        case 'displayed_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_DisplayedTemperature', $this->Translate('Displayed Temperature'), $ProfileName);
                            }
                            break;
                        case 'battery_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_BatteryState', $this->Translate('Battery State'), $ProfileName);
                            }
                            break;
                        case 'temperature_unit':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_TemperatureUnit', $this->Translate('Temperature Unit'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_TemperatureUnit');
                            }
                            break;
                        case 'mute_buzzer':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MuteBuzzer', $this->Translate('Mute Buzzer'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MuteBuzzer');
                            }
                            break;
                        case 'adaptation_run_control':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_AdaptationRunControl', $this->Translate('Adaptation Run Control'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_AdaptationRunControl');
                            }
                            break;
                        case 'adaptation_run_status':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_AdaptationRunStatus', $this->Translate('Adaptation Run Status'), $ProfileName);
                            }
                            break;
                        case 'day_of_week':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_DayOfWeek', $this->Translate('Day Of Week'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DayOfWeek');
                            }
                            break;
                        case 'setpoint_change_source':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_SetpointChangeSource', $this->Translate('Setpoint Change Source'), $ProfileName);
                            }
                            break;
                        case 'programming_operation_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_ProgrammingOperationMode', $this->Translate('Programming Operation Mode'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_ProgrammingOperationMode');
                            }
                            break;
                        case 'keypad_lockout':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_KeypadLockout', $this->Translate('Keypad Lockout'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Keypad_Lockout');
                            }
                            break;
                        case 'buzzer':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Buzzer', $this->Translate('Buzzer'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Buzzer');
                            }
                            break;
                        case 'display_orientation':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_DisplayOrientation', $this->Translate('Display Orientation'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DisplayOrientation');
                            }
                            break;
                        case 'gradient_scene':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_GradientScene', $this->Translate('Gradient Scene'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_GradientScene');
                            }
                            break;
                        case 'switch_type':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_SwitchType', $this->Translate('Switch Type'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_SwitchType');
                            }
                            break;
                        case 'indicator_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_IndicatorMode', $this->Translate('Indicator Mode'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_IndicatorMode');
                            }
                            break;
                        case 'melody':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Melody', $this->Translate('Melody'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Melody');
                            }
                            break;
                        case 'power_type':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerType', $this->Translate('Power Type'), $ProfileName);
                            }
                            break;
                        case 'volume':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Volume', $this->Translate('Volume'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Volume');
                            }
                            break;
                        case 'backlight_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_BacklightMode', $this->Translate('Backlight Mode'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_BacklightMode');
                            }
                            break;
                        case 'effect':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Effect', $this->Translate('Effect'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Effect');
                            }
                            break;
                        case 'action':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Action', $this->Translate('Action'), $ProfileName);
                            }
                            break;
                        case 'sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Sensitivity', $this->Translate('Sensitivity'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Sensitivity');
                            }
                            break;
                        case 'power_outage_memory':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerOutageMemory', $this->Translate('Power Outage Memory'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PowerOutageMemory');
                            }
                            break;
                        case 'power_on_behavior':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerOnBehavior', $this->Translate('Power on behavior'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PowerOnBehavior');
                            }
                            break;
                        case 'power_on_behavior_l1':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerOnBehaviorL1', $this->Translate('Power on behavior L1'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PowerOnBehaviorL1');
                            }
                            break;
                        case 'power_on_behavior_l2':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerOnBehaviorL2', $this->Translate('Power on behavior L2'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PowerOnBehaviorL2');
                            }
                            break;
                        case 'power_on_behavior_l3':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerOnBehaviorL3', $this->Translate('Power on behavior L3'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PowerOnBehaviorL3');
                            }
                            break;
                        case 'power_on_behavior_l4':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_PowerOnBehaviorL4', $this->Translate('Power on behavior L4'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PowerOnBehaviorL4');
                            }
                            break;
                        case 'motor_direction':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MotorDirection', $this->Translate('Motor Direction'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MotorDirection');
                            }
                            break;
                        case 'motion_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MotionSensitivity', $this->Translate('Motion Sensitivity'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MotionSensitivity');
                            }
                            break;
                        case 'force':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Force', $this->Translate('Force'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Force');
                            }
                            break;
                        case 'moving':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Moving', $this->Translate('Current Action'), $ProfileName);
                            }
                            break;
                        case 'moving_left':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MovingLeft', $this->Translate('Current Action Left'), $ProfileName);
                            }
                            break;
                        case 'moving_right':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MovingRight', $this->Translate('Current Action Right'), $ProfileName);
                            }
                            break;
                        case 'trv_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_TRVMode', $this->Translate('TRV Mode'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_TRVMode');
                            }
                            break;
                        case 'motion_direction':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MotionDirection', $this->Translate('Motion Direction'), $ProfileName);
                            }
                            break;
                        case 'radar_scene':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_RadarScene', $this->Translate('Radar Scene'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_RadarScene');
                            }
                            break;
                        case 'motor_working_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_MotorWorkingMode', $this->Translate('Motor Working Mode'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MotorWorkingMode');
                            }
                            break;
                        case 'control':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Control', $this->Translate('Control'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Control');
                            }
                            break;
                        case 'mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Mode', $this->Translate('Mode'), $ProfileName);
                            }
                            $this->EnableAction('ZWAVE2M_Mode');
                            break;
                        case 'control_back_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_ControlBackMode', $this->Translate('Control back Mode'), $ProfileName);
                            }
                            $this->EnableAction('ZWAVE2M_ControlBackMode');
                            break;
                        case 'border':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_Border', $this->Translate('Border'), $ProfileName);
                            }
                            $this->EnableAction('ZWAVE2M_Border');
                            break;
                        case 'brightness_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_BrightnessSate', $this->Translate('Brightness State'), $ProfileName);
                            }
                            break;
                        case 'self_test':
                        case 'selftest':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_SelfTest', $this->Translate('Self Test'), $ProfileName);
                                if ($expose['access'] == 1) {
                                    $this->EnableAction('ZWAVE2M_SelfTest');
                                }
                            }
                            break;
                        case 'brightness_level':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('ZWAVE2M_BrightnessLevel', $this->Translate('Brightness Level'), $ProfileName);
                                if ($expose['access'] == 1) {
                                    $this->EnableAction('ZWAVE2M_BrightnessLevel');
                                }
                            }
                            break;
                        default:
                            $missedVariables[] = $expose;
                            break;
                    }
                    break; //enum break
                case 'numeric':
                    switch ($expose['property']) {
                        case 'remote_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_RemoteTemperature', $this->Translate('Remote Temperature'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_RemoteTemperature');
                            }
                            break;
                        case 'occupied_heating_setpoint_scheduled':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_OccupiedHeatingSetpointScheduled', $this->Translate('Occupied Heating Setpoint Scheduled'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_OccupiedHeatingSetpointScheduled');
                            }
                            break;
                        case 'regulation_setpoint_offset':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_RegulationSetpointOffset', $this->Translate('Regulation Setpoint Offset'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_RegulationSetpointOffset');
                            }
                            break;
                        case 'load_estimate':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_LoadEstimate', $this->Translate('Load Estimate'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_LoadEstimate');
                            }
                            break;
                        case 'load_room_mean':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_LoadRoomMean', $this->Translate('Load Room Mean'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_LoadRoomMean');
                            }
                            break;
                        case 'algorithm_scale_factor':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_AlgorithmScaleFactor', $this->Translate('Algorithm Scale Factor'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_AlgorithmScaleFactor');
                            }
                            break;
                        case 'trigger_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_TriggerTime', $this->Translate('Trigger Time'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_TriggerTime');
                            }
                            break;
                        case 'window_open_internal':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_WindowOpenInternal', $this->Translate('Window Open Internal'), 'Z2M.WindowOpenInternal');
                            }
                            break;
                        case 'external_measured_room_sensor':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ExternalMeasuredRoomSensor', $this->Translate('External Measured Room Sensor'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_ExternalMeasuredRoomSensor');
                            }
                            break;
                        case 'smoke_density_dbm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_SmokeDensityDBM', $this->Translate('Smoke Density db/m'), $ProfileName);
                            }
                            break;
                        case 'display_brightness':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_DisplayBrightness', $this->Translate('Display Brightness'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DisplayBrightness');
                            }
                            break;
                        case 'display_ontime':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_DisplayOntime', $this->Translate('Display Ontime'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DisplayOntime');
                            }
                            break;
                        case 'side':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Side', $this->Translate('Side'), $ProfileName);
                            }
                            break;
                        case 'angle_x':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Angle_X', $this->Translate('Angle X'), $ProfileName);
                            }
                            break;
                        case 'angle_y':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Angle_Y', $this->Translate('Angle Y'), $ProfileName);
                            }
                            break;
                        case 'angle_z':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Angle_Z', $this->Translate('Angle Z'), $ProfileName);
                            }
                            break;
                        case 'boost_heating_countdown_time_set':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_BoostHeatingCountdownTimeSet', $this->Translate('Boost Heating Countdown Time Set'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_BoostHeatingCountdownTimeSet');
                            }
                            break;
                        case 'power_outage_count':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_PowerOutageCount', $this->Translate('Power Outage Count'), $ProfileName);
                            }
                            break;
                        case 'duration':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Duration', $this->Translate('Alarm Duration'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_Duration');
                            }
                            break;
                        case 'humidity_max':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_HumidityMax', $this->Translate('Humidity Max'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_HumidityMax');
                            }
                            break;
                        case 'humidity_min':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_HumidityMin', $this->Translate('Humidity Min'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_HumidityMin');
                            }
                            break;
                        case 'temperature_max':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_TemperatureMax', $this->Translate('Temperature Max'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_TemperatureMax');
                            }
                            break;
                        case 'temperature_min':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_TemperatureMin', $this->Translate('Temperature Min'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_TemperatureMin');
                            }
                            break;
                        case 'action_rate':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ActionRate', $this->Translate('Action Rate'), $ProfileName);
                            }
                            break;
                        case 'action_step_size':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ActionStepSize', $this->Translate('Action Step Size'), $ProfileName);
                            }
                            break;
                        case 'action_transition_time':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ActionTransTime', $this->Translate('Action Transition Time'), $ProfileName);
                            }
                            break;
                        case 'action_group':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ActionGroup', $this->Translate('Action Group'), $ProfileName);
                            }
                            break;
                        case 'action_color_temperature':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ActionColorTemp', $this->Translate('Action Color Temperature'), $ProfileName);
                            }
                            break;
                        case 'linkquality':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Linkquality', $this->Translate('Linkquality'), $ProfileName);
                            }
                            break;
                        case 'valve_position':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_ValvePosition', $this->Translate('Valve Position'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_ValvePosition');
                            }
                            break;
                        case 'duration_of_attendance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Attendance', $this->Translate('Duration of Attendance'), $ProfileName);
                            }
                            break;
                        case 'duration_of_absence':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_Absence', $this->Translate('Duration of Absence'), $ProfileName);
                            }
                            break;
                        case 'battery':
                            $this->RegisterVariableInteger('ZWAVE2M_Battery', $this->Translate('Battery'), '~Battery.100');
                            break;
                        case 'temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_Temperature', $this->Translate('Temperature'), '~Temperature');
                            break;
                        case 'temperature_l1':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL1', $this->Translate('Temperature L1'), '~Temperature');
                            break;
                        case 'temperature_l2':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL2', $this->Translate('Temperature L2'), '~Temperature');
                            break;
                        case 'temperature_l3':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL3', $this->Translate('Temperature L3'), '~Temperature');
                            break;
                        case 'temperature_l4':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL4', $this->Translate('Temperature L4'), '~Temperature');
                            break;
                        case 'temperature_l5':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL5', $this->Translate('Temperature L5'), '~Temperature');
                            break;
                        case 'temperature_l6':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL6', $this->Translate('Temperature L6'), '~Temperature');
                            break;
                        case 'temperature_l7':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL7', $this->Translate('Temperature L7'), '~Temperature');
                            break;
                        case 'temperature_l8':
                            $this->RegisterVariableFloat('ZWAVE2M_TemperatureL8', $this->Translate('Temperature L8'), '~Temperature');
                            break;
                        case 'device_temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_DeviceTemperature', $this->Translate('Device Temperature'), '~Temperature');
                            break;
                        case 'humidity':
                            $this->RegisterVariableFloat('ZWAVE2M_Humidity', $this->Translate('Humidity'), '~Humidity.F');
                            break;
                        case 'pressure':
                            $this->RegisterVariableFloat('ZWAVE2M_Pressure', $this->Translate('Pressure'), '~AirPressure.F');
                            break;
                        case 'co2':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_CO2', $this->Translate('CO2'), $ProfileName);
                            }
                            break;
                        case 'voc':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_VOC', $this->Translate('VOC'), $ProfileName);
                            }
                            break;
                        case 'pm25':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_PM25', $this->Translate('PM25'), $ProfileName);
                            }
                            break;
                        case 'formaldehyd':
                            $this->RegisterVariableInteger('ZWAVE2M_Formaldehyd', $this->Translate('Formaldehyd'), '');
                            break;
                        case 'voltage':
                            $this->RegisterVariableFloat('ZWAVE2M_Voltage', $this->Translate('Voltage'), '~Volt');
                            break;
                        case 'illuminance_lux':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux', $this->Translate('Illuminance Lux'), '~Illumination');
                            break;
                        case 'illuminance_lux_l1':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l1', $this->Translate('Illuminance Lux l1'), '~Illumination');
                            break;
                        case 'illuminance_lux_l2':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l2', $this->Translate('Illuminance Lux l2'), '~Illumination');
                            break;
                        case 'illuminance_lux_l3':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l3', $this->Translate('Illuminance Lux l3'), '~Illumination');
                            break;
                        case 'illuminance_lux_l4':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l4', $this->Translate('Illuminance Lux l4'), '~Illumination');
                            break;
                        case 'illuminance_lux_l5':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l5', $this->Translate('Illuminance Lux l5'), '~Illumination');
                            break;
                        case 'illuminance_lux_l6':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l6', $this->Translate('Illuminance Lux l6'), '~Illumination');
                            break;
                        case 'illuminance_lux_l7':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l7', $this->Translate('Illuminance Lux l7'), '~Illumination');
                            break;
                        case 'illuminance_lux_l8':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance_Lux_l8', $this->Translate('Illuminance Lux l8'), '~Illumination');
                            break;
                        case 'illuminance':
                            $this->RegisterVariableInteger('ZWAVE2M_Illuminance', $this->Translate('Illuminance'), '');
                            break;
                        case 'strength':
                            $this->RegisterVariableInteger('ZWAVE2M_Strength', $this->Translate('Strength'), '');
                            break;
                        case 'angle_x':
                            $this->RegisterVariableFloat('ZWAVE2M_Angle_X', $this->Translate('Angle X'), '');
                            break;
                        case 'angle_x_absolute':
                            $this->RegisterVariableFloat('ZWAVE2M_AngleXAbsolute', $this->Translate('Angle X Absolute'), '');
                            break;
                        case 'angle_y':
                            $this->RegisterVariableFloat('ZWAVE2M_Angle_Y', $this->Translate('Angle Y'), '');
                            break;
                        case 'angle_y_absolute':
                            $this->RegisterVariableFloat('ZWAVE2M_AngleYAbsolute', $this->Translate('Angle Y Absolute'), '');
                            break;
                        case 'angle_z':
                            $this->RegisterVariableFloat('ZWAVE2M_Angle_Z', $this->Translate('Angle Z'), '');
                            break;
                        case 'smoke_density':
                            $this->RegisterVariableFloat('ZWAVE2M_SmokeDensity', $this->Translate('Smoke Density'), '');
                            break;
                        case 'power':
                            $this->RegisterVariableFloat('ZWAVE2M_Power', $this->Translate('Power'), '~Watt.3680');
                            break;
                        case 'current':
                            $this->RegisterVariableFloat('ZWAVE2M_Current', $this->Translate('Current'), '~Ampere');
                            break;
                        case 'energy':
                            $this->RegisterVariableFloat('ZWAVE2M_Energy', $this->Translate('Energy'), '~Electricity');
                            break;
                        case 'occupancy_timeout':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_OccupancyTimeout', $this->Translate('Occupancy Timeout'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_OccupancyTimeout');
                            }
                            break;
                        case 'max_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_MaxTemperature', $this->Translate('Max Temperature'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MaxTemperature');
                            }
                            break;
                        case 'min_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_MinTemperature', $this->Translate('Min Temperature'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MinTemperature');
                            }
                            break;
                        case 'eco_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_EcoTemperature', $this->Translate('Eco Temperature'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_EcoTemperature');
                            }
                            break;
                        case 'open_window_temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_OpenWindowTemperature', $this->Translate('Open Window Temperature'), '~Temperature');
                            $this->EnableAction('ZWAVE2M_OpenWindowTemperature');
                            break;
                        case 'holiday_temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_HolidayTemperature', $this->Translate('Holiday Temperature'), '~Temperature');
                            $this->EnableAction('ZWAVE2M_HolidayTemperature');
                            break;
                        case 'position':
                            $this->RegisterVariableInteger('ZWAVE2M_Position', $this->Translate('Position'), '~Shutter');
                            break;
                        case 'position_left':
                            $this->RegisterVariableInteger('ZWAVE2M_PositionLeft', $this->Translate('Position Left'), '~Shutter');
                            break;
                        case 'position_right':
                            $this->RegisterVariableInteger('ZWAVE2M_PositionRight', $this->Translate('Position Right'), '~Shutter');
                            break;
                        case 'boost_heating_countdown':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_BoostHeatingCountdown', $this->Translate('Boost Heating Countdown'), 'Z2M.Minutes');
                            }
                            break;
                        case 'away_preset_days':
                            $this->RegisterVariableInteger('ZWAVE2M_AwayPresetDays', $this->Translate('Away Preset Days'), '');
                            $this->EnableAction('ZWAVE2M_AwayPresetDays');
                            break;
                        case 'boost_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_BoostTime', $this->Translate('Boost Time'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_BoostTime');
                            }
                            break;
                        case 'boost_timeset_countdown':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_BoostTimesetCountdown', $this->Translate('Boost Time'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_BoostTimesetCountdown');
                            }
                            break;
                        case 'comfort_temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_ComfortTemperature', $this->Translate('Comfort Temperature'), '~Temperature.Room');
                            $this->EnableAction('ZWAVE2M_ComfortTemperature');
                            break;
                        case 'eco_temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_EcoTemperature', $this->Translate('Eco Temperature'), '~Temperature.Room');
                            $this->EnableAction('ZWAVE2M_EcoTemperature');
                            break;
                        case 'away_preset_temperature':
                            $this->RegisterVariableFloat('ZWAVE2M_AwayPresetTemperature', $this->Translate('Away Preset Temperature'), '~Temperature.Room');
                            $this->EnableAction('ZWAVE2M_AwayPresetTemperature');
                            break;
                        case 'current_heating_setpoint_auto':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_CurrentHeatingSetpointAuto', $this->Translate('Current Heating Setpoint Auto'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_CurrentHeatingSetpointAuto');
                            }
                            break;
                        case 'overload_protection':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_OverloadProtection', $this->Translate('Overload Protection'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_OverloadProtection');
                            }
                            break;
                        case 'calibration_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_CalibrationTime', $this->Translate('Calibration Time'), $ProfileName);
                            }
                            break;
                        case 'calibration_time_left':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_CalibrationTimeLeft', $this->Translate('Calibration Time Left'), $ProfileName);
                            }
                            break;
                        case 'calibration_time_right':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_CalibrationTimeRight', $this->Translate('Calibration Time Right'), $ProfileName);
                            }
                            break;
                        case 'soil_moisture':
                                $this->RegisterVariableInteger('ZWAVE2M_SoilMoisture', $this->Translate('Soil Moisture'), '~Intensity.100');
                            break;
                        case 'action_angle':
                            $this->RegisterVariableInteger('ZWAVE2M_ActionAngle', $this->Translate('Action angle'), '');
                            break;
                        case 'action_from_side':
                            $this->RegisterVariableInteger('ZWAVE2M_ActionFromSide', $this->Translate('Action from side'), '');
                            break;
                        case 'action_side':
                            $this->RegisterVariableInteger('ZWAVE2M_ActionSide', $this->Translate('Action side'), '');
                            break;
                        case 'action_to_side':
                            $this->RegisterVariableInteger('ZWAVE2M_ActionToSide', $this->Translate('Action to side'), '');
                            break;
                        case 'motion_speed':
                            $this->RegisterVariableInteger('ZWAVE2M_MotionSpeed', $this->Translate('Motionspeed'), '');
                            break;
                        case 'radar_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_RadarSensitivity', $this->Translate('Radar Sensitivity'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_RadarSensitivity');
                            }
                            break;
                        case 'action_duration':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_ActionDuration', $this->Translate('Action Duration'), $ProfileName);
                            }
                            break;
                        case 'percent_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_PercentState', $this->Translate('PercentState'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_PercentState');
                            }
                            break;
                        case 'target_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_TargetDistance', $this->Translate('Target Distance'), $ProfileName);
                            }
                            break;
                        case 'minimum_range':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_MinimumRange', $this->Translate('Minimum Range'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MinimumRange');
                            }
                            break;
                        case 'maximum_range':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_MaximumRange', $this->Translate('Maximum Range'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MaximumRange');
                            }
                            break;
                        case 'deadzone_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_DeadzoneTemperature', $this->Translate('Deadzone Temperature'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DeadzoneTemperature');
                            }
                            break;
                        case 'max_temperature_limit':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_MaxTemperatureLimit', $this->Translate('Max Temperature Limit'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_MaxTemperatureLimit');
                            }
                            break;
                        case 'detection_delay':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_DetectionDelay', $this->Translate('Detection Delay'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DetectionDelay');
                            }
                            break;
                        case 'fading_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('ZWAVE2M_FadingTime', $this->Translate('Fading Time'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_FadingTime');
                            }
                            break;
                        case 'detection_interval':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->registerVariableFloat('ZWAVE2M_DetectionInterval', $this->Translate('Detection Interval'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_DetectionInterval');
                            }
                            break;
                        case 'action_code':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->registerVariableFloat('ZWAVE2M_ActionCode', $this->Translate('Action Code'), $ProfileName);
                            }
                            break;
                        case 'action_transaction':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->registerVariableFloat('ZWAVE2M_ActionTransaction', $this->Translate('Action Transaction'), $ProfileName);
                            }
                            break;
                        case 'brightness_white':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('ZWAVE2M_BrightnessWhite', $this->Translate('Brightness White'), $ProfileName);
                                $this->EnableAction('ZWAVE2M_BrightnessWhite');
                            }
                            break;
                        default:
                            $missedVariables[] = $expose;
                            break;
                    }
                    break; //numeric break
                case 'composite':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'strobe':
                                            $this->RegisterVariableBoolean('ZWAVE2M_Strobe', $this->Translate('Strobe'), '~Switch');
                                            $this->EnableAction('ZWAVE2M_Strobe');
                                            break;
                                        default:
                                            // Default composite binary
                                            $missedVariables['composite'][] = $feature;
                                            break;
                                    }
                                    break; //Composite binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'strobe_duty_cycle':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('ZWAVE2M_StrobeDutyCycle', $this->Translate('Strobe Duty Cycle'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_StrobeDutyCycle');
                                            break;
                                        case 'duration':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableFloat('ZWAVE2M_Duration', $this->Translate('Duration'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_Duration');
                                            break;
                                        case 'motor_speed':
                                            $this->RegisterVariableInteger('ZWAVE2M_MotorSpeed', $this->Translate('Motor Speed'), '~Intensity.255');
                                            $this->EnableAction('ZWAVE2M_MotorSpeed');
                                            break;
                                        default:
                                            // Default composite binary
                                            $missedVariables['composite'][] = $feature;
                                            break;
                                    }
                                    break; //Composite numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'mode':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_Mode', $this->Translate('Mode'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_Mode');
                                            break;
                                        case 'week':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_Week', $this->Translate('Woche'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_Week');
                                            break;
                                        case 'level':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_Level', $this->Translate('Level'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_Level');
                                            break;
                                        case 'strobe_level':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_StrobeLevel', $this->Translate('Strobe Level'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_StrobeLevel');
                                            break;
                                        default:
                                            // Default composite enum
                                            $missedVariables['composite'][] = $feature;
                                            break;
                                    }
                                    break; //Composite enum break;
                            }
                        }
                    }
                    break; //Composite break
                case 'cover':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        default:
                                            // Default cover binary
                                            $missedVariables['cover'][] = $feature;
                                            break;
                                    }
                                    break; //Cover binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'position':
                                            $this->RegisterVariableInteger('ZWAVE2M_Position', $this->Translate('Position'), '~Intensity.100');
                                            $this->EnableAction('ZWAVE2M_Position');
                                            break;
                                        case 'position_left':
                                            $this->RegisterVariableInteger('ZWAVE2M_PositionLeft', $this->Translate('Position Left'), '~Intensity.100');
                                            $this->EnableAction('ZWAVE2M_PositionLeft');
                                            break;
                                        case 'position_right':
                                            $this->RegisterVariableInteger('ZWAVE2M_PositionRight', $this->Translate('Position Right'), '~Intensity.100');
                                            $this->EnableAction('ZWAVE2M_PositionRight');
                                            break;
                                        default:
                                            // Default cover binary
                                            $missedVariables['cover'][] = $feature;
                                            break;
                                    }
                                    break; //Cover numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'state':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_State', $this->Translate('State'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_State');
                                            break;
                                        case 'state_left':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_state_left', $this->Translate('State Left'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_state_left');
                                            break;
                                        case 'state_right':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('ZWAVE2M_state_right', $this->Translate('State Right'), $ProfileName);
                                            }
                                            $this->EnableAction('ZWAVE2M_state_right');
                                            break;
                                        default:
                                            // Default cover enum
                                            $missedVariables['cover'][] = $feature;
                                            break;
                                    }
                                    break; //Cover enum break;
                            }
                        }
                    }
                    break; //Cover break
                default: // Expose Type default
                    break;
            }
        }
        $this->SendDebug(__FUNCTION__ . ':: Missed Exposes', json_encode($missedVariables), 0);
    }
}
