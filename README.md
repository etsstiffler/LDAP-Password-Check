# LDAP Password Check

Autor: Fabian Drechsler

Email: [dr@werkgymnasium.de](mailto:dr@werkgymnasium.de)

## Beschreibung
Diese Software ist für den Einsatz in der PaedML-Novell vorgesehen.
Es wird täglich geprüft, ob das Passwort von Lehreraccounts noch eine Mindestanzahl von 10 Tagen gültig ist.

Andernfalls wird täglich eine Erinnerungsmail an die betroffenen Accounts versendet.

Jeder Durchlauf wird in einer Logdatei im Ordner 'log' protokolliert.

## Benutzung
### Installationsvoraussetzungen:
* PaedML-Novell 4.4+
* Mobile Schulkonsole installiert (Es werden der LDAP- und GroupWise-Benutzer der mobilen SK, sowie docker auf dem Gserver benötigt).
* git
Hinweis: Falls die mobile SK nicht installiert ist, bitte nach [Anleitung Schulkonsole-mobil 0.9.6 Update, Installation, Bedienung](https://www.lmz-bw.de/netzwerkloesung/produkte-paedml/paedml-novell/downloads) (Hinweis: Download der Anleitung nur eingeloggt möglich) des LMZ einrichten 
* Netzwerkadressbeschränkungen des ldapuserskmobil entfernt

### Installation
1. Melden Sie sich auf der Konsole am Gserver an.
1. Wechseln Sie mit `cd /opt/paedML/` in das Verzeichnis.
1. Klonen sie das Repository mit dem Befehl

    `git clone https://github.com/etsstiffler/LDAP-Password-Check.git ldappwcheck`

1. Wechseln Sie in den Ordner `ldappwcheck`

    `cd ldappwcheck`

1. Kopieren Sie die Beispielconfig

    `cp ./config.ini.example ./config.ini`

1. Passen Sie mit einem Editor Ihrer Wahl in der `config.ini` die Parameter Ihrer Schule/Benutzer an.
    * ldapuser: LDAP-Benutzer der mobilen SK mit zugehörigem Passwort
    * ldapschule: Name der Schule im LDAP Baum
    * ldapou: Benutzergruppe für die Erinnerungsemails verschickt werden soll, aktuell ist nur eine Gruppe pro Docker-Container möglich und im Auge des Entwicklers nur für die Lehrergruppe sinnvoll.
    * mailhost und co.: Einstellungen für den Versand der Emails
    * pwresethost: Link zur mobilen SK (wird in die Email eingebunden)
    * maildebug: Falls es Probleme beim Mailversand gibt, kann hiermit eine Logdatei erzeugt werden.

1. Führen Sie folgende Befehle aus:
    ```
    chmod +x docker-compose
    ./docker-compose build --no-cache
    
Hinweis: Der Befehl `./docker-compose build --no-cache` muss nach jeder späteren Änderung der `config.ini`erneut ausgeführt werden.

### Start
1. Wechsel in das Verzeichnis `/opt/paedMl/ldappwcheck`

    `cd /opt/paedML/ldappwcheck`

1. Start des Containers:

    `./docker-compose up -d`

1. Check, ob Container läuft:

    `docker ps`


### Stop 
1. Wechseln Sie in das Verzeichnis `/opt/paedMl/ldappwcheck `

    `cd /opt/paedML/ldappwcheck`

1. Stoppen Sie den Container mit dem Befehl

    `./docker-compose down`

    Dieser Befehl stoppt und löscht den Container. 
1. Überprüfen , ob der Container wirklich gestoppt und entfernt wurde:

    `docker ps`

### Aktualsieren der grundlegenden Skripte
1. Wechsel in das Installationsverzeichnis

    `cd /opt/paedML/ldappwcheck`
1. Update der Dateien

    `git pull`
1. Aktualisieren des Docker-Images (siehe unten)
1. Start des Containers(siehe oben)
    
### Aktualisieren des Docker-Images
1. Container stoppen (siehe oben)
1. Altes Image löschen

    `docker rmi ldap-pw-check`

1. Neubau des Images 

    `./docker-compose build --no-cache`


## Tipps und Hinweise
- In der Konfiguration des Cronjobs ist 00:00 Uhr als Zeitpunkt der Ausführung eingetragen. Im Docker-Container ist die Zeitzone UTC, heißt 02:00 Uhr MEZ.
- Beim Bearbeiten von Dateien mit VS-Code kann es vorkommen, dass Zeilenumbrüche und -enden nicht Linuxkonform formatiert werden. Dies ist speziell beim Editieren der Crontab zu beachten.
- Sollten Probleme beim Starten/Stoppen des Containers bzw. Bauen des Containers hilft meist ein Neustart des Docker Prozesses

    `systemctl restart docker.service`

- Alte Containerleichen können mittels 

    `docker container rm CONTAINER_NAME`

  entfernt werden. (CONTAINER_NAME mittels `docker ps` herausfinden)

- ACHTUNG nur für Erfahrene (!!!), mit VORSICHT verwenden und vorher [Docker Dokumentation](https://docs.docker.com/reference/cli/docker/system/prune/) lesen. Das gesamte Docker System kann mit 

    `docker system prune`

    aufgeräumt werden.