<?php

require 'config.php';
require 'vendor/autoload.php';

require 'DM42APIClient.class.php';
require 'DM42APIEmailClient.class.php';

use \Slim\Slim;
use \Slim\App as App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Validator as Validator;
use \ParagonIE\PasswordLock\PasswordLock as PasswordLock;
use Defuse\Crypto\Key;
use PHPMailer\PHPMailer\PHPMailer;

ORM::configure(MYSQLServer.';dbname='.MYSQLDB);//mysql:sock=/var/run/mysqld/mysqld.sock;dbname=sloggerdev');
ORM::configure('username',MYSQLUser);//'sloggerdev');
ORM::configure('password',MYSQLPassword);//'tohgee4aiZeirie0Niphoovie');

require 'dmtools.php';
require 'SessionMgr.class.php';
require 'Parsedown.php';

SessionMgr::sessionStart('StructLogger',SessionLifetimeSecs);

$logid=0;

$CLI=getenv("CLI");
$CLI = ($CLI=="true");

if (!$CLI) {
$app = new App(array(
    'debug' => true, 'settings' => array (
    'displayErrorDetails' => true,
    'determineRouteBeforeAppMiddleware' => true
    )
) );


$container=$app->getContainer();

$container['logger'] = function($c) {

    $logger = new \Monolog\Logger('error_logger');
    if (LOG_FILE) {
        $file_handler = new \Monolog\Handler\StreamHandler(LOG_FILE);
        $file_handler->setFormatter(new Monolog\Formatter\LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n","Y-m-d H:i:s",true,true));
        $crossed_fingers = new \Monolog\Handler\FingersCrossedHandler ($file_handler,\Monolog\Logger::ERROR);
        $logger->pushHandler($crossed_fingers);
    }
    //ADD DEBUG LOGGER IF NEEDED
    if (defined ('DEBUG') && defined ('DEBUG_LOG') && DEBUG) {
        $file_handler = new \Monolog\Handler\StreamHandler(DEBUG_LOG);
        $file_handler->setFormatter(new Monolog\Formatter\LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n","Y-m-d H:i:s",true,true));
        $logger->pushHandler($file_handler);
    }
    if (is_object($logger)) {
        return $logger;
    }
};
}
function createLogtables ($logid) {
    $datatable["create"]="
        CREATE TABLE logdata_".$logid." (
            id BIGINT unsigned NOT NULL AUTO_INCREMENT,
            entry TEXT,
            datestamp DATETIME not null,
            parsed tinyint,
            addedby varchar(31),
            PRIMARY KEY (id)        
        )";
    $datatable["delete"]="DROP TABLE logdata_".$logid;
    $tagstable["create"]="
        CREATE TABLE logtags_".$logid." (
            id BIGINT unsigned NOT NULL AUTO_INCREMENT,
            tag varchar(15),
            entry TEXT,
            logentryid BIGINT,
            datestamp DATETIME not null,
            PRIMARY KEY (id)
        )";
    $tagstable["delete"]="DROP TABLE logtags_".$logid;

    $db=ORM::get_db();
    $result=$db->exec($datatable["create"]);
    if ($result) {
        return 0;
    }
    $result=$db->exec($tagstable["create"]);
    if ($result) {
        $result=$db->exec($datatable["delete"]);
        return 0;
    }
    return 1;
}

function newLog($userid) {
    $newlogid = uniqid();
    if (createLogtables($newlogid)) {
        $newlogmeta=Array();
        $newlogmeta["permission"]["owner"]=$userid;
        $newlogmeta["permission"]["view"][]=$userid;
        $newlogmeta["permission"]["write"][]=$userid;
        $newlogmeta["permission"]["admin"][]=$userid;
        $newlogmeta["name"]="New Log ($newlogid)";
        $newlog = ORM::forTable("logs")->create();
        $newlog->set("logid",$newlogid);
        $newlog->set("owner_id",$userid);
        $newlog->save();
        $newlogsysid = $newlog->get("id");
        
        if (!$newlogsysid) {return false;}
        if (!dm42_update_meta_by_id ("logs",$newlogsysid,$newlogmeta)) {
            return false;
        }
        
        $usermeta=dm42_get_meta_by_id ("users",$userid);
        $usermeta=dm42_addMeta($usermeta,"logs",$newlogid,true);
        if (dm42_update_meta_by_id ("users",$userid,$usermeta)) {
          return $newlogid;
        } else {
          return $false;
        }
    } else {
        return false;
    }
}

function doDBUpdates ($updates,$basever=-1) {
    $db=ORM::get_db();
        foreach ($updates as $version => $update) {
            if ($version <= $basever) {continue;}
            foreach ($update as $query) {

                $result=$db->exec("$query");
                if ($result) {
                    echo "WARNING: ERROR UPDATING TABLES, DATABASE MAY BE IN AN INCONSISTENT STATE! \n";
                    echo "VERSION: $version\n";
                    echo "ERROR IN QUERY: \n";
                    print_r($query);
                    echo "\nERROR INFO:\n";
                    print_r($result);
                    exit;
                }
            }
        }
}

function dbUpdateTables ($basever) {

    $updates = Array (
        1 => Array (
            "CREATE TABLE logs (
                id BIGINT unsigned NOT NULL AUTO_INCREMENT,
                owner_id varchar(31),
                logid varchar(31),
                meta TEXT,
                PRIMARY KEY (id)
            )",
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT unsigned NOT NULL AUTO_INCREMENT,
                email varchar(512),
                password varchar(75),
                userid varchar(31),
                meta TEXT,
                PRIMARY KEY (id)
            )"
        )
      );
    doDBUpdates($updates,$basever);
    }

function dbDropTables() {
    $logs=ORM::forTable("logs")->find_many();
    $updates=Array();
    foreach ($logs as $log) {
      $logtable="logdata_".$log->get("logid");
      $updates[]=Array("drop table if exists ".$logtable.";");
    }
    $updates[]=Array("drop table if exists logs;");
    doDBUpdates($updates);
}

    function loadPWKey () {
	$keyjson = file_get_contents(PW_KEY_FILE);
        $pw_keys = json_decode($keyjson,true);
        $mostrecent = array_pop($pw_keys);
        return Key::loadFromAsciiSafeString($mostrecent);
        }

    function newPWKeys () {
        global $pw_enc_key;
        $keyjson = file_get_contents(PW_KEY_FILE);
        $pw_keys = json_decode($keyjson,true);
        $newkey = Key::createNewRandomKey();
        $pw_keys[] = $newkey->saveToAsciiSafeString();
        $keyjson = json_encode($pw_keys);
        file_put_contents(PW_KEY_FILE,$keyjson);
        $pw_enc_key = $newkey;
	}

    function changePW ($email,$oldpw,$newpw,$force=0) {
        
        if ($force!=1 && !authenticateuser($email,$oldpw)) {
                 return false;
        }
        
        $user = ORM::forTable("users")->where('email',$email)->find_one();

        $enc_key=loadPWKey();
        $enchash = PasswordLock::hashAndEncrypt($newpw,$enc_key);
        $user->set('password',$enchash);
        $user->save();
        return true;
    }
    
    function formResetPW ($formdata) {
        $form="";
        if (isset($formdata["token"]) || $user) {
            if (isset($formdata["username"])) {
                $user = ORM::forTable("users")->where('email',$formdata["username"])->find_one();
                if (!$user) {
                      $form.="<h4>Invalid username.  Please try again.</h4>\n";
                      $form.= formResetPw(Array('token'=>$formdata["token"]));
                } else {
                    $usermeta=dm42_get_meta_by_id("users",$user->get("id"));
                    if (isset($formdata["newpw"]) && ($formdata["newpw"] != $formdata["newpwverify"])) {
                        $form.= "<h4>Passwords Don't match.  Please try again.</h4>\n";
                        $form.= formResetPw(Array('token'=>$formdata["token"]));
                    } else if (!isset($usermeta["pwresettoken"]) ||
                          ($formdata["token"] != $usermeta["pwresettoken"]["token"]) ||
                          (time() > $usermeta["pwresettoken"]["expires"])) {
                        $form.="<h4>Token non-existent or expired.  Please try again.</h4>\n";
                        $form.=formResetPw();
                    } else {
                        unset($usermeta["pwresettoken"]);
                        dm42_update_meta_by_id("users",$user->get("id"),$usermeta);
                        changePW ($formdata["username"],null,$formdata["newpw"],1);
                        
                        $form.="<h4>Password Changed</h4>";
                    }
               }
            } else {
                ob_start();
                Form::open ("ResetPW",null,Array("noLabel" => true));
                Form::Hidden ("FormID","ResetPW");
                Form::Textbox ("Username:","username",array("required" => 1));
                Form::Password ("New Password:", "newpw", array("required" => 1));
                Form::Password ("Verify Password:", "newpwverify", array("required" => 1));
                Form::Button ("Submit");
                Form::close (false);
                $form=ob_get_contents();
                ob_end_clean();
            }
        } else {
                if (($formdata["FormID"]=="ResetPW") && (isset($formdata["username"]))) {
                    if (newResetPWToken($formdata["username"])) {
                        $form.="<h1>Reset token sent.</h1>";
                    } else {
                        $form.="<h1>Unable to send password token.  Check your username and try again.</h1>";
                    }
                } else {
                    ob_start();
                    echo "<h1>Reset Password</h1>";
                    Form::open ("ResetPW",null,Array("noLabel" => true));
                    Form::Hidden ("FormID","ResetPW");
                    Form::Textbox ("Username:","username",array("required" => 1));
                    Form::Button ("Submit");
                    Form::close (false);
                    $form.=ob_get_contents();
                    ob_end_clean();
                }
        }
        
        return $form;

    }
    
    function newLogSubscriberNotify ($email,$logname) {
        $user = ORM::forTable("users")->where('email',$email)->find_one();
        
        if (!$user) {
            return false;
        }
        
        $usermeta=dm42_get_meta_by_id("users",$user->get("id"));
        
        $resetemail.="Greetings!\n\n";
        $resetemail.="Someone has added you to view or contribute to a\n";
        $resetemail.="log on our network named: '".$logname."'.\n\n";
        $resetemail.="\n\n";
        $resetemail.="Since you already have an account on our loggin server,\n";
        $resetemail.="this log will automatically be listed in your available logs.\n";
        $resetemail.="\n\nHave fun logging!";
        if (MAILER == 'PHPMAILER') {
          $emailmessage= new PHPMailer;
          $emailmessage->setFrom(EmailAddress);
          $emailmessage->addAddress($email);
          $emailmessage->Subject = "Added to log: ".$logname;
          $emailmessage->isHTML(false);
          $emailmessage->Body=$resetemail;
          if (!$emailmessage->send()) {
             throw new Exception($emailmessage->ErrorInfo);
             return false;
          } else {
            return true;
          }
        } else if (MAILER == 'DM42API') {
          $emailclient= new DM42APIEmailClient(DM42APIAccessKey,DM42APISecretKey);
          $emailmessage= new DM42APIEmailMessage();
          $emailmessage->addTo($email);
          $emailmessage->addFrom(EmailAddress);
          $emailmessage->addText($resetemail);
          $emailmessage->addSubject("Added to log: ".$logname);
          $emailclient->sendEmail($emailmessage); 
          return true;
        } else {
          //NO MAILER, JUST PRETEND EVERYTHING IS FINE
          return true;
        }
    }

    function newResetPWToken ($email) {
        $tokenexpire = time() + (2*60*60);
        $pwtoken = md5($email.uniqid());
        $user = ORM::forTable("users")->where('email',$email)->find_one();
        
        if (!$user) {
            return false;
        }
        
        $usermeta=dm42_get_meta_by_id("users",$user->get("id"));
        $tokeninfo['expires']=$tokenexpire;
        $tokeninfo['token']=$pwtoken;
        $usermeta["pwresettoken"]=$tokeninfo;
        dm42_update_meta_by_id("users",$user->get("id"),$usermeta);
        
        $pwreseturl=HTMLURL."/pwreset?token=".$pwtoken;
        
        $resetemail.="Greetings!\n\n";
        $resetemail.="You have recently requested a password reset or\n";
        $resetemail.="someone has added you to view or contribute to a\n";
        $resetemail.="log on our network.\n\n";
        $resetemail.="You can use the following link to set your password.\n";
        $resetemail.=$pwreseturl;
        $resetemail.="\n\n";
        $resetemail.="Your login id is the email address to which this\nmessage was sent.\n";
        $resetemail.="\n\nHave fun logging!";
        if (MAILER == "PHPMAILER") {
          $emailmessage= new PHPMailer;
          $emailmessage->setFrom(EmailAddress);
          $emailmessage->addAddress($email);
          $emailmessage->Subject="Tagged Logger: Password Reset";
          $emailmessage->isHTML(false);
          $emailmessage->Body=$resetemail;
          if (!$emailmessage->send()) {
             throw new Exception($emailmessage->ErrorInfo);
             return false;
          } else {
            return true;
          }
        } else if (MAILER == 'DM42API') {
          $emailclient= new DM42APIEmailClient(DM42APIAccessKey,DM42APISecretKey);
          $emailmessage= new DM42APIEmailMessage();
          $emailmessage->addTo($email);
          $emailmessage->addFrom(EmailAddress);
          $emailmessage->addText($resetemail);
          $emailmessage->addSubject("Tagged Logger: Pasword Reset");
          $emailclient->sendEmail($emailmessage); 
          return true;
        } else {
          // No Mailer - just pretend everything is fine
          return true;
        }
    }

    function addUser ($email,$password) {
        $newuser= \ORM::forTable("users")->create();
        if ($newuser) {
            $userid=uniqid();
            $enc_key=loadPWKey();
            $enchash = PasswordLock::hashAndEncrypt($password, $enc_key);
            $newuser->set('password',$enchash);
            $newuser->set('email',$email);
            $newuser->set('meta','');
            $newuser->set('userid',$userid);
            $newuser->save();
            if ($newuser->get('id')) return $userid;
        }
        return null;
    }

    function authenticateuser($username,$password) {
        global $app;
        global $currentuserid;
        
        if ('' === $username) {
            return false;
        }
        $user = \ORM::forTable("users")->
            select_many('id','email','password','meta','userid')->
            where(
                array(
                    'email'=>"$username",
                )
            )->
            find_one();
            
        if (false === $user) {
            $app->getContainer()->logger->addNotice("SECURITY: Invalid User (".$username.").\n");
            return false;
        }
        
            $enc_key=loadPWKey();
        
        $enchashpw = PasswordLock::hashAndEncrypt($password, $enc_key);
      
        if (!PasswordLock::decryptAndVerify($password,$user->get('password'), $enc_key)) {
            $app->getContainer()->logger->addError("SECURITY: Invalid Password for user (".$username.").\n");
            return false;            
        } 
        $_SESSION["userid"]=$user->get('id');
        $_SESSION["system_userid"]=$user->get('id');
        $_SESSION["current_userid"]=$user->get('userid');
        $currentuserid=$user->get('userid');
        return true;
    }

    function webHTMLHeader () {
        $html="<head>\n";
        $html.='<meta name="viewport" content="width=device-width, initial-scale=1">';
        $html.='<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>';
        $html.='<script src="'.JSRESOURCEURL.'/tlog.js"></script>';
        $html.='<link rel="stylesheet" href="'.CSSRESOURCEURL.'/tlog.css">';
        $html.='<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">';
        $html.='<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">';
        $html.='<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>';
        $html.='</head>';
        $html.="<body>\n";
        $html.="<div class='container' style='max-width:600px;'>\n";
        return $html;
    }
    function webHTMLFooter () {
        $html="</div>";
        $html.="</body>";
        return $html;
    }

    function addUserToLog ($userid,$logid) {

         $userinfo=ORM::forTable("users")->where("userid",$userid)->find_one();
         if ($userinfo) {
             $usermeta=dm42_get_meta_by_id("users",$userid,"meta","userid");
             $logmeta=dm42_get_meta_by_id("logs",$logid,"meta","logid");
             $logmeta["users"][$userid]["write"]=0;
             $usermeta["logs"][]=$logid;
             if (dm42_update_meta_by_id("logs",$logid,$logmeta,"meta","logid")) {
               dm42_update_meta_by_id("users",$userid,$usermeta,"meta","userid");
             }
             
         }

	}

    function webSignup ($formdata) {

        $skipform=0;
        $form="";
        if ($formdata["FormID"]=="Signup") {
            if (!isset($formdata["invite"]) ||
                  $formdata["invite"]!=INVITETOKEN) {
              $skipform = 0;
              $form.="<h2>Bad or missing token</h2>";
              $form.="The token you entered is invalid or may have expired.";
              $form.="<BR><BR>";
            } else {
              $skipform = 1;
              $newuser = isset($formdata["email"]) ? filter_var($formdata['email'],FILTER_SANITIZE_EMAIL) : null;
              $newuser = filter_var($newuser,FILTER_VALIDATE_EMAIL);
              if ($newuser) {
                $newuserinfo=ORM::forTable("users")->where("email",$newuser)->find_one();
                if (!$newuserinfo) {
                  $newuserid=addUser($newuser,uniqid());
                  newResetPWToken ($newuser);
                  $form.="<h3>User Created</h3>";
                  $form.="A link has been sent to your email so that you may ";
                  $form.="set your password.";
                  
                  global $defaultlogs;
                  foreach ($defaultlogs as $logid) {
                    addUserToLog ($newuserid,$logid);
                  }
                } else {
                  $form.="<h3>User already exists</h3>";
                  $form.="If you are having trouble logging in, you may ";
                  $form.="<a href='".HTMLURL."/pwreset'>request a new password.</a>";
                }
              } else {
                  $form.="<h3>Error in email address</h3>";
                  $form.="The email address you entered is not valid.  Please try again.";
                  $skipform=0;
              }
            }
        }
        if (!$skipform) {
        
            ob_start();
            Form::open ("Signup",null,Array("noLabel" => true,"class"=>"col-xs-12 col-sm-12 col-md-12 col-lg-12","view"=>"Vertical"));
            echo "<legend>Sign up</legend>";
            Form::Hidden ("FormID","Signup");
            Form::Textbox ("Email Address", "email", array("required" => 1,"noLabel"=>true,"class"=>"col-xs-12 col-sm-12 col-md-12 col-lg-12"));
            Form::Textbox ("Invite Token","invite",array("value"=>$formdata["invite"],"required"=>1,noLabel=>true,"class"=>"col-xs-12 col-sm-12 col-md-12 col-lg-12"));
            Form::Button ("Sign up");
            echo "<a href='".HTMLURL."' class='btn btn-default'>Cancel</a>";
            //Form::Button ("Cancel", "button", array("onclick" => "go('".HTMLURL."');"));
            Form::close (false);
            $form.=ob_get_contents();
            ob_end_clean();
        }
        
        $form.=webHTMLFooter();
        return $form;
    }

    function webAuthForm () {

        ob_start();
            echo webHTMLHeader();
            Form::open ("Login",null,Array("noLabel" => true,"class"=>"col-xs-12 col-sm-12 col-md-12 col-lg-12","view"=>"Vertical"));
            echo "<legend>Login</legend>";
            Form::Hidden ("FormID","Login");
            Form::Textbox ("Email Address:", "email", array("required" => 1,"noLabel"=>true,"class"=>"col-xs-12 col-sm-12 col-md-12 col-lg-12"));
            Form::Password ("Password:", "password", array("required" => 1));
            Form::Button ("Login");
            Form::Button ("Cancel", "button", array("onclick" => "history.go(-1);"));
            Form::close (false);
            echo "<div><a href='".HTMLURL."/signup'>Sign up for an account!</a></div>";
            echo webHTMLFooter();
        $form=ob_get_contents();
        ob_end_clean();
        return $form;
    }
    
    function webHiddenSearchForm ($formdata) {
        $html="";
        $html.="<div class='hiddenform'>";
        ob_start();
        Form::open ("ShowLog",null,Array("shared"=>true,"id"=>"HiddenSearchForm"));
        Form::Hidden ("FormID","ShowLog");
        Form::Hidden ("logid",$formdata["logid"]);
        Form::Hidden ("Search","",Array("id"=>"HiddenSearchString"));
        Form::Hidden ("perpage",isset($formdata["perpage"]) ? $formdata["perpage"] : 20 );
        Form::close (false);
        $html.=ob_get_contents();
        ob_end_clean();
        $html.="</div>";
        return $html;
    }
    
    function getMyLogList($type="all") {
        //TYPES: all, owned, writable
        if (!isset($_SESSION["userid"])) {return Array();}
        $myuserid=$_SESSION["userid"];
        $usermeta=dm42_get_meta_by_id("users",$myuserid);
        if (!isset($usermeta['logs'])) {return Array();}
        $loglist=Array();
        $logs=$usermeta["logs"];
        foreach ($logs as $logid) {
            $loginfo=ORM::forTable("logs")->where("logid",$logid)->find_one();
            if (!$loginfo) {continue;}
            if ($type!="all") {
              if ($type=="owned" && ($loginfo->get("owner_id")!=$_SESSION["userid"])) {continue;}
              if ($type=="writable") {
                  $logmeta=dm42_maybe_unjson($loginfo->get("meta"));
                  if ((!$logmeta["users"][$_SESSION["current_userid"]]["write"]) &&
                      ($loginfo->get("owner_id")!=$_SESSION["userid"])) {
                      continue;
                  }
              }
            }
            
            $logmeta=dm42_get_meta_by_id("logs",$logid,"meta","logid");
            if (isset($logmeta["name"])) {
                $name = $logmeta["name"];
            } else {
                $name = $logid;
            }
            $loglist[$logid]=$name;
        }
        return $loglist;
    }
    
    function ParseTags ($logid) {
        $logid=filter_var($logid,FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
        $unparsedcount=0;
        $max=50;
        while ($unparsedcount == 0 || $unparsecount == $max) {
            $unparsed=ORM::forTable("logdata_".$logid)->
                where ("parsed",0)->
                limit ($max)->
                find_many();
            foreach ($unparsed as $entryrow) {
                $entry=$entryrow->get("entry");
                $tokens=preg_split('/\s+/',$entry);
                $first=true;
                $parsed_entry="";
                $deleteprevious=ORM::forTable("logtags_".$logid)->where("logentryid",$entryrow->get("id"))->delete_many();
                foreach ($tokens as $token) {
                    $tag="";
                    if (preg_match("/([a-zA-Z0-9]+):(.*)/",$token,$results)) {
                        $tag=$results[1].":";
                        $value=$results[2];
                    }
                    
                    if (preg_match("/\#(.*)/",$token,$results)) {
                        $tag="#";
                        $value=$results[1];
                    }
                    
                    if ($tag!="") {
                        $newtag=ORM::forTable("logtags_".$logid)->create();
                         
                        $newtag->set("tag",$tag);
                        $newtag->set("entry",$value);
                        $newtag->set("logentryid",$entryrow->get("id"));
                        $newtag->save();
                    }
                    $entryrow->set("parsed",1);
                    $entryrow->save();
                }
            }
            $unparsedcount=count(unparsed);
        }
    }
    function FormAddEntry ($formdata) {
	global $app;
        $form = "";
        if (isset($formdata["FormID"]) && $formdata["FormID"]=="AddEntry") {
            $failed=0;
            $logid=filter_var($formdata["logid"],FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
           
            //TODO: Check user has "write" permission.
            $logmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");
            $loginfo=ORM::forTable("logs")->where("logid",$formdata["logid"])->find_one();
            if (!$loginfo) {
                return "<h2>Requested log appears not to exist.</h2>";
            }
            if (($logmeta["users"][$_SESSION["current_userid"]]["write"] != 1) && 
                ($loginfo->get("owner_id") != $_SESSION["userid"])) {
                return "<h2>You do not have permission to write to this log.</h2>";
            }
            
            if (!$failed) {
                $newentries=preg_split("/(\r\n|\r|\n)---(\r\n|\r|\n)/",$formdata["Entry"]);
                //$newentries=explode("\r---\r",$formdata["Entry"]);
                  foreach ($newentries as $newentrydata) {
                    $newentry=ORM::forTable("logdata_".$logid)->create();
                    $entrydata=$formdata["Prefix"]." ".$newentrydata." ".$formdata["Suffix"];
                    $newentry->set("entry",$entrydata);
                    $newentry->set("parsed",0);
                    $newentry->set_expr("datestamp","NOW()");
                    try {
		        $newentry->save();
                    } catch (Exception $e) {
                        $failed=1;
                        $app->getContainer()->logger->addError("Unable to add log entry:".print_r($entrydata,true)."\n");
                    }
                  }
            }
            
            if ($failed) {
                $form.="<div class='dm42error'>Unable to add log value.  Log may not exist or is not writeable.</div>\n";
            } else {
                ParseTags($logid);
            }
        }
        
        ob_start();
        $myloglist=getMyLogList("writable");
        if (count($myloglist)>0) {
            echo "<h4>Add Log Entry</h4>\n";
            $prefix=isset($formdata["Prefix"]) ? $formdata["Prefix"] : "";
            $suffix=isset($formdata["Suffix"]) ? $formdata["Suffix"] : "";
            Form::open ("AddEntry",null,Array("view" => "Vertical"));
            Form::Hidden ("FormID","AddEntry");
            Form::Select ("Log","logid",$myloglist,array("value"=>$formdata["logid"]));
            Form::Textbox ("Prefix","Prefix",array("value"=>$prefix));
            Form::Textarea ("Log Entry:","Entry",array("required"=>1));
            Form::Textbox ("Suffix","Suffix",array("value"=>$suffix));
            Form::Button ("Add");
            Form::Button ("Reset","reset");
            Form::close (false);
            $form.=ob_get_contents();
            ob_end_clean();
        } else {
            $form.="<h2>No writable logs.</h2>";
            $form.="You have no writable logs.  Add a new log by using 'Log Settings' in the menu above.";
        }
        return $form;
        
    }

    function logSearch ($search,$logtable,$logtagstable,$hiddentags=Array(),$limit=50,$page=1) {

          if (!dm42_tableexists($logtable)) {
              return null;
          }
          $logdata=ORM::forTable($logtable)->distinct();
          if (is_array($search)) {
              $searchstring=implode(' ',$search);
          } else {
              $searchstring=$search;
          }
          if ($searchstring!="" && $searchstring!="Untagged") {
            $tokens=preg_split('/\s+/',$searchstring);
            $joincount=1;
            foreach ($tokens as $token) {
                
                $tag=null;
                $value=null;
                if (preg_match("/([a-zA-Z0-9]+):(.*)/",$token,$results)) {
                    $tag=$results[1].":";
                    $value=$results[2];
                    $testrestriction=$tag;
                }
                    
                if (preg_match("/\#(.*)/",$token,$results)) {
                    $tag="#";
                    $value=$results[1];
                    $testrestriction=$token;
                }

                if ($tag) {
                  $hidden=0;
                  if (in_array($testrestriction,$hiddentags)) {
                    $hidden=1;
                  }
                }


                if ($tag==null || $value==null || $hidden==1) {
                    continue;
                }
                
                $jointablealias=$logtagstable."_".$joincount;
                $logentryidalias="logentryid_".$joincount;
                $joincount++;

                $orderby="asc";
                if ($token[0] == '<') {
                  $orderby="asc";
                  $token=substr($token,1);
                }
                if ($token[0] == '>') {
                  $orderby="desc";
                  $token=substr($token,1);
                }

                switch ($token[0]) {
                    case "-":
                        $token=substr($token,1);
                        if ($token[0]=="~") {
                            $wherecmd=$logtable.".id not in (select logentryid as ".$logentryidalias." from ".$logtagstable." where tag = ? and entry like ?)";
                            $logdata=$logdata->where_raw($wherecmd,array($tag,$value));
                        } else {
                            $wherecmd=$logtable.".id not in (select logentryid as ".$logentryidalias." from ".$logtagstable." where tag = ? and entry = ?)";
                            $logdata=$logdata->where_raw($wherecmd,array($tag,$value));
                        }
                        break;
                    case "?":
                        $token=substr($token,1);
                        if ($token[0]=="<") {
                          $token=substr($token,1);
                          $logdata=$logdata->
                            where($jointablealias.'.tag',$tag)->
                            where_lte($jointablealias.'.entry',$value)->
                            join($logtagstable,array($logtable.'.id','=',$jointablealias.'.logentryid'),$jointablealias);
                        } else if ($token[0]==">") {
                        $token=substr($token,1);
                        $logdata=$logdata->
                            where($jointablealias.'.tag',$tag)->
                            where_gte($jointablealias.'.entry',$value)->
                            join($logtagstable,array($logtable.'.id','=',$jointablealias.'.logentryid'),$jointablealias);
                        } else if ($token[0]=="=") {
                        $token=substr($token,1);
                        $logdata=$logdata->
                            where($jointablealias.'.tag',$tag)->
                            where($jointablealias.'.entry',$value)->
                            join($logtagstable,array($logtable.'.id','=',$jointablealias.'.logentryid'),$jointablealias);
                        }
                        break;
                    case "~":
                        $token=substr($token,1);
                        if ($token[0]=="-") {
                          $token=substr($token,1);
                          $wherecmd="id in (select logentryid from ".$logtagstable." where tag = ? and entry not like ?)";
                          $logdata=$logdata->
                                where($jointablealias.'.tag',$tag)->
                                where_not_like($jointablealias.'.entry',$value)->
                                join($logtagstable,array($logtable.'.id','=',$jointablealias.'.logentryid'),$jointablealias);
                        } else {
                          $wherecmd="id in (select logentryid from ".$logtagstable." where tag = ? and entry like ?)";
                          $logdata=$logdata->
                                where($jointablealias.'.tag',$tag)->
                                where_like($jointablealias.'.entry',$value)->
                                join($logtagstable,array($logtable.'.id','=',$jointablealias.'.logentryid'),$jointablealias);
                        }
                        if ($orderby!=null) {
                            $orderbycommand="order_by_".$orderby;
                            $logdata=$logdata->$orderbycommand($jointablealias.'.entry');
                        }
                        break;
                    default:
                        $wherecmd="id in (select logentryid from ".$logtagstable." where tag = ? and entry = ?)";
                        $logdata=$logdata->
                                where($jointablealias.'.tag',$tag)->
                                where($jointablealias.'.entry',$value)->
                                join($logtagstable,array($logtable.'.id','=',$jointablealias.'.logentryid'),$jointablealias);
                } 
            }
          }

          if ($searchstring=="Untagged") {
              $wherecmd="id not in (select logentryid from ".$logtagstable.")";
              $logdata=$logdata->where_raw($wherecmd);
          }
          $logdata=$logdata->order_by_desc($logtable.'.datestamp');
          $logdata=$logdata->limit($limit);

          if (is_numeric($page)) {
              $offset=($page-1)*$limit;
              $logdata=$logdata->offset($offset);
          }
          
          $logdata=$logdata->select_many(array("logentry" => "$logtable.entry",'id'=>"$logtable.id",'datestamp'=>"$logtable.datestamp"));
          try {
            $logdata=$logdata->find_many();
          } catch (Exception $e) {
            $pdodb=$logdata->get_db();
            echo "<h3>Error:</h3><pre>";
            print_r($logdata);
            
            echo "\n\n";
            echo $e;
            echo "</pre>";
          }
        return $logdata;
    }
    
    function FormShowLog ($formdata,$params=Array()) {

            $_SESSION["showperpage"] = isset($_SESSION["showperpage"]) ? $_SESSION["showperpage"] : 50;
            $perpage=isset($formdata["perpage"]) ? $formdata["perpage"] : $_SESSION["showperpage"];
        if (isset($formdata["FormID"]) && $formdata["FormID"]=="ShowLog") {
            $perpage=$perpage+0;
            $_SESSION["showperpage"]=$perpage;
        }
        
        ob_start();
        echo webHiddenSearchForm($formdata);
        echo "<div class=\"col-xs-12 col-sm-12 col-md-12 col-lg-12\"'>\n";

        if (isset($formdata["editentry"])) {

          $logid=preg_replace("/([^a-zA-Z0-9])/","",$formdata["logid"]);
          $loginfo=ORM::forTable("logs")->where("logid",$logid)->find_one();
          $permitted=0;
          if ($loginfo) {
              $logmeta=dm42_maybe_unjson($loginfo->get("meta"));
              if ((isset($logmeta["users"][$_SESSION["current_userid"]]["write"]) &&
                        $logmeta["users"][$_SESSION["current_userid"]]["write"]) ||
                        $loginfo->get("owner_id") == $_SESSION["userid"]) {
                  $permitted=1;
              }
          }
          if ($permitted) {
              $logtable="logdata_".$logid;
              $logtagstable="logtags_".$logid;

            $newentry=ORM::forTable("logdata_".$logid)->where("id",$formdata["editentry"])->find_one();

            Form::open ("AddEntry",null,Array("view" => "Vertical"));
            Form::Hidden ("FormID","ShowLog");
            Form::Hidden ("Search",$formdata["Search"]);
            Form::Hidden ("perpage",$formdata["perpage"]);
            Form::Hidden ("logid",$formdata["logid"]);
            Form::Hidden ("EntryID",$formdata["editentry"]);
            Form::Hidden ("Save",true);
            Form::Hidden ("Log",$formdata["logid"]);
            Form::Textarea ("Log Entry:","Entry",array("required"=>1,"value"=>$newentry->get("entry")));
            Form::Button ("Save");
            Form::Button ("Reset","reset");
            Form::close (false);

            Form::open ("ShowLog",null,Array("shared"=>true));
            Form::Hidden ("FormID","ShowLog");
            Form::Hidden ("logid",$formdata["logid"]);
            Form::Hidden ("Search",$formdata["Search"]);
            Form::Hidden ("perpage",$formdata["perpage"]);
            Form::Button ("Quit without saving","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Quit"));
            Form::close (false);

                } else {
                    echo "<h3>You do not have write permission.</h3>";
                }            
        }

        if (isset($formdata["EntryID"]) && isset($formdata["Save"])) {
          $logid=preg_replace("/([^a-zA-Z0-9])/","",$formdata["logid"]);
          $loginfo=ORM::forTable("logs")->where("logid",$logid)->find_one();
          $permitted=0;
          if ($loginfo) {
              $logmeta=dm42_maybe_unjson($loginfo->get("meta"));
              if ((isset($logmeta["users"][$_SESSION["current_userid"]]["write"]) &&
                        $logmeta["users"][$_SESSION["current_userid"]]["write"]) ||
                        $loginfo->get("owner_id") == $_SESSION["userid"]) {
                  $permitted=1;
              }
          }
          if ($permitted) {
              $logtable="logdata_".$logid;
              $logtagstable="logtags_".$logid;
              $newentry=ORM::forTable("logdata_".$logid)->where("id",$formdata["EntryID"])->find_one();
              if ($newentry) {
                $entrydata=$formdata["Entry"];
                $newentry->set("entry",$entrydata);
                $newentry->set("parsed",0);
                try {
                  $newentry->save();
                } catch (Exception $e) {
                  $app->getContainer()->logger->addError("Unable to add log entry:".print_r($entrydata,true)."\n");
                }
              } else {
                echo "<h2>Weird, something went wrong.</h2>  The entry you're editing seems not to be there.  Maybe search and try again.\n";
              }
                ParseTags($logid);

          } else {
                    echo "<h3>You do not have write permission.</h3>";
                }            
        }
        
        if (!$params["hideform"] && !isset($formdata["hidesearch"])) {
            
            echo "<div class=\"col-xs-8 col-sm-8 col-md-8 col-lg-8\"'>\n";
            echo "<h4>Search</h4>\n";
            echo "</div>\n";
            echo "<div>\n";
                    Form::open ("ShowLog",null,Array("view" => "Vertical","shared"=>1,"class"=>"col-xs-1 col-sm-1 col-md-1 col-lg-1"));
                    Form::Hidden ("FormID","ShowLog");
                    Form::Hidden ("logid",$formdata["logid"]);
                    Form::Hidden ("Search",$formdata["Search"]);
                    Form::Hidden ("perpage",$formdata["perpage"]);
                    Form::Hidden ("enabledelete",1);
                    Form::Button ("<span class='glyphicon glyphicon-trash'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Enable Delete"));
                    Form::close (false);
                    Form::open ("ShowLog",null,Array("view" => "Vertical","shared"=>1,"class"=>"col-xs-3 col-sm-3 col-md-3 col-lg-3"));
                    Form::Hidden ("FormID","ShowLog");
                    Form::Hidden ("logid",$formdata["logid"]);
                    Form::Hidden ("Search","Untagged");
                    Form::Hidden ("perpage",$perpage);
                    Form::Button ("Search Untagged","submit",Array("class"=>"btn-xs"));
                    Form::close (false);
            echo "</div>\n";
            echo "<div class=\"col-xs-12 col-sm-12 col-md-12 col-lg-12\"'>\n";
            Form::open ("ShowLog",null,Array("view" => "Vertical"));
            Form::Hidden ("FormID","ShowLog");
            Form::Select ("Log","logid",getMyLogList("all"),array("value"=>$formdata["logid"]));
            Form::Textbox ("Search","Search",array("value"=>$formdata["Search"]));
            Form::Select ("Entries Per Page","perpage",array("20"=>"Show 20 results per page","50"=>"Show 50 results per page","100"=>"Show 100 results per page","200"=>"Show 200 results per page","500"=>"Show 500 results per page","1000"=>"Show 1000 results per page"),array("value"=>$_SESSION["showperpage"]));
            Form::Button ("Submit","submit");
            Form::Button ("Clear Search","button",Array("class"=>"btn-secondary","onclick"=>"javascript:hiddenSearchSubmit('');"));
            Form::close (false);

            echo "</div>";
        }
        
        if (isset($formdata["logid"]) && !isset($formdata["editentry"])) {
          echo "<style> .oddrow {} .evenrow {background-color: #CCCCFF;} </style>\n";
          $logid=preg_replace("/([^a-zA-Z0-9])/","",$formdata["logid"]);
          $loginfo=ORM::forTable("logs")->where("logid",$logid)->find_one();
          $permitted=0;
          if ($loginfo) {
              $logmeta=dm42_maybe_unjson($loginfo->get("meta"));
              if (isset($logmeta["users"][$_SESSION["current_userid"]]) ||
                        $loginfo->get("owner_id") == $_SESSION["userid"]) {
                  $permitted=1;
              }
          }
          if ($permitted) {
              $logtable="logdata_".$logid;
              $logtagstable="logtags_".$logid;

              if (isset($logmeta["users"][$_SESSION["current_userid"]]["write"]) &&
                        $logmeta["users"][$_SESSION["current_userid"]]["write"]=="1" ||
                        $loginfo->get("owner_id") == $_SESSION["userid"]) {
                  if (isset($formdata["deleteentry"])) {
                      $deletetags=ORM::forTable($logtagstable)->
                          where("logentryid",$formdata["deleteentry"])->
                          delete_many();
                      //$deletetags->delete_many();
                      $deleteentry=ORM::forTable($logtable)->
                          where("id",$formdata["deleteentry"])->
                          find_one();
                      $deleteentry->delete();
                  }
              }
              
              $usermeta=dm42_get_meta_by_id("users",$_SESSION["current_userid"],"meta","userid");

              $hide_log=explode(" ",$logmeta["hidetags"]);
              $unhide_log=explode(" ",$logmeta["unhidetags"]);
              $hide_user=explode(" ",$logmeta["users"][$_SESSION["current_userid"]]["hidetags"]);
              $unhide_user=explode(" ",$logmeta["users"][$_SESSION["current_userid"]]["unhidetags"]);
    
              $hide_tags=array_merge($hide_log,$hide_user);
              $hide_tags=array_diff($hide_tags,$unhide_user);
              $unhide_tags=array_merge($unhide_log,$unhide_user);
              $unhide_tags=array_diff($unhide_tags,$hide_user);

              if ($_SESSION["system_userid"]==$loginfo->get("owner_id")) {
                 $hide_tags=Array();
                 $unhide_tags=Array();
              }
   
              $logdata=logSearch($formdata["Search"],$logtable,$logtagstable,Array(),$perpage,$formdata["page"]);
            
              $logmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");
              $tags=$logmeta["tags"];
              $oddevenrow="odd";
              echo "<div class='col-xs-12 col-sm-12 col-md-12 col-lg-12'>";
              if (!$params["hidebuttons"]) {
                if (isset($formdata["page"]) and ($formdata["page"] > 1)) {
                  $prevpage=$formdata["page"]-1;
                  Form::open ("ShowLog",null,Array("noLabel" => true,"class"=>'col-xs-6 col-sm-6 col-md-6 col-lg-6' ,"shared"=>1));
                  Form::Hidden ("FormID","ShowLog");
                  Form::Hidden ("logid",$formdata["logid"]);
                  Form::Hidden ("Search",$formdata["Search"]);
                  Form::Hidden ("perpage",$perpage);
                  Form::Hidden ("page",$prevpage);
                  Form::Button ("Page ".$prevpage);
                  Form::close (false);
                } else {
                  echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'></div>";
                }
                if (count($logdata) == ($perpage)) {
                  if (isset($formdata["page"])) {
                    $nextpage=$formdata["page"]+1;
                  } else {
                    $nextpage=2;
                  } 
                  Form::open ("ShowLog",null,Array("noLabel" => true,"class"=>'col-xs-6 col-sm-6 col-md-6 col-lg-6',"shared"=>1));
                  Form::Hidden ("FormID","ShowLog");
                  Form::Hidden ("logid",$formdata["logid"]);
                  Form::Hidden ("Search",$formdata["Search"]);
                  Form::Hidden ("perpage",$perpage);
                  Form::Hidden ("page",$nextpage);
                  Form::Button ("Page ".$nextpage);
                  Form::close (false);
                }
              echo "<hr>";
              echo "</div>"; 
              
            }
              $Parsedown = new Parsedown();
              foreach ($logdata as $logentry) {
                echo "<div class='".$oddevenrow."row col-xs-12 col-sm-12 col-md-12 col-lg-12'>";
                echo "<div class='col-xs-10 col-sm-10 col-md-10 col-lg-10'>";
                $entry=$logentry->get("logentry");

                $tokens=preg_split('/(\s+)/',$entry,-1,PREG_SPLIT_DELIM_CAPTURE);

                $first=true;
                $hidden=0;
                $parsed_entry="";
                foreach ($tokens as $token) {
                    $tag="";
                    if (preg_match("/(#[[:alnum:]]+)/",$token,$results)) {
                        $parsed_tag=$token;
                        $tag=$token;
                        $value='';
                    } else if (preg_match("/([[:alnum:]_-]+):([[:alnum:]_-].*)/",$token,$results)) {
                        $parsed_tag=$token;
                        $tag=$results[1].":";
                        $value=$results[2];
                    }
                    if ($tag) {
                          if (in_array($tag,$unhide_tags)) {
                            $hidden=0;
                          } else if (in_array($tag,$hide_tags)) {
                            $hidden=1;
                          }
    
                        if ($hidden==1) {continue;}
    
                            $displaytag= "";
                            $displaytag.= "<button class='btn-xs btn btn-default' onclick=\"javascript:hiddenSearchSubmit('".$token."')\" />";
                            $displaytag.= $tag." </button>";
                        if (isset($tags[$tag])) {
                            $parsed_tag="";
                            if (preg_match("/(.*){tag}(.*)/",$tags[$tag],$tagresults)) {
                                $parsed_tag=preg_replace('/\{tag\}/',$value,$tags[$tag]);
                            } else {
                                $parsed_tag=$tags[$tag].$value;
                            }
                                $replacement=$displaytag.' '.$parsed_tag;
                        } else {
                                $replacement=$displaytag.$value;
                        }
                        $parsed_entry.=$replacement;
                        $alltags[$token] = isset($alltags[$token]) ? $alltags[$token]+1 : 1;
                    } else {
                        if ($hidden==1) {continue;}
                        $parsed_entry.=$token;
                    }
                    $first=false;
                }
                echo $Parsedown->text($parsed_entry);
                echo "<br /><span style='font-size:xx-small;weight:bold'>".$logentry->get('datestamp')."</span>";
                echo "</div>";
                //$parsed_entry.="<button class='btn-xs btn btn-primary' onclick='hiddenSearchAppendSubmit(".$token.")'><span class='glyphicon glyphicon-filter' style='font-size:6px;'></span></button>";
                echo "<div>";
                Form::open ("ShowLog",null,Array("shared"=>1,"class"=>"col-xs-1 col-sm-1 col-md-1 col-lg-1"));
                Form::Hidden ("FormID","ShowLog");
                Form::Hidden ("logid",$formdata["logid"]);
                Form::Hidden ("Search",$formdata["Search"]);
                Form::Hidden ("perpage",$formdata["perpage"]);
                Form::Hidden ("editentry",$logentry->get("id"));
                Form::Hidden ("hidesearch",true);
                Form::Button ("<span class='glyphicon glyphicon-pencil'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Edit"));
                Form::close (false);
                if ($formdata["enabledelete"]=="1") {
                    Form::open ("ShowLog",null,Array("shared"=>1,"class"=>"col-xs-1 col-sm-1 col-md-1 col-lg-1"));
                    Form::Hidden ("FormID","ShowLog");
                    Form::Hidden ("logid",$formdata["logid"]);
                    Form::Hidden ("Search",$formdata["Search"]);
                    Form::Hidden ("perpage",$formdata["perpage"]);
                    Form::Hidden ("deleteentry",$logentry->get("id"));
                    Form::Button ("<span class='glyphicon glyphicon-trash'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Delete"));
                    Form::close (false);
                }
                echo "</div>";
                echo "</div>";
                if ($oddevenrow=="odd") { $oddevenrow="even";} else {$oddevenrow="odd";}
              }
              //echo "</div>";
              if (!$params["hidebuttons"]) {
//Paging
                echo "<div class='col-xs-12 col-sm-12 col-md-12 col-lg-12'>";
                if (isset($formdata["page"]) and ($formdata["page"] > 1)) {
                  $prevpage=$formdata["page"]-1;
                  Form::open ("ShowLog",null,Array("noLabel" => true,"class"=>'col-xs-6 col-sm-6 col-md-6 col-lg-6' ,"shared"=>1));
                  Form::Hidden ("FormID","ShowLog");
                  Form::Hidden ("logid",$formdata["logid"]);
                  Form::Hidden ("Search",$formdata["Search"]);
                  Form::Hidden ("perpage",$perpage);
                  Form::Hidden ("page",$prevpage);
                  Form::Button ("Page ".$prevpage);
                  Form::close (false);
                } else {
                  echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'></div>";
                }
                if (count($logdata) == ($perpage)) {
                  if (isset($formdata["page"])) {
                    $nextpage=$formdata["page"]+1;
                  } else {
                    $nextpage=2;
                  } 
                  Form::open ("ShowLog",null,Array("noLabel" => true,"class"=>'col-xs-6 col-sm-6 col-md-6 col-lg-6',"shared"=>1));
                  Form::Hidden ("FormID","ShowLog");
                  Form::Hidden ("logid",$formdata["logid"]);
                  Form::Hidden ("Search",$formdata["Search"]);
                  Form::Hidden ("perpage",$perpage);
                  Form::Hidden ("page",$nextpage);
                  Form::Button ("Page ".$nextpage);
                  Form::close (false);
                }
              echo "</div>"; 
//search startpoints
                arsort($alltags);
                echo "<div class='col-xs-12 col-sm-12 col-md-12 col-lg-12'>";
                echo "<hr>";
                echo "<h4>Additional Search Options</h4>";
                echo "<h4>Include Only:</h4>";
                $tokens=preg_split('/\s+/',$formdata["Search"]);
                $tokens=array_flip($tokens);
                $alltags=array_diff_key($alltags,$tokens);
                $alltags=array_slice($alltags,0,25,true);
                foreach ($alltags as $token=>$count) {
                    global $mintagcount;
                    $mintagcount = isset($mintagcount) ? $mintagcount : 2;
                    if ($count >= $mintagcount) {
                        echo "<button class='btn-xs btn btn-default' onclick=\"javascript:hiddenSearchAppendSubmit('".$token."')\">";
                        echo $token." (".$count.")"."</button>";
                    }
                }
                echo "<h4>Exclude:</h4>";
                foreach ($alltags as $token=>$count) {
                    global $mintagcount;
                    $mintagcount = isset($mintagcount) ? $mintagcount : 2;
                    if ($count >= $mintagcount) {
                        echo "<button class='btn-xs btn btn-default' onclick=\"javascript:hiddenSearchAppendSubmit('-".$token."')\">";
                        echo $token." (".$count.")"."</button>";
                    }
                }
                echo "</div>";
            }
            }
        }
        echo "</div>\n";
        
        $form=ob_get_contents();
        ob_end_clean();
        return $form;
        
    }
    
    function headerButtons($includebuttons=Array()) {
            ob_start();
            $buttoncount=count($includebuttons)+1;
            $remaining=$buttoncount % 4;
            echo "<div id=\"hdrbuttons\" class=\"col-xs-12 col-sm-12 col-md-12 col-lg-12\"'>\n";
            Form::open ("Blank",null,Array("noLabel" => true,"class"=>'col-xs-3 col-sm-3 col-md-3 col-lg-3',"shared"=>true));
            Form::Hidden ("FormID","Blank");
            Form::Button ("<span class='glyphicon glyphicon-home'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Home"));
            Form::close(false);
            echo "\n";
            if (in_array("AddLog",$includebuttons)) {
                Form::open ("AddLog",null,Array("noLabel" => true,"class"=>'col-xs-2 col-sm-2 col-md-2 col-lg-2', "shared" => true));
                Form::Hidden ("FormID","AddLog");
                Form::Button ("<span class='glyphicon glyphicon-plus'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Create New Log"));
                Form::close(false);
                echo "\n";
            }
            if (in_array("UserOptions",$includebuttons)) {
                Form::open ("UserOptions",null,Array("noLabel" => true,"class"=>'col-xs-2 col-sm-2 col-md-2 col-lg-2', "shared" => true));
                Form::Hidden ("FormID","UserOptions");
                Form::Button ("<span class='glyphicon glyphicon-user'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"User Options"));
                Form::close(false);
                echo "\n";
            }
            if (in_array("LogOptions",$includebuttons)) {
                Form::open ("LogOptions",null,Array("noLabel" => true,"class"=>'col-xs-2 col-sm-2 col-md-2 col-lg-2', "shared" => true));
                Form::Hidden ("FormID","LogOptions");
                Form::Button ("<span class='glyphicon glyphicon-cog'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Log Settings"));
                Form::close(false);
                echo "\n";
            }
            if (in_array("Search",$includebuttons)) {
                Form::open ("ShowLog",null,Array("noLabel" => true,"class"=>'col-xs-2 col-sm-2 col-md-2 col-lg-2',"shared"=>true));
                Form::Hidden ("FormID","ShowLog");
                Form::Button ("<span class='glyphicon glyphicon-search'></span>","submit",Array("class"=>"btn-xs","data-toggle"=>"tool-tip","title"=>"Search"));
                Form::close(false);
                echo "\n";
            }
            if (isset($_SESSION["userid"])) {
                Form::open ("Logout",null,Array("noLabel" => true,"class"=>'col-xs-2 col-sm-2 col-md-2 col-lg-2',"shared"=>true));
                Form::Hidden ("FormID","Logout");
                Form::Button ("Log out","submit",Array("class"=>"btn-xs"));
                Form::close(false);
                echo "\n";
            }
            echo "</div>\n";
            echo "<div style='width:100%;'><br></div>";
            $form=ob_get_contents();
            ob_end_clean();
            return $form;

    }
    
    function FormLogOptions($formdata) {
        if (!isset($formdata["logid"])) {
            $mylogs=getMyLogList("owned");
            if (count($mylogs)>0) {
                ob_start();
                echo "<h4>Edit Log Options</h4>\n";
                Form::open ("LogOptions",null,Array("noLabel" => true,"view"=>"Vertical"));
                Form::Hidden ("FormID","LogOptions");
                Form::Select ("Log","logid",$mylogs);
                Form::Button ("Edit");
                Form::close(false);
                echo "<hr>\n";
                $form=ob_get_contents();
                ob_end_clean();
            } else {
               $form="<h2>No logs</h2>";
               $form.="You have no logs.  Click on the + above to add a new log.";
            }
            return $form;
        }
        $loginfo=ORM::forTable("logs")->where("logid",$formdata["logid"])->find_one();
        if (!$loginfo || $_SESSION["userid"]!=$loginfo->get("owner_id") ) {
            return "<h2>You do not have permission to edit this log configuration</h2>";
        }
        $newconfig=Array();
        
        if (isset($formdata["update"]) && $formdata["update"]==1) {
            $oldmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");
            $newconfig["name"]=filter_var($formdata["logname"],FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
            $newconfig["hidetags"]=filter_var($formdata["hidetags"],FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
            $newconfig["unhidetags"]=filter_var($formdata["unhidetags"],FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
          
            foreach ($formdata["tag"] as $tagkey => $tag) {
                $tag=filter_var($formdata["tag"][$tagkey],FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
                //$replacement=filter_var($formdata["replacement"][$tagkey],FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
                $replacement=$formdata["replacement"][$tagkey];
                if (!strlen(trim($tag))>0 || !strlen(trim($replacement))>0) { continue; }
                $newconfig["tags"][$tag]=$replacement;                
            }
            $newconfig["users"]=$oldmeta["users"];
            dm42_update_meta_by_id("logs",$formdata["logid"],$newconfig,"meta","logid");
            $logmeta=$newconfig;   
        } else {
            $logmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");
        }
        if (!$logmeta) {
            return FormLogOptions(Array());
        }

        ob_start();
            echo "<div class='clearfix'></div>";
        Form::open ("LogOptions",null,array("noLabel" => true,"view"=>"Vertical"));
        Form::Hidden ("FormID","LogOptions");
        Form::Hidden ("logid",$formdata["logid"]);
        Form::Hidden ("update","1");
        Form::Textbox("Log Name","logname",Array("value" => $logmeta["name"]));
        if (isset($logmeta["tags"])) {
            $counter=0;
            echo "<div class='row'>";
            echo "<div class='col-xs-4 col-sm-4 col-md-4 col-lg-4'>";
            echo "<label>Tag</label>";
            echo "</div><div class='col-xs-8 col-sm-8 col-md-8 col-lg-8'>";
            echo "<label>Replacement</label>";
            echo "</div>";
            echo "</div>";
            foreach ($logmeta['tags'] as $tag => $replacement) {
                $counter++;
                $tag=filter_var($tag,FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
                //$replacement=filter_var($replacement,FILTER_SANITIZE_STRING,FILTER_FLAG_STRIP_LOW && FILTER_FLAG_STRIP_HIGH && FILTER_FLAG_STRIP_BACKTICK);
                echo "<div class='row'>";
                echo "<div class='col-xs-4'>";
                Form::Textbox("","tag[".$counter."]",Array("value" => $tag));
                echo "</div>";
                echo "<div class='col-xs-8'>";
                Form::Textbox("","replacement[".$counter."]",Array("value"=>$replacement));
                echo "</div>";
                echo "</div>";
            }

        }
            echo "<div class='row'>";
            echo "<div class='col-xs-4 col-sm-4 col-md-4 col-lg-4'>";
            echo "<label>New Tag</label>";
            echo "</div><div class='col-xs-8 col-sm-8 col-md-8 col-lg-8'>";
            echo "<label>New Replacement</label>";
            echo "</div>";
            echo "</div>";
                echo "<div class='row'>";
                echo "<div class='col-xs-4'>";
        Form::Textbox("","tag[]",Array("value" => ""));
                echo "</div>";
                echo "<div class='col-xs-8'>";
        Form::Textbox("","replacement[]",Array("value"=>""));
                echo "</div>";
                echo "</div>";
        //TODO add permission options
            echo "<div class='row'>";
            echo "<div class='col-xs-12 col-sm-12 col-md-12 col-lg-12'>";
            echo "<h4>Hide Tags</h4>";
            echo "Begin hiding text when these tags are encountered.";
            Form::Textbox("Hide tags:","hidetags",Array("value"=>$logmeta["hidetags"]));
            echo "Stop hiding text when these tags are encountered.";
            Form::Textbox("Unhide tags:","unhidetags",Array("value"=>$logmeta["unhidetags"]));
            echo "</div>";
            echo "</div>";
        
        Form::Button ("save");
        Form::close(false);

        echo formLogOptionsUsers($formdata);
        echo formDeleteLog($formdata);
        $form=ob_get_contents();
        ob_end_clean();
        return $form;
        
        
    }
    function formDeleteLog($formdata) {

        $logmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");
        ob_start();
        if (($formdata["FormID"] != "DeleteLog") ||
            (($formdata["FormID"] == "DeleteLog") && 
                  $formdata["confirmdelete"]!="1")) {
          Form::open ("DeleteLog",null,Array("noLabel" => false,"view"=>"Vertical"));
          Form::Hidden ("FormID","DeleteLog");
          Form::Hidden ("logid",$formdata["logid"]);
          if ($formdata["FormID"] == "DeleteLog") {
            Form::HTML ("<h3>WARNING!!!</h3>");
            Form::HTML ("You are about to delete the log <b>".$logmeta["name"]."</b>.");
            Form::HTML ("<b>THIS IS A PERMANENT ACTION and CAN NOT BE UNDONE.</b>");
            Form::Hidden ("confirmdelete","1");
            Form::HTML ("Click the 'Delete Log' button again to confirm.  Otherwise, click 'Home' above.");
          }

          Form::Button ("Delete Log");
          Form::close(false);
          $form=ob_get_contents();
          ob_end_clean();
          return $form;
        }

        
        $db=ORM::get_db();
        foreach (Array("logdata","logtags","logarchive","logarchivetags") as $tableprefix) {
            $query = "drop table if exists ".$tableprefix."_".$formdata["logid"].";";
            try {
              $result=$db->exec("$query");
            } catch (Exception $e) {
            }
        }

        foreach ($logmeta["users"] as $user=>$userinfo) {
            $usermeta=dm42_get_meta_by_id("users",$user,"meta","userid");
            if(($key = array_search($formdata["logid"], $usermeta["logs"])) !== false) {
               unset($usermeta["logs"][$key]);
            }
            dm42_update_meta_by_id("users",$user,$usermeta,"meta","userid");
        }
            $usermeta=dm42_get_meta_by_id("users",$_SESSION["current_userid"],"meta","userid");
            if(($key = array_search($formdata["logid"], $usermeta["logs"])) !== false) {
               unset($usermeta["logs"][$key]);
            }
            dm42_update_meta_by_id("users",$_SESSION["current_userid"],$usermeta,"meta","userid");

        $delrecord=ORM::forTable("logs")->where("logid",$formdata["logid"])->find_one();
        if ($delrecord) {$delrecord->delete();}

        return "<h2>Deleted log: ".$logmeta["name"]."<h2>";
    }

    function formLogOptionsUsers($formdata) {
        if (!isset($formdata["logid"])) {
          return "";
        }

        $logmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");

        if (isset($formdata["FormID"]) &&
            $formdata["FormID"] == "LogOptionsUsers") {
            foreach ($formdata["delete"] as $deluser=>$userdata) {
                unset($formdata["unhidetags"][$deluser]);
                unset($formdata["hidetags"][$deluser]);
                unset($logmeta["users"][$deluser]);
                $userinfo=ORM::forTable("users")->where("userid",$user)->find_one();
                if (!$userinfo) {
                    continue;
                }
                $usermeta=dm42_maybe_unjson($userinfo->get("meta"));
                if(($key = array_search($formdata["logid"], $usermeta["logs"])) !== false) {
                    unset($usermeta["logs"][$key]);
                }
                $userinfo->set('meta',dm42_maybe_json($usermeta));
                $userinfo->save();
            }

            $newuser = isset($formdata["newuser"]) ? filter_var($formdata['newuser'],FILTER_SANITIZE_EMAIL) : null;
            if ($newuser) {
                $newuserinfo=ORM::forTable("users")->where("email",$newuser)->find_one();
                if (!$newuserinfo) {
                  $newuserid=addUser($newuser,uniqid());
                  newResetPWToken ($email);
                } else {
                  $newuserid=$newuserinfo->get('userid');
                  newLogSubscriberNotify ($email,$logmeta["name"]);
                }
                $newuserinfo=ORM::forTable("users")->where("email",$newuser)->find_one();
                $usermeta=dm42_maybe_unjson($newuserinfo->get("meta"));
                $usermeta["logs"][]=$formdata["logid"];
                $usermeta["logs"]=array_unique($usermeta["logs"]);
                $newuserinfo->set('meta',dm42_maybe_json($usermeta));
                $newuserinfo->save();
                $writable = isset($formdata["write"]["new"]) ? 1 : 0;
                $logmeta["users"][$newuserid]["write"]=$writable;
                $logmeta["users"][$newuserid]["unhidetags"]=$formdata["unhidetags"]["new"];
                $logmeta["users"][$newuserid]["hidetags"]=$formdata["hidetags"]["new"];
            }
            unset ($formdata["write"]["new"]);
            unset ($formdata["unhidetags"]["new"]);
            unset ($formdata["hidetags"]["new"]);

            foreach ($formdata["unhidetags"] as $user=>$unhide) {
                if (isset($logmeta["users"][$user])) {
                  $logmeta["users"][$user]["unhidetags"]=$unhide;
                }
                if ($formdata["write"][$user][0]==1) {
                  $logmeta["users"][$user]["write"]=1;
                } else {
                  $logmeta["users"][$user]["write"]=0;
                }
            }
            foreach ($formdata["hidetags"] as $user=>$hide) {
                if (isset($logmeta["users"][$user])) {
                  $logmeta["users"][$user]["hidetags"]=$hide;
                }
            }
       
            $logmeta=dm42_update_meta_by_id("logs",$formdata["logid"],$logmeta,"meta","logid");
        }


        ob_start();
        $logmeta=dm42_get_meta_by_id("logs",$formdata["logid"],"meta","logid");
        Form::open ("LogOptionsUsers",null,Array("noLabel" => false,"view"=>"Vertical"));
        Form::Hidden ("FormID","LogOptionsUsers");
        Form::Hidden ("logid",$formdata["logid"]);

        if (isset($logmeta["users"])) {
            Form::HTML("<h2>Authorized Users</h2>");
            foreach ($logmeta["users"] as $user=>$userdata) {
               $userinfo=ORM::forTable("users")->where("userid",$user)->find_one();
               if (!$userinfo) {
                   continue;
               }
	       $usermeta=dm42_maybe_unjson($userinfo->get("meta"));
               $username=isset($usermeta["name"]) ? $usermeta["name"] : $userinfo->get("email");
               Form::HTML("<h4>$username</h4>");
               Form::HTML('<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">');
               Form::Checkbox("","delete[".$user."]",Array("1"=>"delete"),Array("class"=>"col-xs-6 col-sm-6 col-md-6 col-lg-6","noLabel"=>"true"));
               if ($userdata["write"]=="1") {
                   $checked=1; 
               } else {
                   $checked=0; 
               }
               Form::Checkbox("","write[".$user."]",Array("1"=>"writable"),Array("value"=>$checked,"class"=>"col-xs-6 col-sm-6 col-md-6 col-lg-6","noLabel"=>"true"));
               Form::HTML("</div>");
               echo "Start hiding text when these tags are encountered (Overrides or adds to log settings above).";
               Form::Textbox("Hide Tags","hidetags[".$user."]",Array("value"=>$userdata["hidetags"]));
               echo "Stop hiding text when these tags are encountered (Overrides or adds to log settings above).";
               Form::Textbox("Unhide Tags","unhidetags[".$user."]",Array("value"=>$userdata["unhidetags"]));
            }
        }
        echo "<h4>Add user</h4>";
        Form::Textbox("Email address","newuser");
        Form::Checkbox("","write[new]",Array("1"=>"Writable"),Array("value"=>1));
        echo "Start hiding text when these tags are encountered (Overrides or adds to log settings above).";
        Form::Textbox("Hide Tags","hidetags['new']",Array("value"=>$userdata["hidetags"]));
        echo "Stop hiding text when these tags are encountered (Overrides or adds to log settings above).";
        Form::Textbox("Unhide Tags","unhidetags['new']",Array("value"=>$userdata["unhidetags"]));
        
        Form::Button ("Update Users");
        Form::close(false);
        echo "<hr>\n";
        $form=ob_get_contents();
        ob_end_clean();
        return $form;
    }

    function Auth ($request, $response, $next) {

        if (isset($_SESSION["userid"])) {
            return $next($request,$response);    
        }

        $data=$request->getParsedBody();
        
        if (isset($data["FormID"]) && $data["FormID"]=="Login" &&
            isset($data["email"]) && isset($data["password"])) {
            $email=filter_var($data['email'],FILTER_SANITIZE_EMAIL);
            if ('' === $email) {
                $response=$response->withStatus(200)->write(webAuthForm());
                return $response;
            }

            $password=$data["password"];

            if (authenticateuser($email,$password)) {
                $response=$next($request,$response);
                return $response;
            }
        }
        $response=$response->withStatus(200)->write(webAuthForm());
        return $response;
    }

    function renderHTML ($request,$response,$args) {
        global $defaultlogs;
      
        $formdata=array_merge($request->getQueryParams(),$request->getParsedBody());
        $html=webHTMLHeader();
        $html.="<div class='row'>\n";
        $html.="<div class='col-sm-12 col-md-12 col-lg-12'><!-- ContentDiv -->\n";
        $html.="<div>";
        $html.="</div>";
            $_SESSION["logid"]=$formdata["logid"];
            $_SESSION["mostrecentlogid"]=isset($formdata["logid"]) ? $formdata["logid"] : $_SESSION["mostrecentlogid"];
            $_SESSION["mostrecentlogid"]=isset($_SESSION["mostrecentlogid"]) ? $_SESSION["mostrecentlogid"] : $defaultlogs[0];
            if (isset($_SESSION["system_userid"])) {
                $usermeta=dm42_get_meta_by_id("users",$_SESSION["system_userid"]);
            }
        if (isset($formdata["FormID"])) {
            switch ($formdata["FormID"]) {
                case "AddLog":
                  if (!isset($formdata["logid"])) {
                    //$formdata["logid"]=isset($usermeta["defaultlog"]) ? $usermeta["defaultlog"] : null;
                  }
                  $html.=headerButtons(Array("UserOptions","LogOptions","Search"));
                  $newlogid=newLog($_SESSION["userid"]);
                  if ($newlogid) {
                    $fauxformdata=Array("logid"=>$newlogid);
                    $html.=FormLogOptions($fauxformdata);
                  } else {
                    $html.="<h2>Error: Could not create new log.<h2>";
                  }
                  break;
                case "ShowLog":
                  if (!isset($formdata["logid"])) {
                     $formdata["logid"] = isset($_SESSION["mostrecentlogid"]) ? $_SESSION["mostrecentlogid"] : $usermeta["defaultlog"];
                    //$formdata["logid"]=isset($usermeta["defaultlog"]) ? $usermeta["defaultlog"] : null;
                  }
                  $html.=headerButtons(Array("UserOptions","LogOptions","Search"));
                  $html.=FormShowLog($formdata);
                  break;
                case "LogOptions":
                case "LogOptionsUsers":
                  $html.=headerButtons(Array("AddLog"));
                  $html.=FormLogOptions($formdata);
                  break;
                case "DeleteLog":
                  $html.=headerButtons(Array());
                  $html.=formDeleteLog($formdata);
                  break;
                case "UserOptions":
                  $html.=headerButtons(Array());
                  $html.=formResetPW($formdata);
                  break;
                case "ResetPW":
                  $html.=headerButtons(Array());
                  $formdata=array_merge($formdata,$request->getQueryParams());
                  $html.=formResetPW($formdata);
                  break;
                case "Signup":
                  //$html.=headerButtons(Array());
                  $formdata=array_merge($formdata,$request->getQueryParams());
                  $html.=webSignup($formdata);
                  break;
                case "Logout":
                  session_destroy();
                  $html.="<h1>Logged Out.</h1>";
                  break;
                default:
                  if (!isset($formdata["logid"])) {
                     $formdata["logid"] = isset($_SESSION["mostrecentlogid"]) ? $_SESSION["mostrecentlogid"] : $usermeta["defaultlog"];
                    //$formdata["logid"]=isset($usermeta["defaultlog"]) ? $usermeta["defaultlog"] : null;
                  }
                  $html.=headerButtons(Array("UserOptions","LogOptions","Search"));
                  $html.=FormAddEntry($formdata);
                  if ($formdata["logid"]) {$html.="<h4>Recent Entries</h4>";}
                  $html.=FormShowLog (Array("logid"=>$formdata["logid"],"perpage"=>"10"),Array("hideform"=>1,"hidebuttons"=>1));
            }
        } else {
                if ($request->isGet()) {
                    $pathprefix=preg_replace("/\//","\\\/",URLPATHPREFIX."/html");
                    $path=preg_replace('/'.$pathprefix.'/','',$request->getUri()->getPath());
                    //$path=addslashes(addslashes(URLPATHPREFIX."/html"));
                    $path2=$request->getUri()->getPath();
                    switch ($path) {
                        case '/pwreset':
                          $html.=formResetPW($request->getQueryParams());
                          break;
                        case '/signup':
                          $formdata=$request->getQueryParams();
                          unset($formdata["email"]);
                          $html.=webSignup($formdata);
                          break;
                        default:
                          if ($_SESSION["system_userid"]) {
                            $html.=headerButtons(Array("UserOptions","LogOptions","Search"));
                            $formdata["logid"]=isset($usermeta["defaultlog"]) ? $usermeta["defaultlog"] : null;
                            $html.=FormAddEntry($formdata);
                          } else {
                            $html.=webAuthForm();
                          }
                    }
                } else {
            $html.=headerButtons(Array("UserOptions","LogOptions","Search"));
            $formdata["logid"]=isset($usermeta["defaultlog"]) ? $usermeta["defaultlog"] : null;
            $html.=FormAddEntry($formdata);
            $html.=FormShowLog (Array("logid"=>$formdata["logid"],"perpage"=>"10"),Array("hideform"=>1,"hidebuttons"=>1));
            }
        }
        $html.=webHTMLFooter();
        $response=$response->withStatus(200)->write($html);
        return $response;
    }
    
    function routes () {
        global $app;
        
        $app->group(URLPATHPREFIX, function () use ($app) {

            $app->get('/' , function ($request,$response,$args) use ($app) {
                header('Location: '.HTMLURL);
            });
            $app->get('/html' , function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
            })->add(function ($request, $response, $next) {
                return Auth($request, $response, $next);
            });
            $app->post('/html' , function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
            })->add(function ($request, $response, $next) {
                return Auth($request, $response, $next);
            });
            $app->group('/html', function () use ($app) {
              $app->get('/pwreset',function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
              });
              $app->post('/pwreset',function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
              });
              $app->get('/signup',function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
              });
              $app->post('/signup',function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
              });

              $app->get('/query' , function ($request,$response,$args) use ($app) {
                return renderHTML($request,$response,$args);
              })->add(function ($request, $response, $next) {
                return Auth($request, $response, $next);
              });
            });
            /*
            $app->post('/log/{logid}' , function ($request,$response,$args) use ($app) {
                return apiPostLogEntry($request,$response,$args);
            })->add(function ($request, $response, $next) {
                return authUserCanPost($request, $response, $next);
            });
            $app->get('/log/{logid}' , function ($request,$response,$args) use ($app) {
                return apiGetLogEntries($request,$response,$args);
            })->add(function ($request, $response, $next) {
                return authUserCanQuery($request, $response, $next);
            });
            */
        });
    }

$htmlForms = Array (
    "Login" => Array ("function"=>"webAuthForm")
);

if (!$CLI) {
    routes();
    $app->run();
} else {
    require ("cli.php");
}
