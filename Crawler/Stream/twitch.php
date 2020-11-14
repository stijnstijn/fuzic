<?php

namespace Fuzic\Crawler\Stream;


use Fuzic;
use Fuzic\Lib;
use Fuzic\Config;
use Fuzic\Crawler;


/**
 * Class TwitchChecker
 *
 * Checks the Twitch.tv API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class TwitchChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = '';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Justin';

    /**
     * @var int   Amount of streams queried with each API request
     */
    private $twitch_page_size = 100;

    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'twitch';

    /**
     * File name of Twitch user ID mapping cache
     */
    const USERID_DB = 'twitch-ids.sqlite';


    /**
     * Check Twitch API for game data
     *
     * Sends requests to the Twitch.tv API to retrieve a list of currently active streams for
     * a game, in batches of 100. Data is then added to the live stream array.
     *
     * @return bool   Whether the API check was succesful
     */
    public function run() {
        //get Twitch IDs for the games we want to check
        $game_IDs = array();
        $cache = new Lib\Cache('fuzic');

        foreach($this->games as $game_ID => $game) {
            if(isset($game['twitch'])) {
                $game_IDs[$game['twitch']] = $game_ID;
            }
        }

        $twitch_headers = ['Client-ID: '.Config::TWITCH_API_CLIENTID];
        //authenticate via OAuth
        if(!$cache->get('twitch_oauth_token') || $cache->get('twitch_oauth_lifetime') < time()) {
            $api_url = 'https://api.twitch.tv/kraken/oauth2/token?client_id='.Config::TWITCH_API_CLIENTID.'&client_secret='.Config::TWITCH_API_SECRET.'&grant_type=client_credentials';
            try {
                $token = post_url($api_url);
                $token = json_decode($token, true);
                if(!$token || json_last_error() != JSON_ERROR_NONE || !isset($token['access_token'])) {
                    throw new \ErrorException('Could not retrieve valid JSON for Twitch OAuth token');
                }
                $cache->set('twitch_oauth_token', $token['access_token']);
                $cache->set('twitch_oauth_lifetime', time() + intval($token['expires_in']) - 60);
                $twitch_headers[] = 'Authorization: Bearer '.$token['access_token'];
            } catch (\ErrorException $e) {
                $this->log('Could not log in to Twitch API via OAuth: '.$e->getMessage().'. Continuing without authentication.', Crawler\StreamChecker::LOG_EMERGENCY);
            }
        }

        $base_url = 'https://api.twitch.tv/helix/streams?type=all&first='.$this->twitch_page_size.'&game_id='.implode('&game_id=', array_keys($game_IDs));
        $pagination = '';
        $twitch_streams = array();

        //get stream data from twitch API in batches
        $pages = 0;
        while(true) {

            //get raw API response
            $url = $base_url.$pagination;
            try {
                while(true) {
                    $twitch_response = get_url(
                        $url,
                        $twitch_headers,
                        true, false, 10
                    );
                    $pages += 1;
                    $headers = extract_headers($twitch_response);
                    if(isset($headers['Ratelimit-Remaining']) && $headers['Ratelimit-Remaining'] <= 0) {
                        $wait = $headers['Ratelimit-Reset'] - time();
                        if($wait > 0) {
                            $this->log('Twitch rate limit exceeded while getting streams, fetch #'.$pages.' - sleeping '.$wait.' seconds', Crawler\StreamChecker::LOG_WARNING);
                            sleep($wait);
                        }
                    }
                    if(isset($headers['HTTP-Response-Code']) && $headers['HTTP-Response-Code'] != 429) {
                        break;
                    }
                }
            } catch(\ErrorException $e) {
                $this->log('No connection to Twitch while fetching stream list from: '.$url.' ('.$e->getMessage().')', Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            //if it's not valid JSON, show an error
            try {
                $twitch_data = json_decode($twitch_response, true);
                if(json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception(json_last_error_msg());
                }
            } catch(\Exception $e) {
                $this->log('Could not retrieve Twitch API data ("'.$e->getMessage().'")'."\n", Crawler\StreamChecker::LOG_WARNING);
                exit;
            }

            //if we're at the last page, stop fetching, else see where the next page starts
            if(!isset($twitch_data['pagination']) || !isset($twitch_data['pagination']['cursor'])) {
                break;
            }
            $pagination = '&after='.$twitch_data['pagination']['cursor'];

            //add streams from current batch to data array
            if(isset($twitch_data['data']) && count($twitch_data['data']) > 0) {
                foreach($twitch_data['data'] as $stream) {
                    if($stream['viewer_count'] == 0) {
                        continue;
                    }
                    $twitch_streams[] = $stream;
                }
            } else {
                break;
            }
        }

        //Twitch no longer includes interesting info in the /streams/ response, so we have to look stuff up
        //processing from here on out
        $anon_streams = $twitch_streams;
        $missing_info = array();
        $all_info = array();

        //open database - the CREATE TABLE query will fail if it already exists but that's okay!
        $db = new \PDO('sqlite:twitch-users.sqlite');
        $db->exec("CREATE TABLE twitch_users (id INTEGER PRIMARY KEY, username TEXT, name TEXT, logo TEXT, crawl INTEGER)");

        //get info for known IDs from cache
        $IDs = implode(', ', array_map(function($stream) use ($db) {
            return $db->quote($stream['user_id']);
        }, $anon_streams));
        $stream_IDs = array();
        $query = $db->query("SELECT id, username, name, logo FROM twitch_users WHERE id IN(".$IDs.") AND crawl > ".intval(time() - (86400 * 7)));
        while($user_info = $query->fetch(\PDO::FETCH_ASSOC)) {
            $stream_IDs[$user_info['id']] = $user_info;
        }

        //see which IDs we already have info for and which we need to crawl anew
        foreach($anon_streams as $stream) {
            if(!isset($stream_IDs[$stream['user_id']])) {
                $missing_info[] = $stream['user_id'];
            } else {
                $all_info[$stream['user_id']] = $stream_IDs[$stream['user_id']];
            }
        }

        //ask Twitch for user info
        while(count($missing_info) > 0) {
            //get API response
            $page = array_slice($missing_info, 0, 100);
            $missing_info = array_slice($missing_info, 100);
            try {
                while(true) {
                    $twitch_response = get_url(
                        'https://api.twitch.tv/helix/users?id='.implode('&id=', $page),
                        $twitch_headers,
                        true, false, 10
                    );
                    $headers = extract_headers($twitch_response);
                    if(isset($headers['Ratelimit-Remaining']) && $headers['Ratelimit-Remaining'] <= 0) {
                        $wait = $headers['Ratelimit-Reset'] - time();
                        if($wait > 0) {
                            $this->log('Twitch rate limit exceeded while getting user info - sleeping '.$wait.' seconds', Crawler\StreamChecker::LOG_WARNING);
                            sleep($wait);
                        }
                    }
                    if(isset($headers['HTTP-Response-Code']) && $headers['HTTP-Response-Code'] != 429) {
                        break;
                    }
                }
            } catch(\ErrorException $e) {
                $this->log('No connection to Twitch while fetching user info from: '.$url.' ('.$e->getMessage().')', Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            //handle faulty responses
            try {
                $user_data = json_decode($twitch_response, true);
                if(json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception(json_last_error_msg());
                }
            } catch(\Exception $e) {
                $this->log('Could not retrieve twitch API data ("'.$e->getMessage().'")', Crawler\StreamChecker::LOG_WARNING);

                return false;
            }

            //this usually shouldn't happen, but just in case
            if(!is_array($user_data) || count($user_data) == 0 || !isset($user_data['data'])) {
                var_dump($user_data);
                break;
            }

            //save info and also put into database so we don't need to crawl it later
            foreach($user_data['data'] as $user) {
                $all_info[$user['id']] = array(
                    'username' => $user['login'],
                    'name' => $user['display_name'],
                    'logo' => $user['profile_image_url']
                );
                $db->exec("DELETE FROM twitch_users WHERE id = ".$db->quote($user['id']));
                $db->exec("INSERT INTO twitch_users (id, username, name, logo, crawl) VALUES (".
                    $db->quote($user['id']).", ".
                    $db->quote($user['login']).", ".
                    $db->quote($user['display_name']).", ".
                    $db->quote($user['profile_image_url']).", ".
                    $db->quote(time() + rand(0, 3600)).")"); //fuzz times so we don't have to refresh all at once
            }
        }

        //finally, save all stream data
        foreach($anon_streams as $stream) {
            $info = $all_info[$stream['user_id']];
            $this->add_stream($info['username'], [
                'provider' => static::DB_PROVIDER_ID,
                'viewers' => $stream['viewer_count'],
                'name' => $info['username'],
                'real_name' => $info['name'],
                'avatar' => $info['logo'],
                'title' => $stream['title'],
                'game' => Crawler\StreamChecker::map_game_name($game_IDs[$stream['game_id']])
            ]);
        }

        return true;
    }
}