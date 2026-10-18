<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper.php';
require_once __DIR__ . '/../libs/rest.php';

class Volumio extends IPSModuleStrict
{
    use VoluminoHelper;
    use VolumioREST;

    private const COVER_MAX_BYTES = 5242880;
    private const COVER_TILE_SIZE = 400;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        //Der übergeordnete MQTT-Server/-Client wird bei IPSModuleStrict automatisch über die parentRequirements
        //in der module.json verbunden.
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyBoolean('SendToGroup', false);
        $this->RegisterPropertyString('GroupID', '');
        $this->RegisterAttributeString('CoverURL', '');
        $this->RegisterAttributeString('CoverMime', '');
        $this->RegisterAttributeString('RadioStations', '[]');
        $this->RegisterAttributeString('Playlists', '[]');

        $this->RegisterVariableString('Volumio_Title', $this->Translate('Title'), $this->textPresentation());
        $this->RegisterVariableString('Volumio_Artist', $this->Translate('Artist'), $this->textPresentation());
        $this->RegisterVariableString('Volumio_Album', $this->Translate('Album'), $this->textPresentation());
        $this->RegisterVariableInteger('Volumio_Position', $this->Translate('Position'), $this->durationPresentation());
        $this->RegisterVariableInteger('Volumio_Duration', $this->Translate('Duration'), $this->durationPresentation());
        $this->RegisterVariableInteger('Volumio_Volume', $this->Translate('Volume'), $this->volumePresentation());
        $this->RegisterVariableInteger('Volumio_VolumeUpDown', $this->Translate('Volume (Up/Down)'), $this->volumeUpDownPresentation());
        $this->RegisterVariableBoolean('Volumio_Mute', $this->Translate('Mute'), $this->mutePresentation());
        $this->RegisterVariableInteger('Volumio_Controls', $this->Translate('Controls'), $this->controlsPresentation());
        $this->RegisterVariableBoolean('Volumio_Repeat', $this->Translate('Repeat'), $this->switchPresentation());
        $this->RegisterVariableBoolean('Volumio_Random', $this->Translate('Random'), $this->switchPresentation());
        $this->RegisterVariableBoolean('Volumio_Online', $this->Translate('Online'), $this->onlinePresentation());
        $this->RegisterVariableInteger('Volumio_RadioStation', $this->Translate('Radio station'), $this->enumerationPresentation([]));
        $this->RegisterVariableInteger('Volumio_Playlist', $this->Translate('Playlist'), $this->enumerationPresentation([]));

        $this->EnableAction('Volumio_Volume');
        $this->EnableAction('Volumio_VolumeUpDown');
        $this->EnableAction('Volumio_Mute');
        $this->EnableAction('Volumio_Controls');
        $this->EnableAction('Volumio_Repeat');
        $this->EnableAction('Volumio_Random');
        $this->EnableAction('Volumio_RadioStation');
        $this->EnableAction('Volumio_Playlist');

        //Kachel-Visualisierung, unter Symcon 9.0 ohne Vollbildtyp
        $this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') ? INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN : INSTANCE_VISUALIZATION_TYPE_HTML);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $MQTTTopic = rtrim($this->ReadPropertyString('MQTTTopic'), '/');
        if ($MQTTTopic === '') {
            $this->SetReceiveDataFilter('.*NoTopicConfigured.*');
            $this->SetStatus(104);
            return;
        }
        if ($this->ReadPropertyBoolean('SendToGroup') && trim($this->ReadPropertyString('GroupID'), '/ ') === '') {
            $this->SetReceiveDataFilter('.*NoTopicConfigured.*');
            $this->SetStatus(202);
            return;
        }
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '/(status|available).*');
        $this->SetStatus(102);
    }

    public function ReceiveData(string $JSONString): string
    {
        $this->SendDebug('JSON', $JSONString, 0);
        $Base = rtrim($this->ReadPropertyString('MQTTTopic'), '/');
        if ($Base === '') {
            return '';
        }
        $Buffer = json_decode($JSONString, true);
        if (!is_array($Buffer) || !isset($Buffer['Topic'])) {
            return '';
        }
        $Topic = (string) $Buffer['Topic'];
        $prefix = $Base . '/status/';
        if ($Topic !== $Base . '/available' && strpos($Topic, $prefix) !== 0) {
            return '';
        }
        //Bei IPSModuleStrict ist der Payload HEX-kodiert.
        $Payload = hex2bin((string) ($Buffer['Payload'] ?? ''));
        if ($Payload === false) {
            $this->SendDebug('Payload', 'Ungültiger Payload (kein HEX)', 0);
            return '';
        }
        $this->SendDebug($Topic, $Payload, 0);
        if ($Topic === $Base . '/available') {
            $this->SetValue('Volumio_Online', $Payload === 'online');
            $this->UpdateVisualizationValue(json_encode(['online' => $Payload === 'online']));
            return '';
        }

        switch (substr($Topic, strlen($prefix))) {
            case 'state':
                $controls = ['play' => 2, 'pause' => 3, 'stop' => 4];
                if (isset($controls[$Payload])) {
                    $this->SetValue('Volumio_Controls', $controls[$Payload]);
                    $this->UpdateVisualizationValue(json_encode(['state' => $Payload]));
                }
                break;
            case 'title':
                $this->SetValue('Volumio_Title', $Payload);
                $this->UpdateVisualizationValue(json_encode(['title' => $Payload]));
                break;
            case 'artist':
                $this->SetValue('Volumio_Artist', $Payload);
                $this->UpdateVisualizationValue(json_encode(['artist' => $Payload]));
                break;
            case 'album':
                $this->SetValue('Volumio_Album', $Payload);
                $this->UpdateVisualizationValue(json_encode(['album' => $Payload]));
                break;
            case 'albumart':
                if ($this->updateCover($Payload)) {
                    $this->UpdateVisualizationValue(json_encode(['cover' => $this->coverDataURI()]));
                }
                break;
            case 'volume':
                if (is_numeric($Payload)) {
                    $this->SetValue('Volumio_Volume', (int) $Payload);
                    $this->UpdateVisualizationValue(json_encode(['volume' => (int) $Payload]));
                }
                break;
            case 'mute':
                $this->SetValue('Volumio_Mute', $Payload === 'true');
                $this->UpdateVisualizationValue(json_encode(['mute' => $Payload === 'true']));
                break;
            case 'repeat':
                $this->SetValue('Volumio_Repeat', $Payload === 'true');
                $this->UpdateVisualizationValue(json_encode(['repeat' => $Payload === 'true']));
                break;
            case 'random':
                $this->SetValue('Volumio_Random', $Payload === 'true');
                $this->UpdateVisualizationValue(json_encode(['random' => $Payload === 'true']));
                break;
            case 'seek':
                if (is_numeric($Payload)) {
                    $Seconds = intdiv((int) $Payload, 1000);
                    $this->SetValue('Volumio_Position', $Seconds);
                    $this->UpdateVisualizationValue(json_encode(['position' => $Seconds]));
                }
                break;
            case 'duration':
                if (is_numeric($Payload)) {
                    $Seconds = intdiv((int) $Payload, 1000);
                    $this->SetValue('Volumio_Duration', $Seconds);
                    $this->UpdateVisualizationValue(json_encode(['duration' => $Seconds]));
                }
                break;
        }
        return '';
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Volumio_Volume':
                $this->Volume((int) $Value);
                break;
            case 'Volumio_VolumeUpDown':
                switch ((int) $Value) {
                    case 1:
                        $this->VolumeUp();
                        break;
                    case 2:
                        $this->VolumeDown();
                        break;
                }
                break;
            case 'Volumio_Mute':
                $this->Mute((bool) $Value);
                break;
            case 'Volumio_Repeat':
                $this->Repeat((bool) $Value);
                break;
            case 'Volumio_Random':
                $this->Random((bool) $Value);
                break;
            case 'Volumio_Controls':
                switch ((int) $Value) {
                    case 1:
                        $this->Prev();
                        break;
                    case 2:
                        $this->Play();
                        break;
                    case 3:
                        $this->Pause();
                        break;
                    case 4:
                        $this->Stop();
                        break;
                    case 5:
                        $this->Next();
                        break;
                }
                break;
            case 'Volumio_RadioStation':
                $this->PlayRadio((int) $Value);
                break;
            case 'Volumio_Playlist':
                $Playlists = json_decode($this->ReadAttributeString('Playlists'), true);
                $Index = (int) $Value - 1;
                if (is_array($Playlists) && isset($Playlists[$Index])) {
                    $this->PlayPlaylist((string) $Playlists[$Index]);
                    $this->SetValue('Volumio_Playlist', (int) $Value);
                }
                break;
            case 'Browse':
                //Aus der Kachel: Ebene der Mediathek laden und an die Kachel zurückgeben.
                $this->UpdateVisualizationValue(json_encode(['browse' => $this->browse((string) $Value)]));
                break;
            case 'PlayItem':
                //Aus der Kachel: JSON {"service","uri","title","type"}
                $Item = json_decode((string) $Value, true);
                if (is_array($Item) && isset($Item['uri'])) {
                    $this->PlayLibraryItem((string) ($Item['service'] ?? ''), (string) $Item['uri'], (string) ($Item['title'] ?? ''), (string) ($Item['type'] ?? ''));
                }
                break;
            case 'UpdateLists':
                echo $this->Translate($this->UpdateLists() ? 'Lists updated.' : 'Lists could not be loaded. Check the Volumio host.');
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Unbekannter Ident: ' . $Ident, 0);
        }
    }

    public function GetVisualizationTile(): string
    {
        $Html = (string) file_get_contents(__DIR__ . '/module.html');
        return $Html . '<script>handleMessage(' . json_encode(json_encode($this->tileData())) . ');</script>';
    }

    //Lädt das Cover von der gemeldeten URL und legt es als Medienobjekt ab. Gibt true zurück, wenn sich das Cover geändert hat.
    private function updateCover(string $URL): bool
    {
        $URL = trim($URL);
        if ($URL === '' || $URL === $this->ReadAttributeString('CoverURL')) {
            return false;
        }
        $Absolute = $this->absoluteURL($URL);
        if ($Absolute === '') {
            $this->SendDebug('Cover', 'Relative URL ohne Host: ' . $URL, 0);
            return false;
        }
        $Context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $Data = @file_get_contents($Absolute, false, $Context, 0, self::COVER_MAX_BYTES + 1);
        if ($Data === false || $Data === '' || strlen($Data) > self::COVER_MAX_BYTES) {
            $this->SendDebug('Cover', 'Download fehlgeschlagen oder zu groß: ' . $Absolute, 0);
            return false;
        }
        $Info = @getimagesizefromstring($Data);
        if ($Info === false) {
            $this->SendDebug('Cover', 'Kein gültiges Bild: ' . $Absolute, 0);
            return false;
        }
        $Extensions = [IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        $Extension = $Extensions[$Info[2]] ?? 'jpg';

        $ID = @$this->GetIDForIdent('Volumio_Cover');
        if (!$ID) {
            $ID = IPS_CreateMedia(MEDIATYPE_IMAGE);
            IPS_SetParent($ID, $this->InstanceID);
            IPS_SetIdent($ID, 'Volumio_Cover');
            IPS_SetName($ID, $this->Translate('Cover'));
        }
        IPS_SetMediaFile($ID, 'media/Volumio_' . $this->InstanceID . '.' . $Extension, false);
        IPS_SetMediaContent($ID, base64_encode($Data));
        $this->WriteAttributeString('CoverURL', $URL);
        $this->WriteAttributeString('CoverMime', (string) $Info['mime']);
        return true;
    }

    private function absoluteURL(string $URL): string
    {
        //Nicht URL-sichere Zeichen (zum Beispiel Leerzeichen in Radio-Namen) kodieren, bereits kodierte bleiben erhalten.
        $URL = preg_replace_callback('/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', fn (array $Match): string => rawurlencode($Match[0]), $URL);
        if (preg_match('#^https?://#i', $URL)) {
            return $URL;
        }
        //Host ohne Schema und ohne abschließenden Schrägstrich, auch wenn "http://10.0.0.5/" eingetragen wurde.
        $Host = rtrim(preg_replace('#^https?://#i', '', trim($this->ReadPropertyString('Host'))), '/');
        if ($Host !== '' && $URL[0] === '/') {
            return 'http://' . $Host . $URL;
        }
        return '';
    }

    //Cover als Data-URI für die Kachel, verkleinert, wenn die GD-Erweiterung vorhanden ist.
    private function coverDataURI(): string
    {
        $ID = @$this->GetIDForIdent('Volumio_Cover');
        if (!$ID) {
            return '';
        }
        $Data = base64_decode(IPS_GetMediaContent($ID), true);
        if ($Data === false || $Data === '') {
            return '';
        }
        $Mime = $this->ReadAttributeString('CoverMime') ?: 'image/jpeg';
        if (function_exists('imagecreatefromstring')) {
            $Source = @imagecreatefromstring($Data);
            if ($Source !== false) {
                $Width = imagesx($Source);
                $Height = imagesy($Source);
                $Scale = min(1, self::COVER_TILE_SIZE / max($Width, $Height));
                $Target = imagecreatetruecolor(max(1, (int) round($Width * $Scale)), max(1, (int) round($Height * $Scale)));
                imagecopyresampled($Target, $Source, 0, 0, 0, 0, imagesx($Target), imagesy($Target), $Width, $Height);
                ob_start();
                imagejpeg($Target, null, 80);
                $Data = (string) ob_get_clean();
                $Mime = 'image/jpeg';
            }
        }
        return 'data:' . $Mime . ';base64,' . base64_encode($Data);
    }

    private function tileData(): array
    {
        $Controls = $this->GetValue('Volumio_Controls');
        $States = [2 => 'play', 3 => 'pause', 4 => 'stop'];
        return [
            'title'    => $this->GetValue('Volumio_Title'),
            'artist'   => $this->GetValue('Volumio_Artist'),
            'album'    => $this->GetValue('Volumio_Album'),
            'state'    => $States[$Controls] ?? 'stop',
            'volume'   => $this->GetValue('Volumio_Volume'),
            'mute'     => $this->GetValue('Volumio_Mute'),
            'repeat'   => $this->GetValue('Volumio_Repeat'),
            'random'   => $this->GetValue('Volumio_Random'),
            'position' => $this->GetValue('Volumio_Position'),
            'duration' => $this->GetValue('Volumio_Duration'),
            'online'   => $this->GetValue('Volumio_Online'),
            'cover'    => $this->coverDataURI(),
            'labels'   => [
                'tabPlayer'  => $this->Translate('Player'),
                'tabRadio'   => $this->Translate('Radio'),
                'tabLibrary' => $this->Translate('Library'),
                'back'       => $this->Translate('Back'),
                'play_item'  => $this->Translate('Play'),
                'noList'     => $this->Translate('No entries. Check the Volumio host in the instance.'),
                'loading'    => $this->Translate('Loading...'),
                'previous'   => $this->Translate('Prev'),
                'play'       => $this->Translate('Play'),
                'pause'      => $this->Translate('Pause'),
                'next'       => $this->Translate('Next'),
                'mute'       => $this->Translate('Mute'),
                'repeat'     => $this->Translate('Repeat'),
                'random'     => $this->Translate('Random'),
                'volume'     => $this->Translate('Volume'),
                'offline'    => $this->Translate('Offline'),
                'nothing'    => $this->Translate('Nothing playing')
            ]
        ];
    }

    private function textPresentation(): array
    {
        return ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION];
    }

    private function durationPresentation(): array
    {
        return ['PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'COUNTDOWN_TYPE' => 0, 'FORMAT' => 1, 'MILLISECONDS' => false];
    }

    private function volumePresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'          => 0,
            'MAX'          => 100,
            'STEP_SIZE'    => 1,
            'SUFFIX'       => ' %'
        ];
    }

    private function mutePresentation(): array
    {
        return ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH, 'USAGE_TYPE' => 1];
    }

    private function switchPresentation(): array
    {
        return ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH];
    }

    private function onlinePresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => json_encode([
                ['Value' => false, 'Caption' => 'Offline', 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 16711680, 'ContentColorActive' => false, 'ContentColorValue' => -1],
                ['Value' => true, 'Caption' => 'Online', 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 65280, 'ContentColorActive' => false, 'ContentColorValue' => -1]
            ])
        ];
    }

    private function volumeUpDownPresentation(): array
    {
        return $this->enumerationPresentation([1 => 'Up', 2 => 'Down']);
    }

    private function controlsPresentation(): array
    {
        return $this->enumerationPresentation([1 => 'Prev', 2 => 'Play', 3 => 'Pause', 4 => 'Stop', 5 => 'Next']);
    }

    private function enumerationPresentation(array $Options): array
    {
        $List = [];
        foreach ($Options as $Value => $Caption) {
            $List[] = ['Value' => $Value, 'Caption' => $this->Translate($Caption), 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
        }
        return ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($List)];
    }
}
