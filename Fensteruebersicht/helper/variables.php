<?php

// Declare
declare(strict_types=1);

trait FUEB_variables
{
    /**
     * Determines the Homematic variables automatically.
     */
    public function DetermineHomematicVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $monitoredVariables = [];
        $instances = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
        if (!empty($instances)) {
            $variables = [];
            foreach ($instances as $instance) {
                $children = @IPS_GetChildrenIDs($instance);
                foreach ($children as $child) {
                    $match = false;
                    $object = @IPS_GetObject($child);
                    if ($object['ObjectIdent'] == 'STATE') {
                        $match = true;
                    }
                    if ($match) {
                        // Check for variable
                        if ($object['ObjectType'] == 2) {
                            array_push($variables, ['ID' => $child]);
                        }
                    }
                }
            }
            // Get already listed variables
            $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
            // Add new variables
            $newVariables = array_diff(array_column($variables, 'ID'), array_column($monitoredVariables, 'ID'));
            if (!empty($newVariables)) {
                foreach ($newVariables as $variable) {
                    $name = strstr(@IPS_GetName(@IPS_GetParent($variable)), ':', true);
                    if (!$name) {
                        $name = @IPS_GetName(@IPS_GetParent($variable));
                    }
                    array_push($monitoredVariables, [
                        'ID'          => $variable,
                        'Description' => $name,
                        'Profile'     => 0]);
                }
            }
        }
        // Sort variables by name
        usort($monitoredVariables, function ($a, $b)
        {
            return $a['Name'] <=> $b['Name'];
        });
        // Rebase array
        $monitoredVariables = array_values($monitoredVariables);
        // Update variable list
        IPS_SetProperty($this->InstanceID, 'MonitoredVariables', json_encode($monitoredVariables));
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        $this->ReloadConfiguration();
        echo 'Die Variablen wurden ermittelt!';
    }
}
