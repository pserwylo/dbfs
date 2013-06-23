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

/**
 * Exposes two children:
 * One child which is the fields in this table, and another which is the rows in this table.
 */
class TableDirectory extends DbDavDirectory {

    private $tableName;

    public function __construct( $tableName, PDO $db ) {
        parent::__construct( $db );
        $this->tableName = $tableName;
    }

    public function getChildren() {
        return array(
            new AllTableRowsDirectory( $this->tableName, $this->db() ),
            new AllTableFieldsDirectory( $this->tableName, $this->db() ),
        );
    }

    public function getName() {
        return $this->tableName;
    }
}

/**
 * Lists all the tables in the database.
 */
class AllDatabaseTablesDirectory extends DbDavDirectory {

    private $tables = null;

    public function __construct( PDO $db ) {
        parent::__construct( $db );
    }

    /**
     * @return TableDirectory[]
     */
    private function tables() {
        if ( $this->tables == null ) {
            $this->tables = array();

            $result = $this->db()->prepare( "SHOW TABLES;" );
            $result->execute();
            $rows = $result->fetchAll( PDO::FETCH_ASSOC );
            $tables = array();
            foreach( $rows as $row ) {
                $tableName = array_pop( $row );
                $tables[] = new TableDirectory( $tableName, $this->db() );
            }
        }
        return $this->tables;
    }

    public function getChildren() {
        return $this->tables();
    }

    public function getName()  {
        return "All Tables";
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
            FROM " . $this->folderConfig->table() . "
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
            UPDATE " . $this->folderConfig->table() . "
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

class CustomFolderDirectory extends DbDavDirectory {

    private $folderConfig;
    private $children = null;

    public function __construct( FolderConfig $config, PDO $db ) {
        parent::__construct( $db );
        $this->folderConfig = $config;
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
            $table  = $this->folderConfig->table();
            $sql    = "SELECT $fields FROM $table;";

            $result = $this->db()->prepare( $sql );
            $result->execute();
            $rows = $result->fetchAll( PDO::FETCH_ASSOC );
            foreach( $rows as $row ) {
                $this->children[] = new CustomFile( $row, $this->folderConfig, $this->db() );
            }
        }
        return $this->children;
    }

    public function getChildren() {
        return $this->children();
    }

    public function getName() {
        return $this->folderConfig->name();
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
                if ( !$folder->database() || $folder->database() == $this->databaseName() ) {
                    $this->folders[] = new CustomFolderDirectory( $folder, $this->db() );
                }
            }
        }
        return $this->folders;
    }

    public function getChildren() {
        return array_merge(
            array( new AllDatabaseTablesDirectory( $this->db() ) ),
            $this->folders()
        );
    }

    public function getName() {
        return $this->databaseName();
    }

}

/**
 * Lists all the rows in a table.
 */
class AllTableRowsDirectory extends DbDavDirectory {

    private $rows = null;
    private $tableName;

    public function __construct( $tableName, PDO $db ) {
        parent::__construct( $db );
        $this->tableName = $tableName;
    }

    private function primaryKey() {
        $result = $this->db()->prepare( "SHOW FIELDS FROM $this->tableName;" );
        $result->execute();
        $rows = $result->fetchAll( PDO::FETCH_ASSOC );
        $primaryKey = array();
        foreach( $rows as $field ) {
            if ( $field['Key'] == "PRI" ) {
                $primaryKey[] = $field['Field'];
            }
        }
        return $primaryKey;
    }

    private function rows() {
        if ( $this->rows == null ) {
            $primaryKey = $this->primaryKey();

            $result = $this->db()->prepare( "SELECT * FROM $this->tableName;" );
            $result->execute();
            $rows = $result->fetchAll( PDO::FETCH_ASSOC );

            $this->rows = array();
            foreach( $rows as $row ) {
                $this->rows[] = new RowDirectory( $this->tableName, $primaryKey, $row, $this->db() );
            }
        };
        return $this->rows;
    }

    public function getChildren() {
        return $this->rows();
    }

    /**
     * Returns the name of the node.
     *
     * This is used to generate the url.
     *
     * @return string
     */
    public function getName()  {
        return "All Rows";
    }
}

/**
 * A value is the value for a particular column at a given row.
 */
abstract class DirectoryWithValues extends DbDavDirectory {



}

/**
 * Lists all of the items in a row.
 */
class RowDirectory extends DirectoryWithValues {

    private $primaryKey;
    private $row;
    private $tableName;

    public function __construct( $tableName, array $primaryKey, array $row, PDO $db ) {
        parent::__construct( $db );
        $this->primaryKey = $primaryKey;
        $this->row        = $row;
        $this->tableName  = $tableName;
    }

    public function getChildren() {
        return array();
    }

    /**
     * Returns the name of the node.
     *
     * This is used to generate the url.
     *
     * @return string
     */
    public function getName() {
        $primaryValues = array();
        foreach( $this->primaryKey as $keyField ) {
            $primaryValues[] = $this->row[ $keyField ];
        }
        return join( "-", $primaryValues );
    }
}

/**
 * Lists all of the items in a row.
 */
class FieldDirectory extends DirectoryWithValues {

    private $tableName;
    private $fieldName;

    public function __construct( $tableName, $fieldName, PDO $db ) {
        parent::__construct( $db );
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
    }

    public function getChildren() {
        return array();
    }

    public function getName() {
        return $this->fieldName;
    }
}

/**
 * Lists all the fields in a table.
 */
class AllTableFieldsDirectory extends DbDavDirectory {

    private $fields = null;
    private $tableName;

    public function __construct( $tableName, PDO $db ) {
        parent::__construct( $db );
        $this->tableName = $tableName;
    }

    /**
     * @return FieldDirectory[]
     */
    private function fields() {
        if ( $this->fields == null ) {
            $result = $this->db()->prepare( "SHOW FIELDS FROM $this->tableName;" );
            $result->execute();
            $rows = $result->fetchAll( PDO::FETCH_ASSOC );

            $this->fields = array();
            foreach( $rows as $field ) {
                $this->fields[] = new FieldDirectory( $this->tableName, $field['Field'], $this->db() );
            }
        };
        return $this->fields;
    }

    public function getChildren() {
        return $this->fields();
    }

    /**
     * Returns the name of the node.
     *
     * This is used to generate the url.
     *
     * @return string
     */
    function getName()
    {
        return "All Fields";
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

    public function run() {
        $server = new \Sabre\DAV\Server( $this->root() );
        $server->exec();
    }

}
