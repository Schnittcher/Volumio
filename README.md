[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-9.0%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/Volumio/workflows/Check%20Style/badge.svg)](https://github.com/Schnittcher/Volumio/actions)

# Volumio
Dieses Modul verbindet Volumio (https://volumio.org/) mit IP-Symcon. Voraussetzung ist das offizielle Volumio-4-Plugin "MQTT Client".

## Inhaltsverzeichnis
- [Volumio](#volumio)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [Screenshots](#screenshots)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Funktionsumfang](#2-funktionsumfang)
  - [3. Enthaltene Module](#3-enthaltene-module)
  - [4. Installation](#4-installation)
  - [5. Konfiguration in IP-Symcon](#5-konfiguration-in-ip-symcon)
  - [6. Spenden](#6-spenden)
  - [7. Lizenz](#7-lizenz)

## Screenshots

Kachel-Visualisierung: Player, Radio (Hauptebene und Kategorie) und Mediathek.

![Kachel Player](docs/screenshots/player.png)
![Kachel Radio](docs/screenshots/radiosender1.png)
![Kachel Radio, Kategorie mit Sendern](docs/screenshots/radiosender2.png)
![Kachel Mediathek](docs/screenshots/mediathek.png)

## 1. Voraussetzungen

* mindestens IP-Symcon 9.0
* MQTT Server in IP-Symcon
* Volumio 4 mit dem Plugin "MQTT Client"

## 2. Funktionsumfang

* Wiedergabe steuern, Lautstärke, Stumm, Wiederholen und Zufall
* Titel, Künstler, Album, Cover, Position und Dauer anzeigen
* Radiosender, Wiedergabelisten und Mediathek von Volumio durchsuchen und abspielen
* Kachel-Visualisierung
* Befehle optional an eine Gruppe des Plugins senden

Die einzelnen Funktionen stehen bei den Instanzen unter "Enthaltene Module" und in der README des jeweiligen Moduls.

## 3. Enthaltene Module

* [Volumio](Volumio/README.md)
  * Steuert einen Volumio-Player über MQTT und zeigt seinen Zustand an.
  * Radiosender, Wiedergabelisten und Mediathek über die REST-API von Volumio.
  * Kachel-Visualisierung mit Player, Radio und Mediathek.

## 4. Installation
Die Installation erfolgt über den IP-Symcon Module Store.

## 5. Konfiguration in IP-Symcon
Die Instanz "Volumio" anlegen, den MQTT Server als übergeordnete Instanz verbinden und das MQTT Topic des Players eintragen (`<Basis-Topic>/<Geräte-ID>`). Für Radiosender, Mediathek und Cover zusätzlich den Volumio-Host eintragen. Die Einzelheiten stehen in der README des Moduls.

## 6. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

[![PayPal](https://img.shields.io/badge/PayPal-Spenden-00457C?logo=paypal&logoColor=white&style=for-the-badge)](https://www.paypal.com/donate?hosted_button_id=EK4JRP87XLSHW) [![Amazon Wunschzettel](https://img.shields.io/badge/Amazon-Wunschzettel-FF9900?logo=amazon&logoColor=white&style=for-the-badge)](https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share)

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
