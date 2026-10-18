# Changelog

## 19.10.2026 - Version 2.0.1

### Fixes
- Die Instanz lässt sich unter Symcon 9.0 wieder anlegen, vorher brach das Anlegen mit "Undefined constant" ab.

## 17.10.2026 - Version 2.0

### Neu
- Das Modul arbeitet jetzt mit dem offiziellen Volumio-4-Plugin "MQTT Client".
- Modul und Bibliothek heißen jetzt "Volumio" (vorher "IPS-Volumio"), bestehende Instanzen bleiben erhalten.
- Neue Variablen: Album, Position, Dauer, Wiederholen, Zufall und Online.
- Das Cover wird heruntergeladen und als Medienobjekt abgelegt.
- Kachel-Visualisierung mit Cover, Steuerung und Lautstärke sowie Reitern für Radio und Mediathek.
- Radio-Reiter in der Kachel mit allen Sendern von Volumio, den eigenen Sendern und den Lieblingssendern; Wiedergabelisten und Mediathek lassen sich laden, durchsuchen und abspielen (REST-API, Volumio-Host nötig).
- Gruppen-Steuerung: Befehle gehen auf Wunsch an die Gruppe des Plugins.
- Neue Funktionen: VOLUMIO_Repeat, VOLUMIO_Random, VOLUMIO_Clear, VOLUMIO_Toggle, VOLUMIO_Unmute, VOLUMIO_UpdateLists, VOLUMIO_PlayRadio, VOLUMIO_PlayPlaylist, VOLUMIO_BrowseLibrary und VOLUMIO_PlayLibraryItem.
- Moderne Variablendarstellungen und Strict-Modul, benötigt IP-Symcon 9.0.

### Fixes
- Titel, Künstler und Status werden mit dem aktuellen Plugin wieder korrekt angezeigt.
- Cover mit Leerzeichen in der Adresse (zum Beispiel bei Radiosendern) werden geladen.

Achtung: Das alte MQTT-Plugin (volumio-mqtt) wird nicht mehr unterstützt. Als MQTT Topic muss jetzt Basis-Topic und Geräte-ID des neuen Plugins stehen, zum Beispiel volumio/<Geräte-ID>.
Achtung: Entfernt wurden die Variablen Power, Lautstärke (Push) und Radio Sender (bestehende Instanzen behalten sie, sie werden nicht mehr aktualisiert und können manuell gelöscht werden) sowie die Funktionen VOLUMIO_Power, VOLUMIO_VolumePushPlus, VOLUMIO_VolumePushMinus, VOLUMIO_UpdateRadioSender, VOLUMIO_getStatus, VOLUMIO_getMultiroomdevices und VOLUMIO_getSources, weil das neue Plugin sie nicht anbietet. VOLUMIO_Play hat keinen Parameter mehr.
Achtung: Die Radiosender-Liste in der Konfiguration (Name und Stream-URL) entfällt. Die Sender kommen jetzt aus Volumio ("Meine Radiosender" und Lieblingssender), die neue Variable "Radiosender" ersetzt die alte.
