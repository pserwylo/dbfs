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

class Config {

    private $loginUsername;
    private $loginPassword;

    private $dbDsn;
    private $dbUsername;
    private $dbPassword;

    private $foldersWildcardName;

    public function __construct( $configFile ) {
        $config = parse_ini_file( $configFile, true );
        if ( $config === false ) {
            throw new Exception( "Config file '$configFile' not found. Cannot start dbfs." );
        }

        $this->loginUsername = $config['login']['username'];
        $this->loginPassword = $config['login']['password'];

        $this->dbDsn      = trim( $config['database']['dsn'], "; \t" );
        $this->dbUsername = $config['database']['username'];
        $this->dbPassword = $config['database']['password'];

        $this->foldersWildcardName = $config['folders']['wildcardFolderName'];

        // All remaining entries are tables which have been defined
        foreach( $config as $name => $values ) {
            $folder = FolderConfig::createFromConfig( $name, $values );
            if ( $folder ) {
                $this->folders[] = $folder;
            }
        }
    }

    public function foldersWildcardName() {
        return $this->foldersWildcardName;
    }

    public function dbDsn() {
        return $this->dbDsn;
    }

    public function dbUsername() {
        return $this->dbUsername;
    }

    public function dbPassword() {
        return $this->dbPassword;
    }

    public function loginUsername() {
        return $this->loginUsername;
    }

    public function loginPassword() {
        return $this->loginPassword;
    }

    /**
     * @return FolderConfig[]
     */
    public function folders() {
        return $this->folders;
    }

    private static $config;

    public static function set( Config $config ) {
        self::$config = $config;
    }

    /**
     * @return Config
     */
    public static function get() {
        assert( self::$config != null );
        return self::$config;
    }
}

class FolderConfig {

    const CONFIG_PREFIX = "folder:";

    private $name;
    private $database;
    private $table;
    private $column;
    private $fields;
    private $filename;
    private $mimeType;
    private $lastModifiedField = null;

    public static function createFromConfig( $name, $values ) {
        if ( substr( $name, 0, strlen( self::CONFIG_PREFIX ) ) != self::CONFIG_PREFIX ) {
            return null;
        }

        $folder = new self( substr( $name, strlen( self::CONFIG_PREFIX ) ) );

        $folder->database = $values['database'];
        $folder->table    = $values['table'];
        $folder->column   = $values['column'];
        $folder->filename = $values['filename'];
        $folder->fields   = self::extractFields( $folder->filename );
        $folder->mimeType = $values['mimeType'];
        $folder->lastModifiedField = isset( $values['lastModifiedField'] ) ? $values['lastModifiedField'] : null;

        return $folder;
    }

    private static function extractFields( $string ) {
        $regex   = '^\$([a-zA-Z\d_]*)^';
        $matches = array();
        preg_match_all( $regex, $string, $matches );
        $fields = $matches[1];
        return $fields;
    }

    private function __construct( $name ) {
        $this->name = $name;
    }

    public function name() {
        return $this->name;
    }

    public function database() {
        return $this->database;
    }

    public function table() {
        return $this->table;
    }

    public function column() {
        return $this->column;
    }

    public function filename() {
        return $this->filename;
    }

    public function fields() {
        return $this->fields;
    }

    public function mimeType() {
        return $this->mimeType;
    }

    public function lastModifiedField() {
        return $this->lastModifiedField;
    }

}