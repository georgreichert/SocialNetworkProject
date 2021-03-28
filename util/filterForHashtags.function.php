<?php
    // by "trante", found here: https://stackoverflow.com/questions/3060601/retrieve-all-hashtags-from-a-tweet-in-a-php-function
    function filterForHashtags (String $text) : array {
        $hashtags= FALSE;  
        preg_match_all("/(#\w+)/u", $text, $matches);  
        if ($matches) {
            $hashtagsArray = array_count_values($matches[0]); // remove duplicate tags
            $hashtags = array_keys($hashtagsArray); // only read tags, discard count values
        }
        return $hashtags;
    }
?>