<?php
# Import PHPMailer classes into the global namespace
# These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

# Load Composer's autoloader
require 'vendor/autoload.php';

# Zeitzone festlegen
date_default_timezone_set('Europe/Berlin');

# Config Datei einlesen
$conf = parse_ini_file('config.ini');

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
$mailstmpauth = $conf["mailstmpauth"];
$mailstmpsecure = $conf["mailstmpsecure"];
$mailstmpautotls = $conf["mailstmpautotls"];
$debug = $conf["maildebug"];
$resethost = $conf["pwresethost"];

# Errormeldungen
$errors = array();

# LDAP Verbindung herstellen
$ldapconn = ldap_connect($ldaphost);
if ($ldapconn) {

    # binding to ldap server
    $ldapbind = ldap_bind($ldapconn, $ldapuser, $ldappass);

    // verify binding
    if ($ldapbind) {
        $basedn = "ou=$ldapou, ou=Benutzer, ou=$ldapschule, ou=SCHULEN, o=ml3";
        $justthese = array("cn","sn", "givenname", "mail", "passwordexpirationtime");

        $sr = ldap_list($ldapconn, $basedn, "sn=*", $justthese);

        $info = ldap_get_entries($ldapconn, $sr);

        for ($i=0; $i < $info["count"]; $i++) {
            
            # Nur Acxcounts mit Mailadresse berücksichtigen
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
                    $mail = new PHPMailer();
                    $mail->CharSet = 'utf-8'; 
                    $mail->isSMTP();
                    $mail->SMTPDebug = $debug;
                    $mail->Host = $mailhost;
                    $mail->SMTPSecure = $mailstmpsecure;
                    $mail->SMTPAutoTLS = $mailstmpautotls;
                    $mail->SMTPAuth = $mailstmpauth;
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
        $errors[] = "LDAP bind failed...<br>";
    }
}else{
    $errors[] = "LDAP connection failed <br>";
}

# Ausgabe der Fehler
if(!empty($errors)){
    $errors = implode(" ",$errors);
    echo $errors;
}

