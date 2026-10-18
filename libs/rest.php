<?php

declare(strict_types=1);

//Zugriff auf die REST-API von Volumio (Senderlisten, Wiedergabelisten, Mediathek). Das MQTT-Plugin bietet dafür keine Befehle.
trait VolumioREST
{
    private const REST_TIMEOUT = 5;

    public function UpdateLists(): bool
    {
        $Stations = [];
        $Loaded = false;
        foreach (['radio/myWebRadio', 'radio/favourites'] as $URI) {
            $Navigation = $this->restGet('browse?uri=' . rawurlencode($URI));
            if ($Navigation === null) {
                continue;
            }
            $Loaded = true;
            foreach ($this->navigationItems($Navigation) as $Item) {
                if ($Item['uri'] !== '' && stripos($Item['type'], 'radio') !== false && stripos($Item['type'], 'category') === false) {
                    $Stations[$Item['uri']] = $Item;
                }
            }
        }
        $Playlists = $this->restGet('listplaylists');
        if (!$Loaded && !is_array($Playlists)) {
            return false;
        }
        $Stations = array_values($Stations);
        usort($Stations, fn (array $A, array $B): int => strcasecmp($A['title'], $B['title']));
        $Playlists = is_array($Playlists) ? array_values(array_filter($Playlists, 'is_string')) : [];
        sort($Playlists, SORT_NATURAL | SORT_FLAG_CASE);

        $this->WriteAttributeString('RadioStations', json_encode($Stations));
        $this->WriteAttributeString('Playlists', json_encode($Playlists));
        $this->setListPresentation('Volumio_RadioStation', array_column($Stations, 'title'));
        $this->setListPresentation('Volumio_Playlist', $Playlists);
        return true;
    }

    public function PlayRadio(int $value): void
    {
        $Stations = json_decode($this->ReadAttributeString('RadioStations'), true);
        if (!is_array($Stations) || !isset($Stations[$value - 1])) {
            $this->SendDebug(__FUNCTION__, 'Unbekannter Sender: ' . $value, 0);
            return;
        }
        $Item = $Stations[$value - 1];
        $this->PlayLibraryItem((string) $Item['service'], (string) $Item['uri'], (string) $Item['title'], (string) $Item['type']);
        $this->SetValue('Volumio_RadioStation', $value);
    }

    public function PlayPlaylist(string $value): void
    {
        $this->restGet('commands/?cmd=playplaylist&name=' . rawurlencode($value));
    }

    public function PlayLibraryItem(string $Service, string $URI, string $Title, string $Type = ''): void
    {
        $Item = ['service' => $Service, 'uri' => $URI, 'title' => $Title];
        if ($Type !== '') {
            $Item['type'] = $Type;
        }
        $this->restPost('replaceAndPlay', ['item' => $Item]);
    }

    //Liefert den Inhalt einer Ebene der Volumio-Mediathek als JSON: {"uri":..., "prev":..., "items":[...]}. Leere URI = Hauptebene.
    public function BrowseLibrary(string $URI): string
    {
        return json_encode($this->browse($URI));
    }

    protected function browse(string $URI): array
    {
        $Navigation = $this->restGet('browse?uri=' . rawurlencode($URI === '' ? '/' : $URI));
        $Prev = $Navigation['navigation']['prev']['uri'] ?? null;
        return [
            'uri'   => $URI,
            'prev'  => $Prev,
            'items' => $Navigation === null ? [] : $this->navigationItems($Navigation),
            'error' => $Navigation === null
        ];
    }

    private function restBase(): string
    {
        $Host = rtrim((string) preg_replace('#^https?://#i', '', trim($this->ReadPropertyString('Host'))), '/');
        return $Host === '' ? '' : 'http://' . $Host;
    }

    private function restGet(string $Path): mixed
    {
        return $this->restRequest($Path, 'GET');
    }

    private function restPost(string $Path, array $Body): mixed
    {
        return $this->restRequest($Path, 'POST', json_encode($Body));
    }

    private function restRequest(string $Path, string $Method, string $Content = ''): mixed
    {
        $Base = $this->restBase();
        if ($Base === '') {
            $this->SendDebug('REST', 'Kein Volumio Host konfiguriert', 0);
            return null;
        }
        $Http = ['method' => $Method, 'timeout' => self::REST_TIMEOUT, 'ignore_errors' => true];
        if ($Method === 'POST') {
            $Http['header'] = "Content-Type: application/json\r\n";
            $Http['content'] = $Content;
        }
        $URL = $Base . '/api/v1/' . $Path;
        $Response = @file_get_contents($URL, false, stream_context_create(['http' => $Http]));
        $this->SendDebug('REST ' . $Method, $URL . ($Content !== '' ? ' ' . $Content : '') . ' => ' . substr((string) $Response, 0, 300), 0);
        if ($Response === false) {
            return null;
        }
        $Data = json_decode($Response, true);
        return $Data === null && $Response !== 'null' ? null : $Data;
    }

    //Vereinheitlicht die Antworten von "browse": Einträge ("items") und Quellen der Hauptebene ("name"/"plugin_name").
    private function navigationItems(?array $Navigation): array
    {
        $Items = [];
        foreach ($Navigation['navigation']['lists'] ?? [] as $List) {
            if (isset($List['items']) && is_array($List['items'])) {
                foreach ($List['items'] as $Item) {
                    $Items[] = [
                        'title'    => (string) ($Item['title'] ?? ''),
                        'artist'   => (string) ($Item['artist'] ?? ''),
                        'uri'      => (string) ($Item['uri'] ?? ''),
                        'type'     => (string) ($Item['type'] ?? ''),
                        'service'  => (string) ($Item['service'] ?? ''),
                        'albumart' => $this->absoluteArt((string) ($Item['albumart'] ?? ''))
                    ];
                }
            } elseif (isset($List['name'])) {
                $Items[] = [
                    'title'    => (string) $List['name'],
                    'artist'   => '',
                    'uri'      => (string) ($List['uri'] ?? ''),
                    'type'     => 'source',
                    'service'  => (string) ($List['plugin_name'] ?? ''),
                    'albumart' => $this->absoluteArt((string) ($List['albumart'] ?? ''))
                ];
            }
        }
        return $Items;
    }

    private function absoluteArt(string $URL): string
    {
        if ($URL !== '' && $URL[0] === '/') {
            return $this->restBase() . $URL;
        }
        return $URL;
    }

    private function setListPresentation(string $Ident, array $Titles): void
    {
        $ID = @$this->GetIDForIdent($Ident);
        if (!$ID) {
            return;
        }
        $List = [];
        foreach (array_values($Titles) as $Index => $Title) {
            $List[] = ['Value' => $Index + 1, 'Caption' => (string) $Title, 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
        }
        IPS_SetVariableCustomPresentation($ID, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($List)]);
    }
}
