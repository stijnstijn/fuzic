<?php
namespace Fuzic\Crawler\Stream;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;

/**
 * Class AbiosytChecker
 *
 * @package Fuzic
 */
class YoutubeChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'y-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Youtube';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'youtube';

    /**
     * Gather data about currently listed YouTube live streams listed
     */
    public function run() {
        $streams = array();
        foreach($this->games as $game => $settings) {
            if(!isset($settings['youtube'])) {
                continue;
            }

            foreach($settings['youtube'] as $youtube_game_ID) {
                unset($streams_raw);
                //get game live streams from youtube gaming
                //this involves some html scraping, but unfortunately there is no api (yet) so this is the next best thing
                $yt_html = get_url('https://gaming.youtube.com/game/'.$youtube_game_ID, array(), false, true);
                preg_match("/path: '\/browse',[\s]+params: {\"browseId\": \"".$youtube_game_ID."\"},[\s]+data: ([^\n]+)/si", $yt_html, $json);
                if(!isset($json[1]) || false === ($json = json_decode($json[1], true))) {
                    $this->log('Could not parse YouTube JSON for game '.$game.' (youtube ID '.$youtube_game_ID.')', Crawler\StreamChecker::LOG_EMERGENCY);
                    continue;
                }

                //this is some crazy framework data structure
                if(empty($json) || !isset($json['contents'])) {
                    $this->log('No YouTube streams found for game '.$game.' (youtube ID '.$youtube_game_ID.')', Crawler\StreamChecker::LOG_WARNING);
                    continue;
                }
                $json = $json['contents']['singleColumnBrowseResultsRenderer']['tabs'];
                foreach($json as $tab) {
                    if(strtolower($tab['softTabRenderer']['title']) == 'live') {
                        $streams_raw = $tab['softTabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['items'];
                        break;
                    }
                }

                if(!isset($streams_raw) || empty($streams_raw)) {
                    $this->log('No YouTube live streams for game '.$game, Crawler\StreamChecker::LOG_DEBUG);
                    continue;
                }

                //add streams found on live page
                foreach($streams_raw as $data) {
                    $data = $data['gamingVideoRenderer'];
                    $video_ID = $data['videoId'];

                    //views >= 1K are displayed as "1K" so we need to call an URL to get the precise number :/
                    $viewers = isset($data['shortViewCountText']) ? str_replace([' views', ' viewers'], '', $data['shortViewCountText']['runs'][0]['text']) : 0;
                    if(preg_match('/([0-9,.]+K)/', $viewers)) {
                        try {
                            $precise_viewers = get_url('https://www.youtube.com/live_stats?v='.$video_ID.'&time='.time(), [], false, false,3);
                            if($precise_viewers === false) {
                                throw new \ErrorException();
                            }
                        } catch(\ErrorException $e) {
                            $precise_viewers = floatval(str_replace([',', 'K'], ['.', ''], $viewers));
                            $precise_viewers *= 1000;
                        }
                        $this->log('Stream '.$data['shortBylineText']['runs'][0]['text'].' has more than 1K viewers ('.$viewers.'), queried precise value of '.$precise_viewers, Crawler\StreamChecker::LOG_WARNING);
                        $viewers = $precise_viewers;
                    } else {
                        $viewers = intval($viewers);
                    }

                    if($viewers < 5) { //ignore noobs
                        continue;
                    }

                    //annoyingly, youtube channels may not have a matching username, so sometimes we're gonna end up with
                    //some random-looking channel ID as stream ID, but oh well
                    if(isset($data['shortBylineText']['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'])) {
                        $channel = $data['shortBylineText']['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'];
                        if(isset($data['shortBylineText']['runs'][0]['navigationEndpoint']['browseEndpoint']['canonicalBaseUrl'])) {
                            $user = explode('/', $data['shortBylineText']['runs'][0]['navigationEndpoint']['browseEndpoint']['canonicalBaseUrl']);
                            $channel = array_pop($user);
                        }
                    } else {
                        if(isset($data['shortBylineText']['runs'][0]['navigationEndpoint']['watchGamingEventEndpoint']['gamingEventId'])) {
                            $channel = $data['shortBylineText']['runs'][0]['navigationEndpoint']['watchGamingEventEndpoint']['gamingEventId'];
                        } else {
                            var_dump($data);
                            continue;
                        }
                    }

                    $streams[$video_ID] = array(
                        'viewers' => $viewers,
                        'name' => $data['shortBylineText']['runs'][0]['text'],
                        'real_name' => $data['shortBylineText']['runs'][0]['text'],
                        'avatar' => 'https:'.$data['thumbnail']['thumbnails'][0]['url'],
                        'title' => $data['title']['runs'][0]['text'],
                        'provider' => static::DB_PROVIDER_ID,
                        'game' => $game
                    );
                    $this->add_stream($channel, $streams[$video_ID]);
                }
            }
        }

        //remove already crawled streams from indexed streams
        foreach(array_keys($streams) as $remote_ID) {
            if(isset($this->indexed[$remote_ID])) {
                unset($this->indexed[$remote_ID]);
            }
        }

        if(!class_exists("\Google_Client")) {
            $this->log('Google Client class not available, could not run individual checkers for streams '.implode(', ', array_keys($this->indexed)), Crawler\StreamChecker::LOG_EMERGENCY);
            $skip_google = true;
        }

        //if there are any leftover, crawl them individually
        if(!empty($this->indexed) && !isset($skip_google)) {
            try {
                $google = new \Google_Client();
                $google->setDeveloperKey(Fuzic\Config::GOOGLE_API_KEY);
                $youtube = new \Google_Service_YouTube($google);
            } catch(\Google_Exception $e) {
                $this->log('Google error "'.$e->getMessage().'" when setting up API', Crawler\StreamChecker::LOG_EMERGENCY);

                return;
            }

            $IDs = implode(',', array_keys((array) $this->indexed));

            $next = '';
            $video_data = array();

            //get video data from API for all streams
            while($next !== false) {
                try {
                    $videos = $youtube->videos->listVideos('snippet', ['id' => $IDs, 'pageToken' => $next]);
                } catch(\Google_Exception $e) {
                    $this->log('Google error "'.$e->getMessage().'" while retrieving live video data', Crawler\StreamChecker::LOG_EMERGENCY);

                    return;
                }
                foreach($videos['items'] as $stream) {
                    $video_data[$stream['id']] = $stream;
                }
                if(isset($videos['nextPageToken'])) {
                    $next = $videos['nextPageToken'];
                } else {
                    $next = false;
                }
            }

            foreach($this->indexed as $video_ID => $game) {
                $api_data = $video_data[$video_ID];
                if($api_data['snippet']['liveBroadcastContent'] != 'live') {
                    $this->log('YouTube stream '.$video_ID.' reported as live but not confirmed to be so by API', Crawler\StreamChecker::LOG_WARNING);
                }
                $channel = self::map_video_to_user($video_ID);

                //unfortunately there's no viewer number in the API response, so we need a separate URL call :/
                $viewers = intval(get_url('https://www.youtube.com/live_stats?v='.$video_ID.'&t='.time()));
                if(strstr($viewers, 'K') !== false) {
                    $viewers = intval($viewers) * 1000;
                }

                $this->add_stream($channel['remote_ID'], array(
                    'viewers' => $viewers,
                    'name' => $channel['name'],
                    'real_name' => $channel['name'],
                    'avatar' => $api_data['snippet']['thumbnails']['default']['url'],
                    'title' => $api_data['snippet']['title'],
                    'provider' => static::DB_PROVIDER_ID,
                    'game' => $game
                ));
            }
        }
    }

    /**
     * Get Youtube username for channel ID
     *
     * Annoyingly, youtube channels may not have a matching username, so sometimes we're gonna end up with
     * some random-looking channel ID as stream ID, but oh well
     *
     * @param string $channel_ID Channel ID
     *
     * @return mixed Username, or channel ID if none found
     */
    public static function map_channel_to_user($channel_ID) {
        try {
            $page = get_url('https://www.youtube.com/channel/'.$channel_ID);
            libxml_use_internal_errors(true);
            $html = new \DOMDocument();
            $html->loadHTML($page, LIBXML_NOWARNING);
            libxml_use_internal_errors(false);
            $header = getElementsByClassName($html, 'branded-page-header-title-link');
            if($header->length == 0) {
                return $channel_ID;
            }
            $link = $header->item(0)->getAttribute('href');
            if($link && preg_match('/\/user\/([^\/]+)/si', $link, $match)) {
                return $match[1];
            }
        } catch(\ErrorException $e) {
            return $channel_ID;
        }

        return $channel_ID;
    }

    /**
     * Get Youtube username for Video ID
     *
     * @param $video_ID   Video ID
     *
     * @return array|bool `array('name' => $name, 'remote_ID' => $user_ID)`, or `false` if failed
     */
    public static function map_video_to_user($video_ID) {
        try {
            $page = get_url('https://www.youtube.com/watch?v='.$video_ID);
            libxml_use_internal_errors(true);
            $html = new \DOMDocument();
            $html->loadHTML($page, LIBXML_NOWARNING);
            libxml_use_internal_errors(false);
            $header = getElementsByClassName($html, 'yt-card');
            if($header->length == 0) {
                return false;
            }

            $ID_link = getElementsByClassName($header->item(0), 'yt-user-photo');
            var_dump($ID_link);
            if($ID_link) {
                $remote_ID = explode('/', $ID_link->item(0)->getAttribute('href'));
                $remote_ID = array_pop($remote_ID);
                $name = $ID_link->item(0)->getElementsByTagName('img')->item(0)->getAttribute('alt');
                echo 'Remote ID '.$remote_ID."\n";

                return array('name' => $name, 'remote_ID' => $remote_ID);
            }

            return false;
        } catch(\ErrorException $e) {
            return false;
        }
    }
}