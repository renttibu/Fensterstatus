<?php

// Declare
declare(strict_types=1);

trait FST_messageSink
{
    /**
     * Incomming messages.
     *
     * @param $TimeStamp
     * @param $SenderID
     * @param $Message
     * @param $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            // $Data[0] = actual value
            // $Data[1] = difference to last value
            // $Data[2] = last value
            case VM_UPDATE:
                $this->UpdateWindowStatus();
                break;

        }
    }

    //#################### Private

    /**
     * Unregisters the variables from message sink.
     */
    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
            }
        }
    }

    /**
     * Registers the variables for message sink.
     */
    private function RegisterMessages(): void
    {
        // Unregister first
        $this->UnregisterMessages();
        // Register first sensor
        $id = $this->ReadPropertyInteger('Sensor1');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Register second sensor
        $id = $this->ReadPropertyInteger('Sensor2');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
    }
}