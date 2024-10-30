<?php
# Autor: Fabian Drechsler
# Email: dr@Werkgymnasium.de
# Juli 2023

error_reporting(E_ALL);



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';


# Zeitzone festlegen
date_default_timezone_set('Europe/Berlin');

# Logging
$date = new DateTime();
$day = $date->format("Y-m-d ");
$date = $date->format("Y-m-d H:i:s");
$logdir = "./log/";
$logfile = $logdir.$date ."-ldap-pw-check.log";
$log = array();
$date = new DateTime();
$date = $date->format("Y-m-d H:i:s");
$log[] = $date."\t[INFO]\tStart LDAP-PW-Check";



# Config Datei einlesen
$conf = parse_ini_file('config.ini');

if(!$conf){
    $date = new DateTime();
    $date = $date->format("Y-m-d H:i:s");
    $log[] = $date."\t[ERROR]\tEs gab ein Problem mit der config.ini. Entweder enhält sie Fehler oder ist nicht vorhanden. Skript bricht ab.";

}else{
    # Variablen aus der config.ini zuweisen
    # LDAP Variablen
    $ldaphost = $conf["ldapserver"];
    $ldapuser = "cn=".$conf["ldapuser"].",ou=Server,ou=dienste,o=ml3";
    $ldappass = $conf["ldappw"];
    $ldapschule = $conf["ldapschule"];
    $ldapou = $conf["ldapou"];


    # Mail Variablen
    $mailhost = $conf["mailhost"];
    $mailuser = $conf["mailuser"];
    $mailpw = $conf["mailpw"];
    $mailfrom = $conf["mailfrom"];
    $mailsmtpauth = $conf["mailsmtpauth"];
    $mailsmtpsecure = $conf["mailsmtpsecure"];
    $mailsmtpautotls = $conf["mailsmtpautotls"];
    $debug = $conf["maildebug"];
    $resethost = $conf["pwresethost"];



    # ldap_connect() überprüft die LDAP Uri auf Syntaxfehler, stellt aber noch keine Verbindung her.
    $ldapconn = ldap_connect($ldaphost);

    if ($ldapconn) {

        # binding to ldap server
        # Stellt die eigentliche Verbindung zum Server her
        $ldapbind = ldap_bind($ldapconn, $ldapuser, $ldappass);

        // verify binding
        if ($ldapbind) {
            
            $date = new DateTime();
            $date = $date->format("Y-m-d H:i:s");
            $log[] = $date."\t[INFO]\tLDAP Verbindungstatus: " . ldap_error($ldapconn);


            $basedn = "ou=$ldapou, ou=Benutzer, ou=$ldapschule, ou=SCHULEN, o=ml3";
            $justthese = array("cn","sn", "givenname", "mail", "passwordexpirationtime");

            $sr = ldap_list($ldapconn, $basedn, "sn=*", $justthese);

            $info = ldap_get_entries($ldapconn, $sr);

            # Arrays zum speichern der Benutzer, die eine Mail bekommen bzw. deren Accounts bereits gesperrt sind
            $logusersmail = array();
            $loguserlock = array();


            $date = new DateTime();
            $date = $date->format("Y-m-d H:i:s");
            $log[] = $date."\t[INFO]\tAnzahl aller kontrollierter Accounts " . $info["count"];
            #$log[] = $date."\t[INFO]\tBetroffene Accounts:";

            for ($i=0; $i < $info["count"]; $i++) {
                
                # Nur Accounts mit Mailadresse berücksichtigen
                if(isset($info[$i]["mail"][0])){

                    # Speichern der wichtigen Account Infos in ensprechenden Variablen
                    $cn = $info[$i]["cn"][0];
                    $mailaddress = $info[$i]["mail"][0];
                    $expirationdate = $info[$i]["passwordexpirationtime"][0];
                    $today = time();
                    $expdate = strtotime($expirationdate);

                    # Berechnet die restlichen Gültigkeitstage
                    $validdays = round(($expdate - $today) / (60 * 60 * 24));

                    # Wenn gültige Tage sich zwischen 0 und 10 befindet, wird eine Mail gesendet
                    # Neue bzw. abgelaufene Accounts haben negative Gültigkeitstage
                    if((0 <= $validdays) && ($validdays <= 10)){

          

                        # Start der PHPMAiler Klasse
                        $mail = new PHPMailer(true);

                        ################################################################
                        # Änderung des Speicherns der SMTP Debug Ausgabe in eigenem Logfile
                        # smtp.log wird am Ende in das gesamte Logfile integriert
                        $mail->Debugoutput = function($str, $level) {
                            $date = new DateTime();
                            $date = $date->format("Y-m-d H:i:s");
                            file_put_contents('./log/smtp.log', $date. "\t$level\t$str\n", FILE_APPEND | LOCK_EX);
                        };
                        ################################################################
                        $mail->CharSet = 'utf-8'; 
                        $mail->isSMTP();
                        $mail->SMTPDebug = $debug;
                        $mail->Host = $mailhost;
                        $mail->SMTPSecure = $mailsmtpsecure;
                        $mail->SMTPAutoTLS = $mailsmtpautotls;
                        $mail->SMTPAuth = $mailsmtpauth;
                        $mail->Username = $mailuser;
                        $mail->Password = $mailpw;
                        $mail->setFrom($mailfrom);
                        $mail->addAddress($mailaddress);
                        $mail->Subject = "Erinnerung - Passwortablauf in $validdays Tagen";
                        $mail->isHTML(true);
                        #################################################################
                        # Mailinhalt auf Wunsch anpassen
                        $mailContent = "<p>Das Passwort Ihres Schulaccounts läuft in $validdays Tagen ab.</p>
                            <p>Bitte denken Sie daran es zeitnah zu aktualisieren bevor es endgültig abläuft und Ihr Account gesperrt wird.</p>
                            <p>Mobile Schulkonsole: <a href='$resethost'>$resethost</a>.</p>
                            <p>Hinweis: Es handelt sich um eine automatisch generierte Email. Sie werden diese täglich bis zum endgültigen Ablauf Ihres Passwortes erhalten.</p>
                            ";
                        ################################################################
                        $mail->Body = $mailContent;

                        # Abschicken der Email
                        if(!($mail->send())){
                            # Mail wurde nicht gesendet Empfänger und Fehlerinfo werden in Logvariable abgelegt
                            $date = new DateTime();
                            $date = $date->format("Y-m-d H:i:s");  
                            $log[] = $date."\t[ERROR]\tMail an $cn konnte nicht gesendet werden.";
                            $log[] = $date."\t[ERROR]\tMailer Error: " . $mail->ErrorInfo;

                        }else{
                            # Mail erfolgreich gesendet, Namen in Array ablegen
                            $logusersmail[] = $cn;
                        }
                    }
                    # Alle bereits abgelaufenen Accounts werden für eine Übersicht in $loguserlock abgelegt.
                    if ($validdays <= 0){
                        $loguserlock[] = $cn;

                    }
                }
                
            }

            # Infos in Log einfügen
            # an wen wurden Mails geschickt
            # welche Accounts sind bereits abgelaufen
            $date = new DateTime();
            $date = $date->format("Y-m-d H:i:s");
            $logusersmail = implode(",",$logusersmail);
            $loguserlock = implode(",", $loguserlock);

            # STMP Log auslesen, falls es existiert
            if(file_exists('./log/smtp.log')){
                $smtplog = file_get_contents('./log/smtp.log');
            }else{
                $smtplog = $date."\t[INFO]\tEs wurde keine Email verschickt.";
            }
            

            $log[] = "------------------------------------";
            $log[] = $date."\t[INFO]\tMailversand an folgende Accounts:";
            $log[] = $date."\t[INFO]\t$logusersmail";
            $log[] = $date."\t[INFO]\tSMTP-Log:";
            $log[] = $smtplog;
            $log[] = "------------------------------------";
            $log[] = $date."\t[INFO]\tAbgelaufene Accounts:";
            $log[] = $date."\t[INFO]\t$loguserlock";
            $log[] = "------------------------------------";


        } else {
            $date = new DateTime();
            $date = $date->format("Y-m-d H:i:s");
            $log[] = $date."\t[ERROR]\tLDAP Verbindungstatus: " . ldap_error($ldapconn);
            ldap_get_option($ldapconn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $err);
            $log[] = $date."\t[ERROR] ldap_get_option: $err";
        }
    }else{
        $date = new DateTime();
        $date = $date->format("Y-m-d H:i:s");
        $log[] = $date."\t[ERROR]\tDie LDAP-URI enthält Fehler.";
    }

    # Löschen des temporären smtp logs
    if(file_exists('./log/smtp.log')){
        unlink('./log/smtp.log');
        $date = new DateTime();
        $date = $date->format("Y-m-d H:i:s");
        $log[] = $date."\t[INFO]\tSMTP-Log gelöscht.";
    }else{
        $date = new DateTime();
        $date = $date->format("Y-m-d H:i:s");
        $log[] = $date."\t[ERROR]\tSMTP-Log konnte nicht gelöscht werden.";
    }


    # Log Abschluss
    $date = new DateTime();
    $date = $date->format("Y-m-d H:i:s");
    $log[] = $date."\t[INFO]\tSkript beendet";

    # Speichern des Logs
    if(!empty($log)){
        $log = implode(" \n",$log)."\n";
        file_put_contents($logfile, $log);
    }
}
?>
