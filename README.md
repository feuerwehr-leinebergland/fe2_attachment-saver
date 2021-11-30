


# AttachmentSaver
Die Feuerwehr Leinebergland wird aktuell über eine verschlüsselte Alarm-E-Mail der [IRLS Hildesheim](https://www.hildesheim.de/feuerwehr/berufsfeuerwehr/integrierte-regionalleitstelle-hildesheim-irls.html) alarmiert.  
In dieser E-Mail befindet sich ein verschlüsselter Anhang (Alarmdepesche) in der sich alle Einsatzdaten befinden.
[FE2](https://www.alamos-gmbh.com/service/fe2/) bietet aktuell leider keine Möglichkeit verschlüsselte Anhänge auszulesen.  
Daher ist dieses Skript entstanden, um das Postfach zu überwachen, auszulesen, die E-Mail zu entschlüsseln und den Anhang an FE2 zu übergeben.

## Abhängigkeiten
- [GnuPG](https://gnupg.org/ftp/gcrypt/binary/gnupg-w32-2.3.3_20211012.exe) (GNU Privacy Guard)
- [Mailparse](https://windows.php.net/downloads/pecl/releases/mailparse/3.1.2/php_mailparse-3.1.2-7.3-nts-vc15-x64.zip) (PHP Extension)
- [PHP 7.3](https://www.php.net/distributions/php-7.3.33.tar.gz) (Hypertext Preprocessor)

## Installation

### GnuPG
 1. Abhängigkeit herunterladen und installieren.

### PHP 7.3
 1. Abhängigkeit herunterladen und entpacken.
 2. Die entpackten Dateien nach `C:\Program Files\PHP\` kopieren.
 3. Die Konfigurationsdatei `php.ini-production` in `php.ini` umbenennen.
 4. Im Bereich `Paths and Directories` den Eintrag `;extension_dir = "ext"` in `extension_dir = "C:\Program Files\PHP\ext"` ändern.
 5. Im Bereich `Dynamic Extensions` müssen folgende Einträge geändert werden:
	- `;extension=imap` in `extension=imap`
	- `;extension=mbstring` in `extension=mbstring`
	- `;extension=openssl` in `extension=openssl`

> **Information:** Um PHP ausführen zu können wird eine Systemumgebungsvariable benötigt.
> Erweiterte Systemeinstellungen -> Umgebungsvariablen -> Systemvariablen -> Path -> Neu -> `C:\Program Files\PHP\`

### Mailparse (PHP Extension)
 1. Abhängigkeit herunterladen und entpacken.
 2. Die Datei `php_mailparse.dll` nach `C:\Program Files\PHP\ext\` kopieren.
 3. In der Konfigurationsdatei muss unter `extension=mbstring` der Eintrag `extension=mailparse` hinzugefügt werden.

## Konfiguration
 1. Die Konfigurationsdatei `config.example.php` in `config.php` umbenennen.
 2. Die Konfigurationsparameter entsprechend anpassen.
```
<?php
return [
    'check_interval_in_seconds' => 1,
    'mailbox_to_check' => [
        'imap_hostname' => 'example.com',
        'imap_email_address' => 'user@example.com',
        'imap_password' => 'password',
        'imap_port' => 993,
        'imap_options' => '/ssl'
    ],
    'private_key' => [
        'file' => 'C:\\path\\to\\private.key',
        'passphrase' => 'password'
    ],
    'sender_email_addresses' => [
      'mail@example.com'
    ],
    'save_attachments_to' => 'C:\\path\\to\\attachments',
    'working_directory' => 'C:\\path\\to\\working-directory',
    'path_to_gpg_application' => 'C:\\Program Files (x86)\\GnuPG\\bin\\',
    'path_separator' => '\\'
];
```

## Verwendung
 1. Über die Eingabeaufforderung in das entsprechende Verzeichnis navigieren.
 2. Skript mit `php main.php` starten.