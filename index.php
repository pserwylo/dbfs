<?php

require_once( "lib/autoload.php" );
require_once( "Config.php"  );
require_once( "db-dav.php"  );

$config = new Config( "/home/pete/code/dbfs/dbfs.config" );
Config::set( $config );

date_default_timezone_set( 'Australia/Melbourne' );

$server = new Server();
$server->run();