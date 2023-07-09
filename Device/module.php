<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/Zwave2MQTTHelper.php';

class Zwave2MQTTDevice extends IPSModule
{
    use ColorHelper;
    use MQTTHelper;
    use VariableProfileHelper;
    use Zwave2MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zwave');
        $this->RegisterPropertyString('MQTTTopic', '');
        
        $this->zwaveConfig = Array(
			Array(  
                "ident" => "ZWAVE2M_LastActive", 	    
                "caption" => "Last Active", 
                "description" => "timestamp of last communication",			
                "type" => "Integer", 	
                "profile" => "~UnixTimestamp",				
                "topic" => 'lastActive', 		
                "extractor" => "divideBy1000", 	
                "writeable" => false
            ),
			Array(  
                "ident" => "ZWAVE2M_DeviceStatus", 	
                "caption" => "Device Health",
                "description" => "device marked healthy by controller", 				
                "type" => "Boolean", 	
                "profile" => "~Alert.Reversed", 			
                "topic" => 'status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(  
                "ident" => "ZWAVE2M_Intensity", 	
                "caption" => "Intensity",
                "description" => "Multivelvel Switch v4", 				
                "type" => "Integer", 	
                "profile" => "~Intensity.100", 			
                "topic" => '38/1/currentValue', 			
                "extractor" => "dimIntensity", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_IntensityOnOff", 	
                "caption" => "Status",
                "description" => "Multivelvel Switch v4 Dummy Switch", 				
                "type" => "Boolean", 	
                "profile" => "~Switch", 			
                "topic" => '38/1/currentValueDummy', 			
                "extractor" => "dimIntensityOnOff", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_Switch", 	
                "caption" => "Status",
                "description" => "Binary Switch v1", 				
                "type" => "Boolean", 	
                "profile" => "~Switch", 			
                "topic" => '37/0/currentValue', 			
                "extractor" => "copyValue", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_Color", 	
                "caption" => "Color RGB",
                "description" => "Color Switch v1", 				
                "type" => "Integer", 	
                "profile" => "~HexColor", 			
                "topic" => '51/0/hexColor', 			
                "extractor" => "rgbColor", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_LockRF", 	
                "caption" => "Lock Remote Operations",
                "description" => "Protection v2 remote lock", 				
                "type" => "Boolean", 	
                "profile" => "~Lock", 			
                "topic" => '117/0/rf', 			
                "extractor" => "intToBoolean", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_LockLocal", 	
                "caption" => "Lock Local Operations",
                "description" => "Protection v2 local lock", 				
                "type" => "Boolean", 	
                "profile" => "~Lock", 			
                "topic" => '117/0/local', 			
                "extractor" => "intToBoolean", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_SceneID1", 	
                "caption" => "Scene ID 1",
                "description" => "Central Scene v2 ch 1", 				
                "type" => "Integer", 	
                "profile" => "", 			
                "topic" => '91/0/scene/001', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_SceneID2", 	
                "caption" => "Scene ID 2",
                "description" => "Central Scene v2 ch 2", 				
                "type" => "Integer", 	
                "profile" => "", 			
                "topic" => '91/0/scene/002', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_BinarySensor", 	
                "caption" => "Binary Sensor",
                "description" => "Binary Sensor v1", 				
                "type" => "Boolean", 	
                "profile" => "", 			
                "topic" => '48/0/Any', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_Illuminance", 	
                "caption" => "Illumination",
                "description" => "Multilevel Sensor v8 Illuminance", 				
                "type" => "Integer", 	
                "profile" => "~Illumination",
                "topic" => '49/0/Illuminance', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_AirTemperature", 	
                "caption" => "Air Temperature",
                "description" => "Multilevel Sensor v8 Air Temperature", 				
                "type" => "Float", 	
                "profile" => "~Temperature",
                "topic" => '49/0/Air_temperature', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_MotionSensor", 	
                "caption" => "Motion Sensor",
                "description" => "Notificaton v5 Motion sensor", 				
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification07",
                "topic" => '113/0/Home_Security/Motion_sensor_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_CoverSensor", 	
                "caption" => "Cover Sensor",
                "description" => "Notificaton v5 Cover sensor", 				
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification07",
                "topic" => '113/0/Home_Security/Cover_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_BatteryLevel", 	
                "caption" => "Battery Level",
                "description" => "Battery v2 level", 				
                "type" => "Integer", 	
                "profile" => "~Battery.100",
                "topic" => '128/0/level', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_BatteryLow", 	
                "caption" => "Battery Low",
                "description" => "Battery v2 low alert", 				
                "type" => "Boolean", 	
                "profile" => "~Alert",
                "topic" => '128/0/isLow', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            )
		);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $Filter = preg_quote($this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') );
        
        $this->SendDebug('Filter ', '.*' . $Filter . '.*', 0);
        $this->SetReceiveDataFilter('.*' . $Filter . '.*');
        
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            
            $this->getDeviceInfo();
        }
        $this->SetStatus(102);
    }

    protected function getConfigTopics() {

        $this->SendDebug('CONFIG', 'There are ' . count($this->zwaveConfig). ' config items defined', 0);

        $configTopics = Array();
        foreach ($this->zwaveConfig as $currentConfigItem) {
            
            if (in_array('topic', $currentConfigItem)) {

                $configTopics[] = $currentConfigItem['topic'];
            }
        }

        return $configTopics;
    }

    protected function getConfigExtractor($ident) {

        foreach ($this->zwaveConfig as $currentConfigItem) {
            
            if (in_array('ident', $currentConfigItem)) {

                if ($currentConfigItem['ident'] == $ident) {

                    if (in_array('extractor', $currentConfigItem)) {

                        return $currentConfigItem['extractor'];
                    }
                }
            }
        }

        return false;
    }

    protected function getConfigIdentForTopic($topic) {

        foreach ($this->zwaveConfig as $currentConfigItem) {
            
            if (in_array('topic', $currentConfigItem)) {

                if ($currentConfigItem['topic'] == $topic) {

                    if (in_array('ident', $currentConfigItem)) {

                        return $currentConfigItem['ident'];
                    }
                }
            }
        }

        return false;
    }

    protected function getConfigItemForTopic($topic) {

        foreach ($this->zwaveConfig as $currentConfigItem) {
            
            if (in_array('topic', $currentConfigItem)) {

                if ($currentConfigItem['topic'] == $topic) {

                    return $currentConfigItem;
                }
            }
        }

        return false;
    }
}
