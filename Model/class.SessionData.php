<?php
/**
 * Session model
 *
 * @package Fuzic-site
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Session Data model
 */
class SessionData extends Lib\Model
{
    const TABLE = 'sessions_data';
    const IDFIELD = 'sessionid';
    const LABEL = 'title';
    const HIDDEN = 1;
}