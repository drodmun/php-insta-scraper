<?php header('Access-Control-Allow-Origin: *'); ?>

<?php

// Caller to standard PHP json decoder controlling error cases
function jsonDecode($json_string, $assoc = true) {
    $json_decoded = json_decode($json_string, $assoc);
    if ($error = json_last_error()) {
        $errorReference = [
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded.',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON.',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded.',
            JSON_ERROR_SYNTAX => 'Syntax error.',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded.',
            JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded.',
            JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded.',
            JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given.',
        ];
        $errStr = isset($errorReference[$error]) ? $errorReference[$error] : "Unknown error ($error)";
        throw new \Exception("JSON decode error ($error): $errStr");
    }
    return $json_decoded;
}

function getPublicInfoData($username) {
    $url     = sprintf("https://www.instagram.com/$username");
    $content = file_get_contents($url);
    $content = explode("window._sharedData = ", $content)[1];
    $content = explode(";</script>", $content)[0];

    return $content;
}

function getPublicInformationJSON($username) {
    $data    = jsonDecode(getPublicInfoData($username));
	
    return json_encode($data['entry_data']['ProfilePage'][0]);
}

$cache_file = "./instagram_feed.json";

$last_modified = date("Ymd", filemtime($cache_file));
$current_date  = date("Ymd");

$cache_file_content = null;
if ($last_modified < $current_date) { // Older than today
    $cache_file_content = getPublicInformationJSON('drodmun');
    unlink($cache_file); // Kill it and create it new

    $fp = fopen($cache_file, 'w');
    fwrite($fp, $cache_file_content);
    fclose($fp);
}else{
    $cache_file_content = file_get_contents($cache_file);
}

function printOnlyImagesFormatted($file_content){
    $results_array = jsonDecode($file_content);
    
    //An example of where to go from there
    // $latest_array = $results_array['graphql']['user']['edge_owner_to_timeline_media']['edges'][0]['node'];
    
    // loop to extract data from an array
    // foreach ($latest_array as $key => $value) {
    //     echo "$key | $value <br/>";
    // }
    
    // echo 'Latest Photo:<br/>';
    // echo '<a href="http://instagram.com/p/'.$latest_array['shortcode'].'"><img src="'.$latest_array['display_url'].'"></a></br>';
    // echo 'Likes: '.$latest_array['likes']['count'].' - Comments: '.$latest_array['comments']['count'].'<br/>';
    
    
    // BAH! An Instagram site redesign in June 2015 broke quick retrieval of captions, locations and some other stuff.
    // echo 'Taken at '.$latest_array['location']['name'].'<br/>';
    //Heck, lets compare it to a useful API, just for kicks.
    // echo '<img src="http://maps.googleapis.com/maps/api/staticmap?markers=color:red%7Clabel:X%7C'.$latest_array['location']['latitude'].','.$latest_array['location']['longitude'].'&zoom=13&size=300x150&sensor=false">';
    
    $instagram_latest = array();
    
    // TODO Control infinite scroll to paginate
    // $totalPosts = $results_array['graphql']['user']['edge_owner_to_timeline_media']['count'];
    $latest_images = $results_array['graphql']['user']['edge_owner_to_timeline_media']['edges'];
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
    
    var_dump(json_encode($instagram_latest));
}

/**
 * Return everything as it comes
 */
//print_r(file_get_contents($cache_file));

/**
 * Return only the images already formated to be received by the front
 */
// printOnlyImagesFormatted($cache_file_content);
?>