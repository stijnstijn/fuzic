<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class AzubuChecker
 *
 * Checks the Azubu API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class DisabledAzubuChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'a-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Azubu';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'azubu';

    /**
     * Gather data about currently broadcasting streams from the Dailymotion API
     *
     * The Azubu API contains info about which game a stream is broadcasting. Therefore, the game is determined by
     * the API response and all streams using the server are reported rather than only those listed on external indexes.
     */
    public function run() {
        try {
            $azubu_data = json_decode(get_url('http://api.azubu.tv/public/channel/live/list', array(), false, false, 10), true);
        } catch(\ErrorException $e) {
            $this->log('No connection to Azubu while fetching stream list', Crawler\StreamChecker::LOG_WARNING);

            return false;
        }


        if(!$azubu_data || empty($azubu_data)) {
            $this->log('Empty Azubu response.', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        foreach($azubu_data['data'] as $stream) {
            if(!$stream['is_live']) {
                continue;
            }

            $game = Crawler\StreamChecker::map_game_name($stream['category']['name']);
            if(!$game) {
                continue;
            }

            $this->add_stream($stream['user']['username'], array(
                'viewers' => $stream['view_count'],
                'name' => $stream['user']['username'],
                'avatar' => $stream['user']['profile']['url_photo_large'],
                'title' => $stream['title'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $game
            ));
        }
    }
}