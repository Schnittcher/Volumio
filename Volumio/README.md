# Volumio
Dieses Modul verbindet Volumio mit IP-Symcon. Es nutzt das offizielle Volumio-4-Plugin "MQTT Client".

## Inhaltsverzeichnis
1. [Konfiguration](#1-konfiguration)
2. [Variablen](#2-variablen)
3. [Funktionen](#3-funktionen)
4. [Spenden](#4-spenden)
5. [Lizenz](#5-lizenz)

## 1. Konfiguration

Voraussetzung ist das Plugin "MQTT Client" auf Volumio 4, das mit dem MQTT-Server von IP-Symcon verbunden ist.

Feld | Beschreibung
------------ | -------------
MQTT Topic | Basis-Topic und Geräte-ID aus dem Plugin, zum Beispiel `volumio/<Geräte-ID>`. Standard im Plugin: Basis-Topic `volumio`, Geräte-ID = Hostname.
Volumio-Host | Hostname oder IP-Adresse von Volumio. Nötig für Radiosender, Wiedergabelisten und Mediathek (REST-API) sowie für Cover mit relativem Pfad (`/albumart?...`). Ein vorangestelltes `http://` wird entfernt.
Listen aktualisieren | Button: lädt die Radiosender ("Meine Radiosender" und Lieblingssender) und die Wiedergabelisten von Volumio.
Befehle an Gruppe senden | Schickt alle Befehle an das Gruppen-Topic des Plugins (`<Basis-Topic>/group/<Gruppen-ID>`) statt an das Gerät. Der Status wird weiter vom MQTT Topic gelesen.
Gruppen-ID | ID der Gruppe im Plugin (Pflicht, wenn die Gruppen-Steuerung aktiv ist).

## 2. Variablen

Variable | Ident | Typ | Beschreibung
------------ | ------------- | ------------- | -------------
Titel | Volumio_Title | String | Aktueller Titel
Künstler | Volumio_Artist | String | Aktueller Künstler
Album | Volumio_Album | String | Aktuelles Album
Position | Volumio_Position | Integer | Position im Titel in Sekunden (nur Anzeige)
Dauer | Volumio_Duration | Integer | Länge des Titels in Sekunden (nur Anzeige)
Lautstärke | Volumio_Volume | Integer | Lautstärke 0 bis 100 %, bedienbar
Lautstärke (Hoch/Runter) | Volumio_VolumeUpDown | Integer | Lauter/Leiser, bedienbar
Stumm | Volumio_Mute | Boolean | Stummschaltung, bedienbar
Steuerung | Volumio_Controls | Integer | Zurück, Wiedergabe, Pause, Stopp, Weiter, bedienbar
Wiederholen | Volumio_Repeat | Boolean | Wiederholung, bedienbar
Zufall | Volumio_Random | Boolean | Zufallswiedergabe, bedienbar
Online | Volumio_Online | Boolean | Erreichbarkeit laut Plugin (`available`)
Radiosender | Volumio_RadioStation | Integer | Auswahl der Radiosender, bedienbar (nach "Listen aktualisieren")
Wiedergabeliste | Volumio_Playlist | Integer | Auswahl der Wiedergabelisten, bedienbar (nach "Listen aktualisieren")

Zusätzlich legt das Modul das Medienobjekt "Cover" an, sobald das Plugin eine Cover-URL meldet. Das Bild wird heruntergeladen und dort abgelegt (bei relativem Pfad ist der Volumio-Host nötig).

### Kachel-Visualisierung

Die Instanz hat eine Kachel mit drei Reitern: **Player** (Cover, Titel, Fortschritt, Steuerung, Zufall, Wiederholen, Lautstärke), **Radio** (alle Sender des Volumio-Radioverzeichnisses, dazu "Meine Radiosender" und die Lieblingssender) und **Mediathek** (Ordner, Alben, Wiedergabelisten und weitere Quellen von Volumio durchsuchen und abspielen). Radio und Mediathek brauchen den Volumio-Host. Die maximierte Ansicht der Kachel gibt es erst ab IP-Symcon 9.1.

## 3. Funktionen

**VOLUMIO_Mute(int $InstanceID, bool $Value)**\
Schaltet Volumio stumm oder wieder laut.
```php
VOLUMIO_Mute(12345, true);
```

**VOLUMIO_Volume(int $InstanceID, int $Value)**\
Setzt die Lautstärke (0 bis 100).
```php
VOLUMIO_Volume(12345, 50);
```

**VOLUMIO_VolumeUp(int $InstanceID)** / **VOLUMIO_VolumeDown(int $InstanceID)**\
Lauter bzw. leiser.
```php
VOLUMIO_VolumeUp(12345);
```

**VOLUMIO_Play(int $InstanceID)**, **VOLUMIO_Pause(int $InstanceID)**, **VOLUMIO_Stop(int $InstanceID)**\
Startet, pausiert oder stoppt die Wiedergabe. Bei Streaming-Diensten (zum Beispiel Tidal, Qobuz, Webradio) wirkt Stopp wie Pause (Verhalten des Plugins).
```php
VOLUMIO_Play(12345);
```

**VOLUMIO_Next(int $InstanceID)** / **VOLUMIO_Prev(int $InstanceID)**\
Nächster bzw. vorheriger Titel.
```php
VOLUMIO_Next(12345);
```

**VOLUMIO_Seek(int $InstanceID, int $Value)**\
Springt zu einer Position im Titel (Millisekunden).
```php
VOLUMIO_Seek(12345, 30000);
```

**VOLUMIO_Repeat(int $InstanceID, bool $Value)** / **VOLUMIO_Random(int $InstanceID, bool $Value)**\
Schaltet Wiederholung bzw. Zufallswiedergabe.
```php
VOLUMIO_Repeat(12345, true);
```

**VOLUMIO_Toggle(int $InstanceID)** / **VOLUMIO_Unmute(int $InstanceID)**\
Schaltet zwischen Wiedergabe und Pause um bzw. hebt die Stummschaltung auf.
```php
VOLUMIO_Toggle(12345);
```

**VOLUMIO_UpdateLists(int $InstanceID)**\
Lädt Radiosender und Wiedergabelisten von Volumio (REST-API, Volumio-Host nötig).
```php
VOLUMIO_UpdateLists(12345);
```

**VOLUMIO_PlayRadio(int $InstanceID, int $Value)**\
Spielt den Radiosender mit der Nummer aus der Liste (Position in der Auswahl der Variable "Radiosender").
```php
VOLUMIO_PlayRadio(12345, 1);
```

**VOLUMIO_PlayPlaylist(int $InstanceID, string $Value)**\
Startet eine gespeicherte Wiedergabeliste.
```php
VOLUMIO_PlayPlaylist(12345, 'Rock');
```

**VOLUMIO_BrowseLibrary(int $InstanceID, string $URI)**\
Liefert eine Ebene der Mediathek als JSON (`uri`, `prev`, `items`). Leere URI = Hauptebene.
```php
$Json = VOLUMIO_BrowseLibrary(12345, '');
```

**VOLUMIO_PlayLibraryItem(int $InstanceID, string $Service, string $URI, string $Title, string $Type = '')**\
Spielt einen Eintrag aus `VOLUMIO_BrowseLibrary` (Titel, Album, Ordner, Radiosender).
```php
VOLUMIO_PlayLibraryItem(12345, 'webradio', 'http://stream.example.org/mp3', 'Beispiel Radio', 'webradio');
```

**VOLUMIO_Clear(int $InstanceID)**\
Leert die Warteschlange.
```php
VOLUMIO_Clear(12345);
```

## 4. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
