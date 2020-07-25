<?php

/*
 * @module      Fensteruebersicht
 *
 * @prefix      FUEB
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
 *              Fensterübersicht
 *             	{F77693E4-F24B-39FA-16A7-C5D1CBD519B3}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Fensteruebersicht extends IPSModule
{
    // Helper
    use FUEB_backupRestore;
    use FUEB_messageSink;
    use FUEB_variables;

    // Constants
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

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
        //$this->RegisterMessages();
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
        $this->RegisterPropertyString('MonitoredVariables', '[]');
    }

    /**
     * Creates the profiles.
     */
    private function CreateProfiles(): void
    {
        $profile = 'FUEB.' . $this->InstanceID . '.WindowStatus.Reversed';
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
            $profileName = 'FUEB.' . $this->InstanceID . '.' . $profile;
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
        // Window status
        $profile = 'FUEB.' . $this->InstanceID . '.WindowStatus.Reversed';
        $this->RegisterVariableInteger('WindowStatus', 'Fensterstatus', $profile, 0);
        // Sensor list
        $this->RegisterVariableString('SensorList', 'Sensoren', 'HTMLBox', 1);
        IPS_SetIcon($this->GetIDForIdent('SensorList'), 'Window');
    }
}