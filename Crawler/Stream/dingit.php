<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class DingitChecker
 *
 * Checks the DingIt API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class DingitChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'di-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Dingit';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'dingit';

    /**
     * Gather data about currently broadcasting streams from the DingIt API
     *
     * This service's API has all the info now! woo
     */
    public function run() {
        try {
            $source = get_url('http://www.dingit.tv/api/get_only_live');
        } catch(\ErrorException $e) {
            return;
        }

        $json = json_decode($source, true);
        if(!$json || !isset($json['list'])) {
            $this->log('DingIt JSON could not be parsed', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        foreach($json['list'] as $stream) {
            $game = Crawler\StreamChecker::map_game_name($stream['game']);
            if(!$game) {
                continue;
            }

            $this->add_stream($stream['login'], array(
                'viewers' => $stream['viewers_count'],
                'name' => $stream['login'],
                'title' => $stream['name'],
                'avatar' => $stream['user_avatar_img'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $game
            ));
        }
    }
}