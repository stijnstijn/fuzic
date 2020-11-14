<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class LiveStreamChecker
 *
 * Checks the LiveStream API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class InactiveLivestreamChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'l-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Mogulus';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'livestream';

    /**
     * Gather data about currently broadcasting streams from the Livestream API
     *
     * This service's API contains no info about which game a stream is broadcasting. Therefore, data gathered earlier
     * from stream indexes is used to determine which streams to fetch information for.
     */
    public function run() {
        $streams = $this->streams;

        foreach($streams as $remote_ID => $game) {
            $remote_id = str_replace('_', '-', $remote_ID);

            try {
                $remote_json = get_url('http://x'.$remote_id.'x.api.channel.livestream.com/2.0/info.json');
            } catch(\ErrorException $e) {
                $this->log('No connection to Livestream while fetching info for '.$remote_ID, Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            $remote_data = json_decode($remote_json, true);
            if(!$remote_data) {
                $this->log('Invalid LiveStream data for stream '.$remote_id, Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            if(!$remote_data['channel']['isLive']) {
                continue;
            }

            $this->add_stream($remote_ID, array(
                'viewers' => $remote_data['channel']['currentViewerCount'],
                'name' => $remote_data['channel']['title'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $game
            ));
        }
    }
}