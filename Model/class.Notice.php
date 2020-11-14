<?php
namespace Fuzic\Models;

use Fuzic\Lib;


class Notice extends Lib\Model
{
    const TABLE = 'notices';
    const HUMAN_NAME = 'Notices';
    const HIDDEN = 1;

    /**
     * Save notice
     *
     * @param User|int $user        User the notice is for. Either the user
     *                              ID or a `User` object.
     * @param string   $text        Notice text
     * @param int      $lifespan    How long before the notice should be
     *                              removed automatically. `0` for indefinite lifespan.
     * @param bool     $dismissable Whether the notice can be dismissed by
     *                              the user
     */
    public static function add($user, $text, $lifespan = 0, $dismissable = true) {
        $dismissable = $dismissable ? 1 : 0;
        $lifespan = abs(intval($lifespan));

        if (is_object($user) && $user instanceof User) {
            $user = $user->get_ID();
        }

        $expires = ($lifespan == 0) ? 0 : (time() + $lifespan);

        static::create([
            'text' => $text,
            'expires' => $expires,
            'dismissable' => $dismissable,
            'user' => $user
        ]);
    }
}