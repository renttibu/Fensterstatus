<?php

/*
 * @module      Fensterstatus
 *
 * @prefix      FST
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.00-3
 * @date        2020-05-29, 18:00, 1590771600
 * @review      2020-05-29, 18:00
 *
 * @see         https://github.com/ubittner/Komfort-Rollladensteuerung
 *
 * @guids       Library
 *              {0E014EC3-EF16-44D9-1D23-D71B7F1DC287}
 *
 *              Fensterstatus
 *             	{5FEFEBA6-CA4E-A318-C9F4-2FDCD48A6041}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Fensterstatus extends IPSModule
{
    // Helper
    use FST_backupRestore;
    use FST_messageSink;

    /**
     * Creates this instance.
     *
     * @return bool|void
     */
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        // Register properties
        $this->RegisterProperties();
        // Create profiles
        $this->CreateProfiles();
        // Register variables
        $this->RegisterVariables();
    }

    /**
     * Applies the changes of this instance.
     *
     * @return bool|void
     */
    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Never delete this line!
        parent::ApplyChanges();
        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        // Register messages
        $this->RegisterMessages();
        // Update status
        $this->UpdateWindowStatus();
    }

    /**
     * Destroys this instance.
     *
     * @return bool|void
     */
    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        // Delete profiles
        $this->DeleteProfiles();
    }

    /**
     * Reloads the configuration form.
     */
    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    /**
     * Gets the configuration form.
     *
     * @return false|string
     */
    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'));
        $formData->elements[0]->items[3]->caption = "Instanz ID:\t\t" . $this->InstanceID;
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            if (!IPS_ObjectExists($senderID)) {
                foreach ($messageID as $messageType) {
                    $this->UnregisterMessage($senderID, $messageType);
                }
                continue;
            } else {
                $senderName = IPS_GetName($senderID);
                $description = $senderName;
                $parentID = IPS_GetParent($senderID);
                if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                    $description = IPS_GetName($parentID);
                }
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                case [10803]:
                    $messageDescription = 'EM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData->actions[1]->items[0]->values[] = [
                'Description'        => $description,
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription];
        }
        return json_encode($formData);
    }

    /**
     * Updates the window status.
     *
     * @return int
     * 0    = closed
     * 1    = opened
     * 2    = tilted
     */
    public function UpdateWindowStatus(): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $windowStatus = 0;
        if (!$this->CheckForExistingSensors()) {
            return $windowStatus;
        }
        $sensor1Value = intval(GetValue($this->ReadPropertyInteger('Sensor1')));
        $this->SendDebug(__FUNCTION__, 'Sensor 1: ' . json_encode($sensor1Value), 0);
        $sensor2Value = intval(GetValue($sensor2 = $this->ReadPropertyInteger('Sensor2')));
        $this->SendDebug(__FUNCTION__, 'Sensor 1: ' . json_encode($sensor2Value), 0);
        // Closed
        $closedStatus = $this->ReadPropertyInteger('ClosedStatus');
        switch ($closedStatus) {
            //  Sensor 1 = 0, false | Sensor 2 = 0, false
            case 0:
                if ($sensor1Value == 0 && $sensor2Value == 0) {
                    $windowStatus = 0;
                }
                break;

            //  Sensor 1 = 0, false | Sensor 2 = 1, true
            case 1:
                if ($sensor1Value == 0 && $sensor2Value == 1) {
                    $windowStatus = 0;
                }
                break;

            //  Sensor 1 = 1, true | Sensor 2 = 0, false
            case 2:
                if ($sensor1Value == 1 && $sensor2Value == 0) {
                    $windowStatus = 0;
                }
                break;

            //  Sensor 1 = 1, true | Sensor 2 = 1, true
            case 3:
                if ($sensor1Value == 1 && $sensor2Value == 1) {
                    $windowStatus = 0;
                }
                break;
        }
        // Tilted
        $tiltedStatus = $this->ReadPropertyInteger('TiltedStatus');
        switch ($tiltedStatus) {
            //  Sensor 1 = 0, false | Sensor 2 = 0, false
            case 0:
                if ($sensor1Value == 0 && $sensor2Value == 0) {
                    $windowStatus = 1;
                }
                break;

            //  Sensor 1 = 0, false | Sensor 2 = 1, true
            case 1:
                if ($sensor1Value == 0 && $sensor2Value == 1) {
                    $windowStatus = 1;
                }
                break;

            //  Sensor 1 = 1, true | Sensor 2 = 0, false
            case 2:
                if ($sensor1Value == 1 && $sensor2Value == 0) {
                    $windowStatus = 1;
                }
                break;

            //  Sensor 1 = 1, true | Sensor 2 = 1, true
            case 3:
                if ($sensor1Value == 1 && $sensor2Value == 1) {
                    $windowStatus = 1;
                }
                break;
        }
        // Opened
        $openedStatus = $this->ReadPropertyInteger('OpenedStatus');
        switch ($openedStatus) {
            //  Sensor 1 = 0, false | Sensor 2 = 0, false
            case 0:
                if ($sensor1Value == 0 && $sensor2Value == 0) {
                    $windowStatus = 2;
                }
                break;

            //  Sensor 1 = 0, false | Sensor 2 = 1, true
            case 1:
                if ($sensor1Value == 0 && $sensor2Value == 1) {
                    $windowStatus = 2;
                }
                break;

            //  Sensor 1 = 1, true | Sensor 2 = 0, false
            case 2:
                if ($sensor1Value == 1 && $sensor2Value == 0) {
                    $windowStatus = 2;
                }
                break;

            //  Sensor 1 = 1, true | Sensor 2 = 1, true
            case 3:
                if ($sensor1Value == 1 && $sensor2Value == 1) {
                    $windowStatus = 2;
                }
                break;
        }

        switch ($windowStatus) {
            // Opened
            case 1:
                $statusText = 'Geöffnet';
                break;
            // Tilted
            case 2:
                $statusText = 'Gekippt';
                break;

            // Closed
            default:
                $statusText = 'Geschlossen';

        }
        $this->SendDebug(__FUNCTION__, 'Fensterstatus: ' . json_encode($windowStatus) . ', ' . $statusText, 0);
        $this->SetValue('WindowStatus', $windowStatus);
        return $windowStatus;
    }

    //#################### Private

    /**
     * Applies the changes if the kernel is ready.
     */
    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Registers the properties.
     */
    private function RegisterProperties(): void
    {
        // Note
        $this->RegisterPropertyString('Note', '');
        // Sensors
        $this->RegisterPropertyInteger('Sensor1', 0);
        $this->RegisterPropertyInteger('Sensor2', 0);
        // Status
        $this->RegisterPropertyInteger('ClosedStatus', 0);
        $this->RegisterPropertyInteger('TiltedStatus', 2);
        $this->RegisterPropertyInteger('OpenedStatus', 3);
    }

    /**
     * Creates the profiles.
     */
    private function CreateProfiles(): void
    {
        $profile = 'FST.' . $this->InstanceID . '.WindowStatus.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Window');
        IPS_SetVariableProfileValues($profile, 0, 2, 0);
        IPS_SetVariableProfileDigits($profile, 0);
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Gekippt', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'Geöffnet', '', 0xFF0000);
    }

    /**
     * Deletes the profiles of this instance.
     */
    private function DeleteProfiles(): void
    {
        $profiles = ['WindowStatus.Reversed'];
        foreach ($profiles as $profile) {
            $profileName = 'FST.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    /**
     * Registers the variables.
     */
    private function RegisterVariables(): void
    {
        $profile = 'FST.' . $this->InstanceID . '.WindowStatus.Reversed';
        $this->RegisterVariableInteger('WindowStatus', 'Fensterstatus', $profile, 0);
    }

    /**
     * Checks for existing sensors.
     *
     * @return bool
     * false    = no or only one sensor is valid
     * true     = both sensors are valid
     */
    private function CheckForExistingSensors(): bool
    {
        $status = 200;
        $result = false;
        $sensor1 = $this->ReadPropertyInteger('Sensor1');
        $sensor2 = $this->ReadPropertyInteger('Sensor2');
        if ($sensor1 != 0 && IPS_ObjectExists($sensor1) && $sensor2 != 0 && IPS_ObjectExists($sensor2)) {
            $status = 102;
            $result = true;
        } else {
            $this->SendDebug(__FUNCTION__, 'Bitte wählen Sie zwei Sensoren aus!', 0);
            $this->LogMessage('ID: ' . $this->InstanceID . ', Bitte wählen Sie zwei Sensoren aus!', 10204);
        }
        $this->SetStatus($status);
        return $result;
    }
}