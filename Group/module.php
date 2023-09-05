<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';

class Zwave2MQTTGroup extends IPSModule
{
    use ColorHelper;
    use MQTTHelper;
    
    public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID); 
       
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zwave');
        $this->RegisterPropertyString('MQTTTopic', '_CLIENTS/ZWAVE_GATEWAY-zwave-js-ui');
        $this->RegisterPropertyString('NodeList', '');
        $this->RegisterPropertyInteger('CommandClass', 38);
        $this->RegisterPropertyInteger('Endpoint', 1);
        $this->RegisterPropertyString('Property', '');

        $this->RegisterVariableInteger('Intensity','Intensity','~Intensity.100');
        $this->EnableAction('Intensity');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value) {

        if ($Ident == 'Intensity') {

            $payload = Array();
            $arrayNodesString = str_getcsv($this->ReadPropertyString('NodeList'));
            $arrayNodes = Array();
            foreach ($arrayNodesString as $currentNode) {
                array_push($arrayNodes, intval($currentNode));
            }
            $payload['nodes'] = str_getcsv($this->ReadPropertyString('NodeList'));
            $payload['commandClass'] = $this->ReadPropertyInteger('CommandClass');
            $payload['endpoint'] = $this->ReadPropertyInteger('Endpoint');
            $payload['property'] = $this->ReadPropertyString('Property');
            $payload['value'] = $Value;

            $payloadJson = json_encode($payload);
            $this->Z2MSet('multicast', $payloadJson);
        }
    }
}
