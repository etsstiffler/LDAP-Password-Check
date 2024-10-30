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

# Errormeldungen
$log = array();
$date = new DateTime();
$date = $date->format("y:m:d h:i:s");
$log[] = $date." Start LDAP-PW-Check";



# Config Datei einlesen
$conf = parse_ini_file('config.ini');

if(!$conf){
    $date = new DateTime();
    $date = $date->format("y:m:d h:i:s");
    $log[] = $date." Es gab ein Problem mit der config.ini. Entweder enhält sie Fehler oder ist nicht vorhanden. Skript bricht ab.";

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
            $date = $date->format("y:m:d h:i:s");
            $log[] = $date." LDAP Verbindungstatus: " . ldap_error($ldapconn);


            $basedn = "ou=$ldapou, ou=Benutzer, ou=$ldapschule, ou=SCHULEN, o=ml3";
            $justthese = array("cn","sn", "givenname", "mail", "passwordexpirationtime");

            $sr = ldap_list($ldapconn, $basedn, "sn=*", $justthese);

            $info = ldap_get_entries($ldapconn, $sr);

            for ($i=0; $i < $info["count"]; $i++) {
                
                # Nur Accounts mit Mailadresse berücksichtigen
                if(isset($info[$i]["mail"][0])){
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
                        $mail = new PHPMailer(true);
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
                        $errors[] = 'Message could not be sent.';
                        $errors[] = 'Mailer Error: ' . $mail->ErrorInfo;
                        }
                    }
                }
                
            }
        } else {
            $date = new DateTime();
            $date = $date->format("y:m:d h:i:s");
            $log[] = $date." LDAP Verbindungstatus: " . ldap_error($ldapconn);
            ldap_get_option($ldapconn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $err);
            $log[] = $date." ldap_get_option: $err";
        }
    }else{
        $date = new DateTime();
        $date = $date->format("y:m:d h:i:s");
        $log[] = $date." Die LDAP-URI enthält Fehler.";
    };
    

    # Ausgabe der Fehler
    if(!empty($log)){
        $log = implode(" \n",$log)."\n";
        echo $log;
    }
}
?>
