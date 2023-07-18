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

    public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);
        
        $this->zwaveConfig = Array(
			Array(  
                "ident" => "ZWAVE2M_LastActive", 	    
                "caption" => "Last Active", 
                "description" => "timestamp of last communication",
                "sortOrder" => 11,
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
                "sortOrder" => 10,
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
                "sortOrder" => 1, 				
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
                "sortOrder" => 2, 				
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
                "sortOrder" => 3, 				
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
                "sortOrder" => 4, 				
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
                "sortOrder" => 30, 				
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
                "sortOrder" => 31,				
                "type" => "Boolean", 	
                "profile" => "~Lock", 			
                "topic" => '117/0/local', 			
                "extractor" => "intToBoolean", 	
                "writeable" => true
            ),
            Array(  
                "ident" => "ZWAVE2M_SceneID", 	
                "caption" => "Scene ID",
                "description" => "Scene Activation v0",
                "sortOrder" => 12, 				
                "type" => "Integer", 	
                "profile" => "", 			
                "topic" => '43/0/sceneId', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(  
                "ident" => "ZWAVE2M_SceneID1", 	
                "caption" => "Scene ID 1",
                "description" => "Central Scene v2 ch 1",
                "sortOrder" => 12, 				
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
                "sortOrder" => 13, 				
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
                "sortOrder" => 14, 				
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
                "sortOrder" => 16, 				
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
                "sortOrder" => 15, 				
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
                "sortOrder" => 16, 				
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
                "sortOrder" => 17, 				
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
                "sortOrder" => 28, 				
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
                "sortOrder" => 29, 				
                "type" => "Boolean", 	
                "profile" => "~Alert",
                "topic" => '128/0/isLow', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_WakeupInterval", 	
                "caption" => "Wakeup Interval",
                "description" => "Battery device wakeup interval",
                "sortOrder" => 49, 				
                "type" => "Integer", 	
                "profile" => "",
                "topic" => '132/0/wakeUpInterval', 			
                "extractor" => "copyValue", 	
                "writeable" => true
            ),
            Array(
                "ident" => "ZWAVE2M_AlertOverHeat", 	
                "caption" => "Overheat Alert",
                "description" => "Notification v5 heat alert",
                "sortOrder" => 18, 				
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification04",
                "topic" => '113/1/Heat_Alarm/Heat_sensor_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_AlertOverCurrent", 	
                "caption" => "Over-current Alert",
                "description" => "Notification v5 over-current alert", 		
                "sortOrder" => 19,		
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification08",
                "topic" => '113/1/Power_Management/Over-current_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_AlertLoad", 	
                "caption" => "Load Alert",
                "description" => "Notification v5 load alert", 
                "sortOrder" => 20,
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification08",
                "topic" => '113/1/Power_Management/Load_error_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_AlertOverLoad", 	
                "caption" => "Over-load Alert",
                "description" => "Notification v5 over-load alert",
                "sortOrder" => 21,
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification08",
                "topic" => '113/1/Power_Management/Over-Load_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            ),
            Array(
                "ident" => "ZWAVE2M_AlertHardwareStatus", 	
                "caption" => "Hardware Status Alert",
                "description" => "Notification v5 hardware status alert",
                "sortOrder" => 22, 				
                "type" => "Integer", 	
                "profile" => "~ZWaveNotification09",
                "topic" => '113/1/System/Hardware_status', 			
                "extractor" => "copyValue", 	
                "writeable" => false
            )
		);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zwave');
        $this->RegisterPropertyString('MQTTTopic', '');

        /* Sort orders: 
          01 - 09: Actions
          10 - 29: Alerts & Status
          30 - 49: Config

        */
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
        
        /* Don't load data by default
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            
            $this->getDeviceInfo();
        }
        */

        $this->SetStatus(102);
    }

    protected function getConfigTopics() {

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

    public function getDeviceInfo()
    {

        $allMqttServers = IPS_GetInstanceListByModuleID('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $mqttInstance = $allMqttServers[0];
        // $this->SendDebug('Parent Instance', $mqttInstance, 0);
        $allTopics = MQTT_GetRetainedMessageTopicList($mqttInstance);
        
        $baseTopic = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/';
        $this->SendDebug('DEVICE INFO', 'Base Topic: ' . $baseTopic, 0);

        $deviceTopics = Array();
        foreach ($allTopics as $currentTopic) {

            $filterRegex = '/^' . $this->ReadPropertyString('MQTTBaseTopic') . '\/' . $this->ReadPropertyString('MQTTTopic') . '\/*/';
            if (preg_match($filterRegex, $currentTopic) ) {
            
                $deviceTopics[] = $currentTopic;
            }
        }
        $this->SendDebug('DEVICE INFO', 'Number of retained topics: ' . count($deviceTopics), 0);
        
        foreach ($deviceTopics as $currentDeviceTopic) {

            $subTopic = str_replace($baseTopic, "", $currentDeviceTopic);
            $topicConfiguration = $this->getConfigItemForTopic($subTopic);

            if ($topicConfiguration) {

                $this->SendDebug('TOPIC CONFIGURATION', "Topic " . $topicConfiguration['topic'] . " indicates support for " . $topicConfiguration['description'], 0);

                // Configuration has been found. Proceeding with registering the variables
                switch ($topicConfiguration['type']) {

                    case "Boolean":
                        $this->RegisterVariableBoolean($topicConfiguration['ident'], $this->Translate($topicConfiguration['caption']), $topicConfiguration['profile'], $topicConfiguration['sortOrder']);
                        break;
                    case "Integer":
                        $this->RegisterVariableInteger($topicConfiguration['ident'], $this->Translate($topicConfiguration['caption']), $topicConfiguration['profile'], $topicConfiguration['sortOrder']);
                        break;
                    case "Float":
                        $this->RegisterVariableFloat($topicConfiguration['ident'], $this->Translate($topicConfiguration['caption']), $topicConfiguration['profile'], $topicConfiguration['sortOrder']);
                        break;
                    case "String":
                        $this->RegisterVariableString($topicConfiguration['ident'], $this->Translate($topicConfiguration['caption']), $topicConfiguration['profile'], $topicConfiguration['sortOrder']);
                        break;
                    default:
                        $this->LogMessage('Unknown data type defined. Skipping variable', KL_ERROR);
                }

                // set item active if needed
                if ($topicConfiguration['writeable']) {

                    $this->EnableAction($topicConfiguration['ident']);
                }

                // read the retained data
                $data = $this->fetchRetainedData($currentDeviceTopic);
                if ($data) {

                    switch ($topicConfiguration['extractor']) {

                        case 'copyValue':
                            $this->extractorCopyValue('get', $topicConfiguration['ident'], $data);
                            break;
                        case 'divideBy1000':
                            $this->extractorDivideBy1000('get', $topicConfiguration['ident'], $data);
                            break;
                        case 'intToBoolean':
                            $this->extractorIntToBoolean('get', $topicConfiguration['ident'], $data);
                            break;
                        case 'rgbColor':
                            $this->extractorRgbColor('get', $topicConfiguration['ident'], $data);
                            break;
                        case 'dimIntensity':
                            $configDummy = $this->getConfigItemForTopic($topicConfiguration['topic'] . 'Dummy');
                            $this->extractorDimIntensity('get', $topicConfiguration['ident'], $configDummy['ident'], $data);
                            break;    

                        default:
                            $this->LogMessage('Receive Data: No handler defined for extractor' . $topicConfiguration['extractor'], KL_ERROR);
                            return;
                    }
                }
            }
            else {

                $this->SendDebug('TOPIC CONFIGURATION', "Topic " . $subTopic . " has no configuration", 0);
            }
        }
    }
}
