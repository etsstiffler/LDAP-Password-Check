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


            $date = new DateTime();
            $date = $date->format("Y-m-d H:i:s");
            $log[] = $date."\t[INFO]\tAnzahl aller Accounts " . $info["count"];
            $log[] = $date."\t[INFO]\tAccounts mit abgelaufenem Passwort";

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

                        $date = new DateTime();
                        $date = $date->format("Y-m-d H:i:s");
                        $log[] = $date." [INFO] $cn";

                        # Start der PHPMAiler Klasse
                        $mail = new PHPMailer(true);

                        ################################################################
                        #  Änderung des Speicherns der SMTP Debug Ausgabe in Logfile
                        $mail->Debugoutput = function($str, $level) {
                            $date = new DateTime();
                            $date = $date->format("Y-m-d H:i:s");
                            file_put_contents('./log/'.$date.'_smtp.log', $date. "\t$level\t$str\n", FILE_APPEND | LOCK_EX);
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
                        $mailContent = "<p>Das Passwort Ihres Schulaccounts läuft in $validdays Tagen ab.</p>
                            <p>Bitte denken Sie daran es zeitnah zu aktualisieren.</p>
                            <p>Mobile Schulkonsole: <a href='$resethost'>$resethost</a>.</p>
                            <p>Hinweis: Es handelt sich um eine automatisch generierte Email. Sie werden diese täglich bis zum endgültigen Ablauf Ihres Passwortes erhalten.</p>
                            ";
                        $mail->Body = $mailContent;
                        if(!($mail->send())){

                        $date = new DateTime();
                        $date = $date->format("Y-m-d H:i:s");  
                        $log[] = $date."\t[ERROR]\tMail an $cn konnte nicht gesendet werden.";
                        $log[] = $date."\t[ERROR]\tMailer Error: " . $mail->ErrorInfo;

                        }else{
                            $date = new DateTime();
                            $date = $date->format("Y-m-d H:i:s"); 
                            $log[] = $date."\t[INFO]\tMail an $cn gesendet.";
                            $log[] = $date."\t[INFO]\tSMTP Log siehe ". $day."_smtp.log";
                        }
                    }
                }
                
            }
        } else {
            $date = new DateTime();
            $date = $date->format("Y-m-d H:i:s");
            $log[] = $date." [ERROR] LDAP Verbindungstatus: " . ldap_error($ldapconn);
            ldap_get_option($ldapconn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $err);
            $log[] = $date." (ERROR] ldap_get_option: $err";
        }
    }else{
        $date = new DateTime();
        $date = $date->format("Y-m-d H:i:s");
        $log[] = $date." [ERROR] Die LDAP-URI enthält Fehler.";
    };
    
    $date = new DateTime();
    $date = $date->format("Y-m-d H:i:s");
    $log[] = $date." [INFO] Skript beendet";

    # Speichern des Logs
    if(!empty($log)){
        $log = implode(" \n",$log)."\n";
        file_put_contents($logfile, $log);
    }
}
?>
