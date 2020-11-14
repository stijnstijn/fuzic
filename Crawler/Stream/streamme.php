<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class StreammeChecker
 *
 * Checks the Stream.me API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class StreammeChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'sm-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Streamme';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'streamme';

    /**
     * Gather data about currently broadcasting streams from the Stream.me API
     *
     * This service's API contains info about which game a stream is broadcasting. Therefore, the game is determined by
     * the API response and all streams using the server are reported rather than only those listed on external indexes.
     */
    public function run() {
        if(empty($this->indexed) || count($this->indexed) == 0) {
            $this->log('Skipping stream.me, no active streams reported', Crawler\StreamChecker::LOG_WARNING);
            return;
        }

        try {
            $source = get_url('https://www.stream.me/live');
        } catch(\ErrorException $e) {
            $this->log('Could not load stream.me live streams page.', Crawler\StreamChecker::LOG_WARNING);
            return;
        }

        preg_match('/vee.context = ([^\n]+);/si', $source, $match);
        if(!$match[1] || empty($match[1])) {
            $this->log('Could not acquire json from stream.me live streams page.', Crawler\StreamChecker::LOG_WARNING);
            return;
        }

        $json = json_decode($match[1], true);
        if(!$json || !isset($json['streams']['models'])) {
            $this->log('Could not parse json from stream.me live streams page.', Crawler\StreamChecker::LOG_WARNING);
            return;
        }

        if(count($json['streams']['models']) == 0) {

            $this->log('Stream.me API is available but no streams were live', Crawler\StreamChecker::LOG_WARNING);
            return;
        }

        foreach($json['streams']['models'] as $stream) {
            if(!$stream['active']) {
                continue;
            }

            $remote_ID = $stream['userSlug'];
            if(!isset($this->indexed[$remote_ID])) {
                continue;
            }

            $this->add_stream($remote_ID, array(
                'viewers' => $stream['stats']['raw']['viewers'],
                'name' => $stream['displayName'],
                'avatar' => $stream['_links']['avatar']['href'],
                'title' => $stream['title'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $this->indexed[$remote_ID]
            ));
        }
    }
}