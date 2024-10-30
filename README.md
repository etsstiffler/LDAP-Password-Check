# LDAP Password Check

Autor: Fabian Drechsler
Email: [dr@werkgymnasium.de](mailto:dr@werkgymnasium.de)

## Beschreibung
Diese Software ist für den Einsatz in der PaedML-Novell vorgesehen.
Es wird täglich geprüft, ob das Passwort von Lehreraccounts noch mind. 10 Tage gültig ist.
Andernfalls wird automatisch eine Erinnerungsmail an die im System hinterlegte Emailadresse gesendet.

## Benutzung
### Voraussetzungen:
* PaedMl-Novell 4.5 (4.4 sollte ebenfalls funktionieren)
* Mobile Schulkonsole installiert (Es werden der LDAP und GroupWise Benutzer der mobilen SK, sowie docker auf dem Gserver benötigt).
Hinweis: Falls die mobile SK nicht installiert ist sein, bitte nach [Anleitung](https://www.lmz-bw.de/index.php?eID=dumpFile&t=f&f=31991&token=d4f2caeef57b533b72d72409f248dbccd2eff8c7) des LMZ enrichten 

### Installation
1. Kopieren Sie die Dateien via WinSCP/BitviseSSH auf den Gserver in den Ordner /opt/paedML/ldappwcheck (Ordner ggf. anlegen)
1. Loggen Sie sich auf der Konsole im Gserver ein.
1. Wechseln sie mit 'cd /opt/paedMl/ldappwcheck' in den oben erstellen Ordner.
1. Führen Sie folgende Befehle aus:
```
chmod +x docker-compose
./docker-compose build --no-cache

´´´
1. Kopieren Sie die Beispielconfig
`cp ./config.ini.example ./config.ini`
1. Passen Sie in der config.ini die Parameter Ihrer Schule/Benutzer an.
* ldapuser: LDAP-Benutzer der mobilen SK mit zugehörigem Passwort
* ldapschule: Name der Schule im LDAP Baum
* ldapou: Benutzergruppe für die Erinnerungsemails verschickt werden soll, aktuell ist nur eine Gruppe pro Dockercontainer möglich und im Auge des Entwicklers nur für die Lehrergruppe sinnvoll.
* mailhost und co.: Einstellungen für den Versand der Emails
* pwresethost: Link zur mobilen SK (wird in die Email eingebunden)

1. Starten Sie den Container mit dem Befehl
`./docker-compose up -d`