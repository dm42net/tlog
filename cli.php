<?php

function cliAddUser() {

        `/bin/stty echo`;
	$email=readline("Email: ");
	$password="1";$password2="2";
	while ($password != $password2) {
        `/bin/stty -echo`;
	$password=readline("Password: ");
	$password2=readline("Password(again): ");
	  if ($password != $password2) {echo "\nPasswords do not match, try again.\n\n";}
	}
	
	if (addUser($email,$password)) {
		echo "User added\n";
	} else {
		echo "Something went wrong adding the user.\n";
	}
}

function cliSetup() {
        dbUpdateTables(0);
        newPWKeys();
}

function cliResetAll() {
        dbDropTables();
        dbUpdateTables(0);
        newPWKeys();
}


function cli_listcommands ($commands_array) {
    if (!is_array($commands_array)) return;
    foreach ($commands_array as $command) {
        echo " ".$command["command"]." - ".$command["description"]."\n";
    }
}

function cli_processcommand ($commands_array) {
    $command=null;
    while ($command!="quit") {
      echo "\n\n<----------------------------------------->\n\n";
      cli_listcommands($commands_array);
      echo "\n\n---\n\n";
      $command=readline("Enter command (type 'quit' to exit): ");
      $foundcommand=0;
          if (!is_array($commands_array)) return;
          foreach ($commands_array as $cmd) {
            if ($command==$cmd["command"]) {
              $foundcommand=1;
              $cmd["function"]();
            }
        }
        if (!$foundcommand && $command!="quit") {
          echo "\nINVALID COMMAND ($command)\n";
        }
    }
}

$cli_commands = Array (
    Array ("command" => "adduser",
           "description" => "Add user to system",
           "function" => "cliAddUser"
          ),
    Array ("command" => "setup",
           "description" => "Set up Database and password secrets.",
           "function" => "cliSetup"),
    Array ("command" => "reset",
           "description" => "Delete all existing logs, Keep users and passwords.",
           "function" => "cliResetAll")
/*
            , Array ( "command" => "cli-command",
                    "description" => "Description",
                    "function" => "class-functionname")
*/
        );

cli_processcommand($cli_commands);
