<?php

declare(strict_types=1);

trait VoluminoHelper
{
    public function Mute(bool $value): void
    {
        $this->SendCommand('mute', $value ? 'true' : 'false');
    }

    public function Volume(int $value): void
    {
        $this->SendCommand('volume', (string) max(0, min(100, $value)));
    }

    public function VolumeUp(): void
    {
        $this->SendCommand('volumeup');
    }

    public function VolumeDown(): void
    {
        $this->SendCommand('volumedown');
    }

    public function Stop(): void
    {
        $this->SendCommand('stop');
    }

    public function Pause(): void
    {
        $this->SendCommand('pause');
    }

    public function Play(): void
    {
        $this->SendCommand('play');
    }

    public function Next(): void
    {
        $this->SendCommand('next');
    }

    public function Prev(): void
    {
        $this->SendCommand('previous');
    }

    public function Seek(int $value): void
    {
        $this->SendCommand('seek', (string) max(0, $value));
    }

    public function Repeat(bool $value): void
    {
        $this->SendCommand('repeat', $value ? 'true' : 'false');
    }

    public function Random(bool $value): void
    {
        $this->SendCommand('random', $value ? 'true' : 'false');
    }

    public function Clear(): void
    {
        $this->SendCommand('clear');
    }

    public function Toggle(): void
    {
        $this->SendCommand('toggle');
    }

    public function Unmute(): void
    {
        $this->SendCommand('unmute');
    }

    //Basis-Topic des Plugins: MQTT Topic ohne die letzte Ebene (Geräte-ID), zum Beispiel "volumio" bei "volumio/wohnzimmer".
    protected function GroupBaseTopic(): string
    {
        $Topic = rtrim($this->ReadPropertyString('MQTTTopic'), '/');
        $Pos = strrpos($Topic, '/');
        return $Pos === false ? '' : substr($Topic, 0, $Pos);
    }

    //Ziel-Topic der Befehle: Gerät (Standard) oder, wenn aktiviert, die Gruppe "<Basis-Topic>/group/<Gruppen-ID>".
    protected function CommandTopic(): string
    {
        $GroupID = trim($this->ReadPropertyString('GroupID'), '/ ');
        if ($this->ReadPropertyBoolean('SendToGroup') && $GroupID !== '') {
            $Base = $this->GroupBaseTopic();
            return ($Base === '' ? '' : $Base . '/') . 'group/' . $GroupID;
        }
        return rtrim($this->ReadPropertyString('MQTTTopic'), '/');
    }

    //Bei IPSModuleStrict ist der Datenfluss HEX-kodiert: "Payload" wird als bin2hex() der Nutzdaten gesendet.
    protected function SendCommand(string $Command, string $Payload = ''): void
    {
        $Topic = $this->CommandTopic();
        if ($Topic === '') {
            return;
        }
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $Topic . '/set/' . $Command;
        $Data['Payload'] = bin2hex($Payload);
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }
}
