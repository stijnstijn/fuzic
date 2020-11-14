<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class HitboxChecker
 *
 * Checks the Hitbox.tv API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class HitboxChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'h-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Hitbox';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'hitbox';

    /**
     * Gather data about currently broadcasting streams from the Hitbox.tv API
     *
     * This service's API contains info about which game a stream is broadcasting. Therefore, the game is determined by
     * the API response and all streams using the server are reported rather than only those listed on external indexes.
     */
    public function run() {
        try {
            $json = get_url('https://www.hitbox.tv/api/media/live/list?fast=true&filter=&game=&grouped=true&hiddenOnly=false&limit=500&liveonly=true&media=true&showHidden=false&size=mid&start=0');
        } catch(\ErrorException $e) {
            $this->log('Could not retrieve Hitbox API data ('.$e->getMessage().')', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        $data = json_decode($json, true);
        if(!$data || !isset($data['livestream'])) {
            $this->log('Could not parse Hitbox API data', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        foreach($data['livestream'] as $stream) {
            if($stream['media_is_live'] != '1') {
                continue;
            }

            $game = Crawler\StreamChecker::map_game_name($stream['category_seo_key']);
            if(!$game) {
                continue;
            }

            $id = $stream['media_name'];

            $this->add_stream($id, array(
                'viewers' => $stream['media_views'],
                'name' => $stream['media_display_name'],
                'avatar' => 'http://edge.sf.hitbox.tv/'.$stream['user_logo'],
                'title' => $stream['media_status'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $game
            ));
        }
    }
}