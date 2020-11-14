<?php
/**
 * Defines a database abstraction layer class that interfaces with a MySQL database.
 *
 * @package    Fuzic
 */
namespace Fuzic\Lib;

use Fuzic;


/**
 * Contains basic database interface methods. Serves as database abstraction
 * layer so to make it theorietically possible to switch to another database
 * type without having to change all code. Also provides extensive debugging
 * functionality.
 *
 * @package    Fuzic
 * @author     Stijn Peeters <php@stijnpeeters.nl>
 * @copyright  Copyright (c) 2009, Stijn Peeters
 * @version    1.5
 */
class mysqldb
{
    /**
     * @var    string  The most recently executed query.
     * @access private
     */
    private $last_query;
    /**
     * @var    resource  The most recently executed query's result.
     * @access private
     */
    private $last_result;
    /**
     * @var    array   The most recent fetches result row.
     * @access private
     */
    private $last_fetched;
    /**
     * @var    boolean Whether debug mode (outputs verbose status and debugging
     *                 messages) is active.
     * @access private
     */
    private $debug_enabled;
    /**
     * @var    boolean Whether to display many debug messages
     * @access private
     */
    private $debug_verbose;
    /**
     * @var    boolean Whether to output debug messages in HTML.
     * @access private
     */
    private $debug_html;
    /**
     * @var    integer Amount of queries executed since object init.
     * @access public
     */
    public $query_count;
    /**
     * @var    integer Amount of rows in most recently fetched result
     * @access private
     */
    private $num_rows;
    /**
     * @var    string  Address of the database server (usually "localhost")
     * @access private
     */
    private $location;
    /**
     * @var    string  Database account username
     * @access private
     */
    private $username;
    /**
     * @var    string  Database account password
     * @access private
     */
    private $password;
    /**
     * @var    string  Database to work with
     * @access private
     */
    private $database;
    /**
     * @var    string  Latest MySQL error
     * @access private
     */
    private $mysql_error;


    /**
     * Constructor
     *
     * Sets up database parameters and sets all internal variables.
     * to their initial states.
     *
     * @param   string  $location      Database location. Usually `localhost` (default)
     * @param   string  $username      Database username. Defaults to `root`
     * @param   string  $password      Database password. Defaults to empty.
     * @param   string  $database      Database name. Defaults to `j2o`.
     * @param   boolean $debug_enabled Whether to use debug mode or not. In debug mode
     *                                 errors get printed to the buffer. Defaults to `true`.
     * @param   boolean $debug_html    Whether to output debug messages in HTML or not.
     *                                 Defaults to `true`.
     *
     * @access  public
     */
    public function __construct($location = '', $username = '', $password = '', $database = '', $debug_enabled = false, $debug_html = true) {
        $this->location = empty($location) ? Fuzic\Config::DB_PLACE : $location;
        $this->username = empty($username) ? Fuzic\Config::DB_USER : $username;
        $this->password = empty($password) ? Fuzic\Config::DB_PASSWD : $password;
        $this->database = empty($database) ? Fuzic\Config::DB_NAME : $database;

        $this->debug_enabled = !!$debug_enabled;
        $this->debug_html = !!$debug_html;
        $this->debug_verbose = false;
        $this->query_count = 0;
        $this->num_rows = 0;
        $this->SQL_CONNECTION = false;
        $this->debug('Database object instantiated, reading from '.$database.'@'.$location, 'green');
    }


    /**
     * Connect to the database
     *
     * The database connection is only opened when actually needed, so no connections
     * are made that aren't used. This method takes the parameters previously set in
     * the constructor and opens the connection. If it fails, it will halt script
     * execution and display an error message to the user.
     *
     * @access  private
     */
    private function connect() {
        try {
            $this->SQL_CONNECTION = new \PDO('mysql:host='.$this->location.';dbname='.$this->database.';charset=utf8mb4', $this->username, $this->password);
        } catch (\PDOException $e) {
            if ($this->debug_enabled) {
                $this->debug('Could not connect to database with given parameters; no connection present! Reason: '.$e->getMessage(), 'red');
            }
            if ($this->debug_html) {
                echo "<!DOCTYPE html><title>Database Error</title><div style=\"border-radius: 4px;padding:10px;width:350px;font-size:14px;color:#FFF;font-family:'Segoe UI','Trebuchet MS',sans-serif;background:#dc0082;margin:10% auto 0 auto;\"><h2 style=\"padding:0;margin:0;border-bottom:1px solid #FFF;text-align:right;color:#FFF;font-size:18px;font-family:'Segoe UI','Trebuchet MS',sans-serif;\">Database Error</h2><p style=\"margin:10px 0 5px 0;padding:0;color:#FFF;\">The database seems to be unreachable. Please check back within a few minutes!</p></div>";
            } else {
                throw new DBException('Error establishing database connection.');
            }
            exit;
        }

        if ($this->SQL_CONNECTION) {
            $this->debug('Database connection (reading from database &quot;'.$this->database.'&quot;) opened.', 'green');
            $this->query("SET CHARACTER SET utf8mb4");
            $this->query('SET NAMES utf8mb4');
        }
    }


    /**
     * Start transaction
     *
     * Only works for specific database architectures.
     *
     * @access  public
     */
    public function start_transaction() {
        if ($this->SQL_CONNECTION === false) {
            $this->debug('No connection found to send query to, opening one...', 'blue');
            $this->connect();
        }
        
        $this->SQL_CONNECTION->exec("SET autocommit=0");
        $this->SQL_CONNECTION->exec("START TRANSACTION");
    }


    /**
     * Commit transaction
     *
     * Only works for specific database architectures.
     *
     * @access  public
     */
    public function commit() {
        $this->SQL_CONNECTION->exec("COMMIT");
        $this->SQL_CONNECTION->exec("SET autocommit=1");
    }


    /**
     * Executes an SQL query.
     *
     * @param   string  $query       The SQL query to commence
     * @param   string  $return_mode Return mode, See `get_query()` for possible values.
     * @param   integer $mode        Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return  integer|array|boolean See `get_query()` for possible values.
     *
     * @access  public
     */
    public function query($query, $return_mode = 'resource', $mode = \PDO::FETCH_ASSOC) {
        if ($this->SQL_CONNECTION === false) {
            $this->debug('No connection found to send query to, opening one...', 'blue');
            $this->connect();
        }

        if (!empty($query)) {
            $this->query_count++;
            $this->last_query = $query;

            try {
                $this->last_result = $this->SQL_CONNECTION->query($query);
                $this->num_rows = $this->num_rows();
                $this->debug('Query executed succesfully '.($this->num_rows ? '('.$this->num_rows.' rows)' : '(0 rows)').', returning as '.$return_mode.'.', 'green');
            } catch (\PDOException $e) {
                $this->mysql_error = $e->getMessage();
                $this->debug('MySQL error: '.$this->mysql_error, 'red');
            }

            if ($this->SQL_CONNECTION->errorCode() != '00000') {
                $error = $this->SQL_CONNECTION->errorInfo();
                $this->mysql_error = $error[2];
                $this->debug('MySQL error: '.$this->mysql_error, 'red');
            }

            $this->num_rows = $this->num_rows();
            return $this->get_query($return_mode, $mode, $this->last_result);
        }
    }


    /**
     * Shorthand for db::query($query, 'all')
     *
     * @param   string  $query The SQL query to commence
     * @param   integer $mode  Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return array                 Array of result rows
     * @access  public
     */
    public function fetch_all($query, $mode = \PDO::FETCH_ASSOC) {
        return $this->query($query, 'complete', $mode);
    }


    /**
     * Acquire all items and index by key
     *
     * Partially a shorthand for db::query($query, 'all'). Indexes the values in the array
     * by one of the (unique) database columns.
     *
     * @param   string  $query The SQL query to commence
     * @param   integer $index Table column to use for indexing.
     * @param   integer $mode  Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return array                 Array of result rows
     *
     * @access  public
     */
    public function fetch_all_indexed($query, $index, $mode = \PDO::FETCH_ASSOC) {
        $results = $this->fetch_all($query, $mode);
        $return = array();

        if ($results && count($results) > 0 && isset($results[0][$index])) {
            foreach ($results as $row) {
                $return[$row[$index]] = $row;
            }
        } else {
            $return = $results;
        }

        return $return;
    }


    /**
     * Fetch single field
     *
     * Shorthand for `query($query, 'firstfield')`
     *
     * @param   string  $query The SQL query to commence
     * @param   integer $mode  Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return mixed                 First field of first result row
     * @access  public
     */
    public function fetch_field($query, $mode = \PDO::FETCH_ASSOC) {
        return $this->query($query, 'firstfield', $mode);
    }


    /**
     * Fetch single row
     *
     * Shorthand for `query($query, 'firstrow')`
     *
     * @param   string  $query The SQL query to commence
     * @param   integer $mode  Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return mixed                 First result row
     * @access  public
     */
    public function fetch_single($query, $mode = \PDO::FETCH_ASSOC) {
        return $this->query($query, 'firstrow', $mode);
    }


    /**
     * Fetch single field from multiple rows
     *
     * Shorthand for `query($query, 'firstfieldarray')`
     *
     * @param   string  $query The SQL query to commence
     * @param   integer $mode  Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return array                 Array of first field of result rows
     * @access  public
     */
    public function fetch_fields($query, $mode = \PDO::FETCH_ASSOC) {
        return $this->query($query, 'firstfieldarray', $mode);
    }


    /**
     * Fetch whether the query returns any results
     *
     * Shorthand for `query($query, 'boolean')`
     *
     * @param   string  $query The SQL query to commence
     * @param   integer $mode  Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return boolean               Whether the query returned a result or not
     * @access  public
     */
    public function fetch_bool($query, $mode = \PDO::FETCH_ASSOC) {
        return $this->query($query, 'boolean', $mode);
    }


    /**
     * Inserts a new row into the database
     *
     * @param   string $table Table to insert data into
     * @param   array  $data  Data, as an associative array. Keys must
     *                        match column names.
     *
     * @return  resource INSERT query
     *
     * @access  public
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        foreach ($values as $i => $value) {
            $values[$i] = $this->escape($value);
        }
        $query = "INSERT INTO ".$table." (`".implode('`, `', $fields).'`) VALUES ('.implode(', ', $values).')';

        return $this->query($query);
    }


    /**
     * Inserts a new row into the database and returns its ID
     *
     * @param   string $table Table to insert data into
     * @param   array  $data  Data, as an associative array. Keys must
     *                        match column names.
     *
     * @return  integer     The ID of the row latest inserted, or `false` if the
     * insertion failed
     *
     * @access  public
     */
    public function insert_fetch_id($table, $data) {
        $insert = $this->insert($table, $data);

        return $insert ? $this->SQL_CONNECTION->lastInsertId() : false;
    }


    /**
     * Update a database table
     *
     * @param   string $table The table to update
     * @param   array  $data  Data, as an associative array. Keys must
     *                        match column names.
     * @param   string $where Optional; a `WHERE` clause
     *
     * @return  boolean     `true` on success, `false` on failure.
     *
     * @access  public
     */
    public function update($table, $data, $where = '') {
        $query = 'UPDATE '.$table.' SET ';
        foreach ($data as $field => $value) {
            $query .= "`".$field."` = ".$this->escape($value).", ";
        }
        $query = substr($query, 0, -2);
        $query .= empty($where) ? '' : ' WHERE '.$where;

        $this->query($query);
    }


    /**
     * Delete a row from the database
     *
     * @param   string $table The table to delete from.
     * @param   string $where Optional; a `WHERE` clause
     *
     * @return  boolean     `true` on success, `false` on failure.
     *
     * @access  public
     */
    public function delete($table, $where = '') {
        $query = "DELETE FROM ".$table;
        $query .= empty($where) ? '' : ' WHERE '.$where;

        return $this->query($query);
    }


    /**
     * Empty a table and reset the AUTO_INCREMENT pointer
     *
     * @param   string $table The table to delete from.
     *
     * @return  boolean     `true` on success, `false` on failure.
     *
     * @access  public
     */
    public function reset($table) {
        return $this->delete($table) && $this->query('ALTER TABLE '.$table.' AUTO_INCREMENT = 0');
    }


    /**
     * Query processing
     *
     * Processes the query to get a datatype that is workable for scripting. The kind
     * of data returned depends on the first parameter, which can have the following
     * values:
     * - Default: Return the "raw" query result reference.
     * - `firstrow`: Return an array containing the first row (or `false` if the query
     *   failed)
     * - `firstfield`: Return the first value of the first row as string (or `false`
     *   if the query failed)
     * - `complete`: Return a two-dimensional array containing all rows (or an empty
     *   array if the query failed)
     * - `boolean`: Returns boolean `true` if a result was found, `false` if not.
     * - `num_rows`: Return integer amount of rows in the query result
     * - `insertid`: Returns ID generated for auto_increment in the query.
     *
     * @see    db::query()
     *
     * @param   string   $return_mode Return mode, see method documentation
     *                                for valid values.
     * @param int|string $mode        Array retrieval mode, which can be
     *                                PDO::FETCH_NUM, PDO::FETCH_ASSOC or PDO::FETCH_BOTH
     * @param   mixed    $use_query   The query to process. Defaults to
     *                                `false`, in which case it uses the last committed query.
     *
     * @return mixed The query result, processed according to the `$return_mode`
     * parameter
     * @access private
     */
    private function get_query($return_mode = 'resource', $mode = \PDO::FETCH_ASSOC, $use_query = false) {
        if ($use_query === false) {
            $query = $this->last_result;
        } else {
            $query = $use_query;
        }

        switch ($return_mode) {
            default: //'query'
                return $query;
            case 'firstrow': //return first row from query (but preserve query)
                $tempQuery = $query;
                return $this->fetch_row($query, $mode);
            case 'firstfield': //return first field from first row (but preserve query)
                $tempQuery = $query;
                $firstRow = $this->fetch_row($tempQuery, \PDO::FETCH_NUM);
                if (is_array($firstRow)) {
                    return $firstRow[0];
                } else {
                    return false;
                }
            case 'complete': //return array with rows as elements
                $tempQuery = $query;
                $return = array();
                while ($tempArray = $this->fetch_row($tempQuery, $mode)) {
                    array_push($return, $tempArray);
                }
                return $return;
            case 'firstfieldarray':
                $tempQuery = $query;
                $return = array();
                while ($tempArray = $this->fetch_row($tempQuery, \PDO::FETCH_NUM)) {
                    array_push($return, $tempArray[0]);
                }
                return $return;
            case 'boolean': //return true if rows > 0
                return $this->num_rows > 0;
            case 'num_rows':
                return $this->num_rows;
            case 'insertid':
                return $this->insert_id();
        }
    }


    /**
     * Row fetching
     *
     * Fetches a row. Triggers an error if there is no database connection or if
     * the used database result is empty. Defaults to last returned result if no
     * result is given as parameter; fetches as PDO::FETCH_BOTH, PDO::FETCH_ASSOC or
     * PDO::FETCH_NUM, depending on the second parameter.
     *
     * @param   string $query_result The query result to fetch a row from. If not
     *                               given, it defaults to the last committed query.
     * @param int      $mode         Array mode; PDO::FETCH_BOTH/PDO::FETCH_ASSOC/PDO::FETCH_NUM
     *
     * @return mixed See method documentation for a list of
     * possible return values.
     * @internal param string $return_mode Return mode, see method documentation
     * for a list of valid values
     * @access   public
     */
    public function fetch_row($query_result = NULL, $mode = \PDO::FETCH_ASSOC) {
        global $__log;

        if (empty($query_result)) {
            $query_result = $this->last_result;
        }

        if (!empty($query_result)) {
            $this->last_fetched = $this->last_result->fetch($mode);
            if (!$this->last_fetched) {
                $this->last_fetched = false;
            }
            return $this->last_fetched;
        } else {
            $this->debug('Fetching rows from non-existant or empty query.', 'blue');
        }
    }


    /**
     * Retrieves name of database currently connected to
     *
     * @return  string      The database name
     *
     * @access  public
     */
    public function get_database_name() {
        return $this->database;
    }

    /**
     * "Forget" latest query and result
     *
     * Needed for serialization
     */
    public function amnesia() {
        $this->last_query = null;
        $this->last_result = null;
    }


    /**
     * Escape string
     *
     * Escapes the string so it's safe against SQL injection attacks, using PDO's
     * `quote()`
     *
     * @param   string $string The string to be escaped.
     *
     * @return  string                  The escaped string.
     * @access  public
     */
    public function escape($string) {
        if ($this->SQL_CONNECTION === false) {
            $this->debug('No database connection found to get escaping character set from; opening one...', 'blue');
            $this->connect();
        }

        if (!is_string($string) && !is_numeric($string)) {
            return $this->SQL_CONNECTION->quote('');
        }

        return $this->SQL_CONNECTION->quote($string);
    }

    /**
     * Escape table name or other MySQL identifier
     *
     * @param   string $string The string to be escaped.
     *
     * @return  string                  The escaped string.
     * @access  public
     */
    public function escape_identifier($string) {
        return '`'.trim(substr(preg_replace('/([^a-zA-Z0-9_$]+)/si', '', $string), 0, 64)).'`';
    }


    /**
     * Amount of returned rows for a query
     *
     * Returns the amount of rows that were returned for a query. If no query is
     * given as parameter, it uses the last executed query.
     *
     * @param   string $query_result The query result to fetch a row from. If not
     *                               given, it defaults to the last committed query.
     *
     * @return  integer|boolean       Returns the amount of returned rows if it is
     * is 1 or more, else returns `false`.
     * @access  public
     */
    public function num_rows($query_result = NULL) {
        if (empty($query_result)) {
            $query_result = $this->last_result;
        }

        if (!empty($query_result)) {
            return $query_result->rowCount();
        } else {
            return false;
        }
    }


    /**
     * Query count
     *
     * Returns the amount of queries committed since object creation.
     *
     * @return  integer
     *
     * @access public
     */
    public function num_queries() {
        return $this->query_count;
    }


    /**
     * Enable or disable debugging
     *
     * Toggles the output of debugging messages.
     *
     * @param   boolean $verbose     Whether to use verbose mode or not. In
     *                               verbose mode, a debug message is shown for pretty much every action; else, only
     *                               for faulty queries. Defaults to false.
     *
     * @param   boolean $toggle_html Whether to toggle the displaying of debug
     *                               messages with HTML markup. Defaults to false.
     *
     * @uses   db::$debug_enabled          To toggle debugging.
     *
     * @access public
     */
    public function toggle_debug($verbose = false, $toggle_html = false) {
        if ($verbose) {
            $this->debug_verbose = true;
        }

        if ($toggle_html) {
            $this->toggle_debug_html();
        }

        $this->debug_enabled = !$this->debug_enabled;
    }

    public function enable_debug($verbose = false) {
        $this->debug_verbose = !!$verbose;
        $this->debug_html = false;
        $this->debug_enabled = true;
    }

    public function disable_debug() {
        $this->debug_enabled = false;
    }


    /**
     * Toggles whether to display debug messages as HTML
     *
     * Defaults to true
     *
     * @access  public
     */
    public function toggle_debug_html($value = NULL) {
        if($value) {
            $this->debug_html = !!$value;
        } else {
            $this->debug_html = !$this->debug_html;
        }
    }


    /**
     * Retrieves most recent SQL error
     *
     * @return  string      The error message, or `false` if there have been no errors
     *
     * @access  public
     */
    public function get_error() {
        return $this->mysql_error;
    }


    /**
     * Outputs a debug message
     *
     * Normally no error messages are shown. If $debug_enabled is set to true,
     * verbose meessages for most actions the script performs will be
     * given using this method.
     *
     * @see    template::$debug_enabled
     *
     * @param  string $debug   The debug message to show.
     * @param  string $sColour The colour of the message. Defaults
     *                         to `black`.
     *
     * @access private
     */
    public function debug($debug, $sColour = 'black') {
        if ($this->debug_enabled && (($sColour != 'green' && $sColour != 'blue') || $this->debug_verbose)) {
            $stackTrace = array_reverse(debug_backtrace());
            $stack = array();
            foreach ($stackTrace as $trace) {
                if (isset($trace['line'])) {
                    $files = explode('/', $trace['file']);
                    $stack[] = array_pop($files).':'.$trace['line'];
                }
            }
            if ($this->debug_html) {
                echo '<pre style="padding:0.5em;z-index:9999;margin:0 auto 1em auto;background:#CCC;border:1px solid #000;color:'.$sColour.';clear:both;white-space:pre-wrap;font-size:13px;">'.$debug.'<br>&#8594; '.implode(' &#8594; ', $stack).(!empty($this->last_query) ? '<div style="text-width:100%;display:block;color:#000;background:#EEE;padding:0.5em;margin:0.1em;margin-top:0.5em;font-family:monospace;">'.str_replace(array("\t", '     '), '', trim($this->last_query, "\n\r")).'</div></div>' : '').'</pre>'."\n";
            } else {
                $query = preg_replace('/([\t ]+)/', ' ', str_replace("\n", '', $this->last_query));
                echo $debug."\nQuery: ".$query."\n";
                echo "Stack trace: ".implode(' -> ', $stack)."\n\n";
            }
        }
    }


    /**
     * Get last MySQL error
     *
     * Retrieves last encountered MySQL error.
     *
     * @return  string         MySQL error string
     *
     * @uses    db::$mysql_error
     *
     * @access  public
     */
    public function error() {
        return $this->mysql_error;
    }


    /**
     * Close database connection
     *
     * Closes the database connection. If there is no open database connection, it
     * triggers an error of level E_USER_USER_NOTICE.
     *
     * @access  public
     */
    public function close() {
        $this->SQL_CONNECTION = null;
        $this->debug('Database connection to "'.$this->database.'" at '.$this->location.' closed after '.$this->query_count.' queries.', 'blue');
        $this->query_count = 0;
    }


    /**
     * Garbage collection
     *
     * Dummy function that only serves to debug the destruction of the
     * database object.
     *
     * @see    db::close()
     *
     * @access public
     */
    public function __destruct() {
        $this->close();

        if ($this->debug_enabled) {
            $hasConnection = !!$this->SQL_CONNECTION;
            $this->debug('Database object ('.($hasConnection ? 'reading from database &quot;'.$this->database.'&quot;' : 'no active connection').') terminated.', 'blue');
        }
    }
}

class DBException extends \ErrorException
{
}