<?php
/**
 * Data model and exception class for handling site users
 *
 * @package Fuzic-site
 */
namespace Fuzic\Models;

use Fuzic;
use Fuzic\Lib;


/**
 * Exceptions thrown by User class.
 */
class UserException extends \ErrorException
{
}

/**
 * User handler class
 */
class User extends Lib\Model
{
    /**
     * Database table containing data records of this class.
     *
     * @const string
     */
    const TABLE = 'users';
    const LABEL = 'handle';
    const HIDDEN = 1;


    /**
     * User access levels
     *
     * @const integer
     */
    const LEVEL_BANNED = 0;
    const LEVEL_ANONYMOUS = 1;
    const LEVEL_NORMAL = 2;
    const LEVEL_STREAMER = 3;
    const LEVEL_ADMIN = 4;


    /**
     * Error messages
     *
     * @const string
     */
    const ERR_EMPTY_USERNAME = 'User name cannot be empty.';
    const ERR_DUPLICATE_USERNAME = 'User name in use already.';
    const ERR_PASSWORD_TOO_SHORT = 'Password too short.';
    const ERR_WRONG_CREDENTIALS = 'Invalid username or password.';


    /**
     * User to use if visitor is not logged in (should be a valid user ID)
     */
    const USER_ANONYMOUS = 1;

    /**
     * User to use for sending site e-mails, etc (should be a valid user ID)
     */
    const USER_SITE = 2;


    /**
     * Constructor
     *
     * @param   array|integer $objectID The ID of the object to be instantiated. Can
     *                                  either be the ID itself, or an array of attributes that should match; this is
     *                                  an
     *                                  associative array with key-value pairings corresponding to database values. The
     *                                  first result (ordered by the ID field of this type of object, descending) is
     *                                  then used as data for the object.
     *
     * @param   boolean       $no_check If this parameter is set to `true` and
     *                                  `$objectID` is an array, the array is not used to match a record from the
     *                                  database but used as object data immediately. This can be used if data has
     *                                  already been retrieved from the database somewhere else in the script.
     *
     * @access  public
     */
    public function __construct($objectID, $no_check = false) {
        parent::__construct($objectID, $no_check);
    }


    /**
     * Get password hash
     *
     * Uses PHP's `password_hash()`.
     *
     * @param   string $input Password to hash.
     *
     * @return  string                      Password hash.
     *
     * @access  public
     */
    public static function get_hash($input) {
        return password_hash($input, PASSWORD_DEFAULT);
    }


    /**
     * Verify a login attempt
     *
     * @param   string|bool $username User name
     * @param   string|bool $password Password
     * @param   string|bool $nonce    Form nonce
     *
     * @return  object                          User object.
     *
     * @throws  UserException                   If login credentials are not valid.
     *
     * @access  public
     */
    public static function verify_login($username = false, $password = false, $nonce = false) {
        global $session;

        if ($username === false && isset($_POST['username'])) {
            $username = $_POST['username'];
        }

        if ($password === false && isset($_POST['password'])) {
            $password = $_POST['password'];
        }

        if ($nonce === false && isset($_POST['_nonce'])) {
            $nonce = $_POST['_nonce'];
        }

        $user = User::find([
            'return' => 'single_object',
            'where' => ['handle = ?' => [$username]]
        ]);

        if ($user && $session->check_nonce($nonce, 'login') && password_verify($password, $user->get('password'))) {
            return $user;
        }

        sleep(1);
        throw new UserException(self::ERR_WRONG_CREDENTIALS);
    }


    /**
     * Get login details from cookie
     *
     * @return  array|boolean                   `false` if cookie is not set, array with
     * items `username` and `password` if it is.
     */
    public static function get_login_from_cookie() {
        if (!empty($_COOKIE[Fuzic\Config::LOGIN_COOKIE])) {
            $data = json_decode($_COOKIE[Fuzic\Config::LOGIN_COOKIE], true);
            if (isset($data['u']) && isset($data['s'])) {
                return [
                    'username' => $data['u'],
                    'sessionid' => $data['s']
                ];
            }
        }

        return false;
    }


    /**
     * Log out, remove cookies
     *
     * @access  public
     */
    public static function logout() {
        global $session, $user;

        setcookie(Fuzic\Config::LOGIN_COOKIE, '', 0);
        $_COOKIE[Fuzic\Config::LOGIN_COOKIE] = '';

        $user = new User(static::USER_ANONYMOUS);

        $session->set('user', static::USER_ANONYMOUS);
        $session->update();

        //redirect to page without logout parameter (else a new login attempt will immediately log out)
        $path = explode('?', $_SERVER['REQUEST_URI']);
        header('Location: '.$path[0]);
        exit;
    }


    /**
     * Validate object attributes
     *
     * Currently only checks `handle`.
     *
     * @param   string $attribute Attribute to validate.
     * @param   mixed  $value     Value of the attribute.
     *
     * @return  mixed                       Validated value.
     *
     * @throws  UserException               If the value is not acceptable.
     *
     * @access  public
     */
    public function validate($attribute, $value) {
        if ($attribute === 'handle') {
            if (empty($value)) {
                throw new UserException(self::ERR_EMPTY_USERNAME);
            }

            $duplicates = User::find([
                'where' => ['LOWER(handle) = ?' => strtolower($value)],
                'return' => 'count'
            ]);

            if ($duplicates > 0) {
                throw new UserException(self::ERR_DUPLICATE_USERNAME);
            }
        }

        return $value;
    }


    /**
     * Validate password
     *
     * Currently only checks password length.
     *
     * @param   string $password Password to validate.
     *
     * @return  string      $password       Validated password.
     *
     * @throws  UserException               If the password is not acceptable.
     *
     * @access  public
     */
    public function validate_password($password) {
        if (strlen($password) < 3) {
            throw new UserException(self::ERR_PASSWORD_TOO_SHORT);
        }

        return $password;
    }


    /**
     * Checks user level
     *
     * @param   integer $level Level to check against.
     *
     * @return  boolean                     Whether the user is of the given level or
     * higher.
     *
     * @access  public
     */
    public function is_level($level) {
        return ($this->get('level') >= $level);
    }


    /**
     * Set 'last seen' timestamp to current timestamp
     *
     * @access  public
     */
    public function touch() {
        $this->set('last_seen', time());
        $this->update();
    }


    /**
     * Set user password
     *
     * Validates and hashes the password before setting it.
     *
     * @uses    User::validate_password()   To validate.
     * @uses    User::get_hash()            To get password hash.
     *
     * @param   string $password Password to set.
     *
     * @access  public
     */
    public function set_password($password) {
        $this->validate_password($password);

        $this->set('password', self::get_hash($password));
        $this->update();
    }


    /**
     * Checks whether user is anonymous
     *
     * @return  boolean
     *
     * @access  public
     */
    public function is_anon() {
        return $this->get('level') <= self::LEVEL_ANONYMOUS;
    }


    /**
     * Checks whether user is banned
     *
     * @return  boolean
     *
     * @access  public
     */
    public function is_banned() {
        return $this->get('level') <= self::LEVEL_BANNED;
    }


    /**
     * Getter
     *
     * @param   string $attribute
     *
     * @return  mixed                       Attribute value.
     *
     * @access  public
     */
    public function __get($attribute) {
        return $this->get($attribute);
    }
}