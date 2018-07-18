<?php header('Access-Control-Allow-Origin: *'); ?>

<?php
    class Utils {
        /**
         * Caller to standard PHP json decoder, but controlling error cases
         */
        public static function jsonDecode($json_string, $assoc = true) {
            $json_decoded = json_decode($json_string, $assoc);
            if ($error = json_last_error()) {
                $errorReference = [
                    JSON_ERROR_DEPTH => 'Maximum stack depth exceeded.',
                    JSON_ERROR_STATE_MISMATCH => 'Invalid JSON.',
                    JSON_ERROR_CTRL_CHAR => 'Control character error.',
                    JSON_ERROR_SYNTAX => 'Syntax error.',
                    JSON_ERROR_UTF8 => 'Malformed UTF-8.',
                    JSON_ERROR_RECURSION => 'Recursive references.',
                    JSON_ERROR_INF_OR_NAN => 'NAN or INF values.',
                    JSON_ERROR_UNSUPPORTED_TYPE => 'Type cannot be encoded.',
                ];
                $errStr = isset($errorReference[$error]) ? $errorReference[$error] : "Unknown error ($error)";
                throw new \Exception("JSON decode error ($error): $errStr");
            }
            return $json_decoded;
        }

        /**
         * Erase old file and create a new one with data indicated
         */
        public static function createCacheFile($cache_file, $JSON_DATA){
            // Delete old file
            unlink($cache_file);
    
            // Save data to new file
            $fp = fopen($cache_file, 'w');
            fwrite($fp, $JSON_DATA);
            fclose($fp);
        }
    }

    class InstagramScrapper {

        /**
         * Get RAW public data of an USER, as object
         */
        public static function getUserPublicInfoData($username) {
            $url     = sprintf("https://www.instagram.com/$username");
            $content = file_get_contents($url);
            $content = explode("window._sharedData = ", $content)[1];
            $content = explode(";</script>", $content)[0];
    
            return $content;
        }
    
        /**
         * Get profile public data of an USER, as JSON
         */
        public static function getPublicInformationJSON($username) {
            // echo '<br> getPublicInformationJSON for: ' . $username; // DEBUG

            $raw_data = InstagramScrapper::getUserPublicInfoData($username);
            // echo '<br> $raw_data: '; var_dump($raw_data); // DEBUG

            $data    = Utils::jsonDecode();
            
            return json_encode($data['entry_data']['ProfilePage'][0]);
        }
        
        /**
         * Return formatted information of images only
         * TODO: Control infine scroll, right now only first 12 posts accesed
         * 
         * Examples:
         * - How to navigate to a post: 
         *      $latest_post = $user_data['graphql']['user']['edge_owner_to_timeline_media']['edges'][0]['node'];
         * - Show accesible key/values of a post: 
         *      foreach ($latest_post as $key => $value) { echo "$key | $value <br/>"; }
         * - Show Image + link to post in IG: 
         *      echo '<a href="http://instagram.com/p/'.$latest_post['shortcode'].'"><img src="'.$latest_post['display_url'].'"></a></br>';
         * - Show Description / Title: 
         *      echo $latest_post['edge_media_to_caption']['edges'][0]['node']['text'];
         * - Show Likes: 
         *      echo $latest_post['edge_media_preview_like']['count'];
         * - Show Comments: 
         *      echo $latest_post['edge_media_to_comment']['count'];
         */
        public static function printOnlyImagesFormatted($rawJSONData){
            // echo '<br> $printOnlyImagesFormatted: '; var_dump($rawJSONData); // DEBUG
            $user_data = Utils::jsonDecode($rawJSONData);
            
            $instagram_latest = array();
            
            // TODO Control infinite scroll to paginate
            // $totalPosts = $user_data['graphql']['user']['edge_owner_to_timeline_media']['count'];
            $latest_images = $user_data['graphql']['user']['edge_owner_to_timeline_media']['edges'];
            foreach ( $latest_images as $image_data ) {
                $image = $image_data['node'];
            
                $instagram_latest[] = array(
                    'description'   => $image['edge_media_to_caption']['edges'][0]['node']['text'],
                    'link'          => '//instagram.com/p/' . $image['shortcode'],
                    'time'          => $image['taken_at_timestamp'],
                    'comments'      => $image['edge_media_to_comment'] != null ? $image['edge_media_to_comment']['count'] : '',
                    'likes'         => $image['edge_media_preview_like'] != null ? $image['edge_media_preview_like']['count'] : '',
                    'thumbnail'     => $image['thumbnail_src'],
                    'media_preview' => $image['media_preview'],
                    'display_url'   => $image['display_url']
                );
            }
            
            return json_encode($instagram_latest);
        }
    }
?>

<?php

    // DATA TO RETURN
    $JSON_DATA = null;

    // File where content is going to be cached
    $cache_file = "./instagram_feed.json";

    // Time to be cached
    $cache_time = 24*60*60*1000; // 1 day (hours*minutes*seconds*1000)

    // Execute API only if username passed
    if(isset($_GET["username"])) {
        
        // Retrieve username of URL to obtain data from IG
        $username = htmlspecialchars($_GET["username"]);
        // echo '<br> username: '; var_dump($username); // DEBUG

        // Check is request is going to be saved in cache, if param exists will force and update from IG
        $forceUpdate = isset($_GET["force"]);
        // echo '<br> forceUpdate: '; var_dump($forceUpdate); // DEBUG
        
        // Last time cache file was saved, if doesn't exists, 0...
        $last_modified_ts = file_exists($cache_file) ? filemtime($cache_file) : 0;

        // This exact moment
        $current_timestamp  = time();
        
        // If force to update cache, or cache older than time set, we delete the file and recreate it
        if ($forceUpdate || ($current_timestamp - $last_modified_ts >= $cache_time)) {
            // echo '<br> Updating file: '; var_dump($forceUpdate); // DEBUG

            // Retrieve new information from IG
            $JSON_DATA = InstagramScrapper::getPublicInformationJSON($username);

            // Call to create cached file
            InstagramScrapper::createCacheFile($cache_file, $JSON_DATA);
        } else{
            // echo '<br> Retrieving from file: '; var_dump($cache_file); // DEBUG

            // No update required, we can obtain data form cached file
            $JSON_DATA = file_get_contents($cache_file);
            // echo '<br> Retrieved: '; var_dump($JSON_DATA); // DEBUG
        }

        // By default we will return full data, if param onlyPics exists, well...
        if(isset($_GET['onlyPics'])) {
            // echo '<br> onlyPics for: '; var_dump($JSON_DATA); // DEBUG

            //Modify return object to get only the images already formated to be received by the front
            $JSON_DATA = InstagramScrapper::printOnlyImagesFormatted($JSON_DATA);
            // echo '<br> onlyPics data obtained: '; var_dump($JSON_DATA); // DEBUG
        }

        /**
         * Return everything as it comes or only pics if modified
         */
        print_r($JSON_DATA);
    }
?>