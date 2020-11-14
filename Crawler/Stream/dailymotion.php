<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class DailymotionChecker
 *
 * Checks the Dailymotion API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class DailymotionChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'd-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Dailymotion';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'dailymotion';

    /**
     * Gather data about currently broadcasting streams from the Dailymotion API
     *
     * This service's API contains no info about which game a stream is broadcasting. Therefore, data gathered earlier
     * from stream indexes is used to determine which streams to fetch information for.
     */
    public function run() {
        $streams = $this->indexed;

        foreach($streams as $remote_ID => $game) {
            if(isset($data['provider']) && strtolower($data['provider']) != strtolower(static::LIQUID_PROVIDER_ID)) {
                continue;
            }

            try {
                $remote_json = get_url('https://api.dailymotion.com/video/'.$remote_ID.'?fields=audience,title');
            } catch(\ErrorException $e) {
                $this->log('No connection to Dailymotion while fetching info for '.$remote_ID, Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            $remote_data = json_decode($remote_json, true);
            if(!$remote_data || !isset($remote_data['audience'])) {
                $this->log('Invalid DailyMotion data for stream '.$remote_ID, Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            $this->add_stream($data['stream_id'], array(
                'viewers' => $remote_data['audience'],
                'name' => $remote_data['title'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $game
            ));
        }
    }
}