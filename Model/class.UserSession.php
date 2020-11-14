<?php
/**
 * Session data model and handler
 *
 * @package Fuzic-site
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Session data model and handler
 */
class UserSession extends Lib\Model
{
    /**
     * Database table containing data records of this class.
     *
     * @const string
     */
    const TABLE = 'usersessions';
    const HIDDEN = 1;

    /**
     * Constructor
     *
     * @param array|int $objectID
     * @param bool      $no_check
     *
     * @throws Lib\ItemNotFoundException
     * @throws \ErrorException
     */
    public function __construct($objectID, $no_check = false) {
        parent::__construct($objectID, $no_check);

        //update client info
        $this->set('browser', $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Make the session a logged-in session
     *
     * @access  public
     */
    public function upgrade() {
        global $user;

        $user = User::verify_login();
        $this->update_ID($user);
        return $user;
    }


    /**
     * Link session to user
     *
     * Sets session cookie and cycles session ID
     *
     * @param   object $user User to link to.
     *
     * @access public
     */
    public function update_ID($user) {
        $this->set('user', $user->get_ID());
        $this->set_ID(static::generate_ID());
        $this->update();

        $this->set_cookie();
    }


    /**
     * Set or update session cookie
     *
     * Stores user and session IDs.
     *
     * @return  boolean                     Whether the cookie was succesfully stored.
     *
     * @access  private
     */
    private function set_cookie() {
        $cookie = json_encode(['u' => $this->get('user'), 's' => $this->get_ID()]);

        $success = setcookie(
            Fuzic\Config::LOGIN_COOKIE,
            $cookie,
            time() + (365 * 24 * 60 * 60),
            '/'
        );

        if ($success) {
            $_COOKIE[Fuzic\Config::LOGIN_COOKIE] = $cookie;
        }

        return $success;
    }


    /**
     * Get nonce tick
     *
     * Get time-dependent variable that can be used to check expiration of (for example)
     * nonce values.
     *
     * @return  integer                     Nonce tick number.
     *
     * @access  private
     */
    private function get_nonce_tick() {
        return ceil(time() / (86400 / 2));
    }


    /**
     * Get nonce
     *
     * @param   string $action Action for which to generate a nonce. May be
     *                         left blank.
     *
     * @return  string                      Unique identifier that can be used to prevent
     * CSRF.
     *
     * @access  public
     */
    public function get_nonce($action = '') {
        return sha1($this->get_nonce_tick().$action.$this->get_ID());
    }

    /**
     * Check whether a nonce is valid
     *
     * @param   string $nonce  Nonce to check.
     * @param   string $action Action for which to generate a nonce. May be
     *                         left blank.
     *
     * @return  boolean
     *
     * @access  public
     */
    public function check_nonce($nonce, $action = '') {
        $tick = $this->get_nonce_tick();

        $challenge = sha1($tick.$action.$this->get_ID());
        if ($challenge !== $nonce) {
            $challenge = sha1(($tick - 1).$action.$this->get_ID());
        }

        return ($challenge === $nonce);
    }

    /**
     * Get random session ID
     *
     * @return  string                      Session ID
     *
     * @access  public
     */
    public static function generate_ID() {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    /**
     * Get session object
     *
     * Checks whether there is an active session and if not creates one.
     *
     * @return  object                      Session object.
     *
     * @throws  UserException              If a session could not be found or created.
     *
     * @access  public
     */
    public static function acquire() {
        $login_cookie = User::get_login_from_cookie();
        $session = false;

        //session cookie?
        if ($login_cookie) {
            $session = static::find([
                'return' => 'single_object',
                'where' => [
                    static::IDFIELD.' = ? AND ip = ?' => [$login_cookie['sessionid'], $_SERVER['REMOTE_ADDR']],
                ]
            ]);
        }

        //anonymous session?
        if (!$session) {
            $session = static::find([
                'return' => 'single_object',
                'where' => [
                    'user = ? AND ip = ? AND browser = ?' => [User::USER_ANONYMOUS, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]
                ]
            ]);
        }

        //create new session?
        $i = 0;
        while (!$session && $i < 5) {
            $session = static::create([
                static::IDFIELD => static::generate_ID(),
                'user' => 1,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'browser' => $_SERVER['HTTP_USER_AGENT'],
            ]);
            $session->update(true);

            //there is a very very very very very small chance that the generated ID 
            //exists already. In the even more unlikely event that multiple collisions
            //in a row are generated, keep track of how many tries there have been so far
            //so the script doesn't keep trying
            $i += 1;
        }

        //if all those failed, give up
        if (!$session) {
            throw new UserException('Could not create user session.');
        }

        return $session;
    }
}