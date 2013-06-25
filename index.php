<?php
/*
 * Copyright 2013 Peter Serwylo
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once( "lib/autoload.php" );
require_once( "Config.php"  );
require_once( "db-dav.php"  );

$config = new Config( "/home/pete/code/dbfs/dbfs.config" );
Config::set( $config );

date_default_timezone_set( 'Australia/Melbourne' );

$server = new Server();
$server->run();