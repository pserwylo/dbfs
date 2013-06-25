<?php

abstract class LazyDbDavDirectory extends \Sabre\DAV\Collection {

    private $db = null;
    private $databaseName;

    public function __construct( $databaseName ) {
        $this->databaseName = $databaseName;
    }

    protected final function databaseName() {
        return $this->databaseName;
    }

    public function db() {
        if ( $this->db == null ) {
            $this->db = new PDO(
                Config::get()->dbDsn() . ";dbname=" . $this->databaseName,
                Config::get()->dbUsername(),
                Config::get()->dbPassword()
            );
        }
        return $this->db;
    }

    public function createFile( $string, $data = null ) {
        throw new \Sabre\DAV\Exception\Forbidden( "Cannot create new files" );
    }

}

abstract class DbDavDirectory extends Sabre\DAV\Collection {

    /** @var PDO */
    private $db;

    public function __construct( $db ) {
        $this->db = $db;
    }

    protected final function db() {
        return $this->db;
    }

    public function createFile( $string, $data = null ) {
        throw new \Sabre\DAV\Exception\Forbidden( "Cannot create new files" );
    }

}

abstract class DbDavFile extends Sabre\DAV\File {

    /** @var PDO */
    private $db;

    public function __construct( $db ) {
        $this->db = $db;
    }

    protected final function db() {
        return $this->db;
    }

}

class CustomFile extends DbDavFile {

    private $folderConfig;
    private $fieldValues;

    public function __construct( array $fieldValues, FolderConfig $config, PDO $db ) {
        parent::__construct( $db );
        $this->folderConfig = $config;
        $this->fieldValues = $fieldValues;
    }

    protected function tableName() {
        return $this->folderConfig->table();
    }

    public function getName() {
        $name = $this->folderConfig->filename();
        foreach( $this->fieldValues as $field => $value ) {
            $name = str_replace( '$' . $field, $value, $name );
        }
        return $name;
    }

    public function getLastModified() {
        if ( $this->folderConfig->lastModifiedField() ) {
            return strtotime( $this->fieldValues[ $this->folderConfig->lastModifiedField() ] );
        } else {
            return 0;
        }
    }

    public function getSize() {
        return $this->fieldValues[ "contentLength" ];
    }

    public function get() {
        $selectField = $this->folderConfig->column();
        $whereFields = array();
        foreach( $this->folderConfig->fields() as $field ) {
            $whereFields[] = "$field = :$field";
        }

        $sql = "
            SELECT $selectField
            FROM " . $this->tableName() . "
            WHERE
                " . join( " AND ", $whereFields ) . ";";
        $result = $this->db()->prepare( $sql );

        $params = array();
        foreach( $this->folderConfig->fields() as $field ) {
            $value = $this->fieldValues[ $field ];
            $params[ $field ] = $value;
        }

        $result->execute( $params );
        $row = $result->fetch( PDO::FETCH_ASSOC );
        return $row[ $selectField ];
    }

    public function put( $contents ) {
        $putParam = "putValue";
        $whereFields = array();
        foreach( $this->folderConfig->fields() as $field ) {
            $whereFields[] = "$field = :$field";
        }

        $sql = "
            UPDATE " . $this->tableName() . "
            SET " . $this->folderConfig->column() . " = :$putParam
            WHERE
                " . join( " AND ", $whereFields ) . ";";
        $result = $this->db()->prepare( $sql );

        $params = array( $putParam => stream_get_contents( $contents ) );
        foreach( $this->folderConfig->fields() as $field ) {
            $value = $this->fieldValues[ $field ];
            $params[ $field ] = $value;
        }

        $result->execute( $params );
    }

}

class WildcardCustomFile extends CustomFile {

    private $tableName;

    public function __construct( array $row, $tableName, FolderConfig $config, PDO $db ) {
        parent::__construct( $row, $config, $db );
        $this->tableName = $tableName;
    }

    protected function tableName() {
        return $this->tableName;
    }

}

class CustomFolderDirectory extends DbDavDirectory {

    /**
     * @param FolderConfig $config
     * @param PDO $db
     * @return CustomFolderDirectory[]
     */
    public static function create( FolderConfig $config, PDO $db ) {
        $folders = array();
        if ( strstr( $config->table(), "*" ) === false ) {
            $folders[] = new CustomFolderDirectory( $config, $db );
        } else {
            $result = $db->prepare( "SHOW TABLES LIKE :table" );
            $result->execute( array( 'table' => str_replace( "*", "%", $config->table() ) ) );
            $rows = $result->fetchAll( PDO::FETCH_ASSOC );
            foreach( $rows as $row ) {
                $table = array_pop( $row );
                $folders[] = new WildcardCustomFolderDirectory( $table, $config, $db );
            }
        }
        return $folders;
    }

    private $folderConfig;
    private $children = null;

    public function __construct( FolderConfig $config, PDO $db ) {
        parent::__construct( $db );
        $this->folderConfig = $config;
    }

    protected function tableName() {
        return $this->folderConfig->table();
    }

    protected final function folderConfig() {
        return $this->folderConfig;
    }

    private function children() {
        if ( $this->children == null ) {
            $this->children = array();

            $fieldsToSelect = array_merge(
                $this->folderConfig->fields(),
                array( "LENGTH( " . $this->folderConfig->column() . " ) as contentLength" )
            );

            if ( $this->folderConfig->lastModifiedField() ) {
                $fieldsToSelect[] = $this->folderConfig->lastModifiedField();
            }

            $fields = join( ", ", $fieldsToSelect );
            $table  = $this->tableName();
            $sql    = "SELECT $fields FROM $table;";

            $result = $this->db()->prepare( $sql );
            $result->execute();
            $rows = $result->fetchAll( PDO::FETCH_ASSOC );
            foreach( $rows as $row ) {
                $this->children[] = $this->createChild( $row );
            }
        }
        return $this->children;
    }

    protected function createChild( array $row ) {
        return new CustomFile( $row, $this->folderConfig, $this->db() );
    }

    public final function getChildren() {
        return $this->children();
    }

    public function getName() {
        return $this->folderConfig->name();
    }
}

class WildcardCustomFolderDirectory extends CustomFolderDirectory {

    /** @var string */
    private $tableName;

    public function __construct( $tableName, FolderConfig $config, PDO $db ) {
        parent::__construct( $config, $db );
        $this->tableName = $tableName;
    }

    protected function tableName() {
        return $this->tableName;
    }

    protected function createChild( array $row ) {
        return new WildcardCustomFile( $row, $this->tableName(), $this->folderConfig(), $this->db() );
    }

    public function getName() {
        $name     = parent::getName();
        $realName = Config::get()->foldersWildcardName();
        $realName = str_replace( '$folder', $name, $realName );
        $realName = str_replace( '$table', $this->tableName(), $realName );
        return $realName;
    }

}

class AllDatabasesDirectory extends DbDavDirectory {

    /** @var DatabaseDirectory[] */
    private $databases = null;

    public function __construct( PDO $db ) {
        parent::__construct( $db );
    }

    private function databases() {
        if ( $this->databases == null ) {
            $this->databases = array();

            $result = $this->db()->query( "SHOW DATABASES" );
            foreach( $result->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
                $databaseName = array_pop( $row );
                $this->databases[] = new DatabaseDirectory( $databaseName );
            }
        }
        return $this->databases;
    }

    public function getChildren() {
        return $this->databases();
    }

    public function getName() {
        return "All Databases";
    }

}

/** Lists all of the tables inside the database. */
class DatabaseDirectory extends LazyDbDavDirectory {

    /** @var CustomFolderDirectory[] */
    private $folders = null;

    public function __construct( $databaseName ) {
        parent::__construct( $databaseName );
    }

    private function folders() {
        if ( $this->folders == null ) {
            $this->folders = array();
            foreach( Config::get()->folders() as $folder ) {
                if (  !$folder->database()
                    || $folder->database() == $this->databaseName()
                    || $folder->database() == "*" ) {

                    $foldersToAdd = CustomFolderDirectory::create( $folder, $this->db() );
                    foreach( $foldersToAdd as $folderToAdd ) {
                        $this->folders[] = $folderToAdd;
                    }
                }
            }
        }
        return $this->folders;
    }

    public function getChildren() {
        return $this->folders();
    }

    public function getName() {
        return $this->databaseName();
    }

}

class Server {

    private function db() {
        $pdo = new PDO(
            Config::get()->dbDsn(),
            Config::get()->dbUsername(),
            Config::get()->dbPassword()
        );
        return $pdo;
    }

    public function root() {
        return new AllDatabasesDirectory( $this->db() );
    }

    public function auth() {
        $auth = new \Sabre\HTTP\BasicAuth();
        $result = $auth->getUserPass();
        if ( !$result || $result[0]!= Config::get()->loginUsername() || $result[1] != Config::get()->loginPassword() ) {
            $auth->requireLogin();
            echo "Authentication required\n";
            die();
        }
    }

    public function run() {

        $this->auth();

        $server = new \Sabre\DAV\Server( $this->root() );
        $server->exec();
    }

}