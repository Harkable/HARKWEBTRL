<?php

class Trawler {
  
  // Social profile details
  private $_twitter_username = "";
  private $_instagram_user_id = "";

  // Tokens & keys
  private $_instagram_client_id = "";
  private $_twitter_consumer_key = "";
  private $_twitter_consumer_secret = "";
  private $_twitter_access_token = "";
  private $_twitter_access_token_secret = "";

  public function __construct() 
  {
    // Init
  }

  private function _fetch_instagrams()
  {
    global $wpdb;

    // Get the newest instagram in DB
    $sql = "SELECT * FROM hc_community WHERE is_instagram='1' ORDER BY created DESC LIMIT 1";
    $latest_instagram = $wpdb->get_row($sql);

    $url = "https://api.instagram.com/v1/users/1294564672/media/recent/";
    $url .= "?client_id=" . $this->_instagram_client_id;
    $url .= ($latest_instagram) ? "&min_timestamp=" . ($latest_instagram->created + 1) : "";

    $response = file_get_contents($url);
    $response = json_decode($response);

    $return_array = array();

    foreach($response->data as $photo)
    {
      $photo_url = $photo->images->low_resolution->url;

      array_push($return_array, array(
        'is_instagram' => TRUE,
        'instagram_src' => $photo_url,
        'instagram_link' => $photo->link,
        'created' => $photo->created_time
      ));
    }

    return $return_array;
  }

  private function _fetch_tweets()
  {
    require_once("twitteroauth/twitteroauth.php");
    global $wpdb;

    // Get the newest instagram in DB
    $sql = "SELECT * FROM hc_community WHERE is_tweet='1' ORDER BY created DESC LIMIT 1";
    $latest_tweet = $wpdb->get_row($sql);

    // Build the URL
    $url = "https://api.twitter.com/1.1/statuses/user_timeline.json";
    $url .= "?screen_name=".$this->_twitter_username;
    $url .= "&trim_user=t";
    $url .= "&exclude_replies=true";
    $url .= "&include_rts=false";
    $url .= ($latest_tweet) ? "&since_id=" . $latest_tweet->tweet_id : "";

    // Get the tweets
    $connection = new TwitterOAuth($this->_twitter_consumer_key, $this->_twitter_consumer_secret, $this->_twitter_access_token, $this->_twitter_access_token_secret);
    $tweets = $connection->get($url);

    $return_array = array();

    foreach($tweets as $tweet)
    {
      array_push($return_array, array(
        'tweet_id' => $tweet->id,
        'tweet_text' => preg_replace('"\b(http://\S+)"', '<a href="$1" target="_blank">$1</a>', $tweet->text),
        'tweet_link' => "https://www.twitter.com/statuses/".$tweet->id,
        'created' => strtotime($tweet->created_at)
      ));
    }

    return $return_array;
  }

  public function get_tweets()
  {
    global $wpdb;

    // First get cached posts from Twitter & Instagram
    $tweets = $wpdb->get_results("SELECT * FROM hc_community WHERE is_tweet='1' ORDER BY created DESC LIMIT 20");
    return $tweets;
  }

  public function get_instagrams()
  {
    global $wpdb;
    $instagrams = $wpdb->get_results("SELECT * FROM hc_community WHERE is_instagram='1' ORDER BY created DESC LIMIT 30");
    return $instagrams;
  }

  public function crawl()
  {
    $instagrams = $this->_fetch_instagrams();
    $tweets = $this->_fetch_tweets();

    // Insert tweets
    $sql = "INSERT hc_community (is_tweet,is_instagram,tweet_id,tweet_text,tweet_link,instagram_src,instagram_link,created) VALUES";
    $first = TRUE;

    foreach($tweets as $tweet)
    {
      $sql .= (!$first) ? "," : "";
      $sql .= sprintf("('%s','%s','%s','%s','%s','%s','%s','%s')",
                '1',
                NULL,
                mysql_escape_string($tweet['tweet_id']),
                mysql_escape_string($tweet['tweet_text']),
                mysql_escape_string($tweet['tweet_link']),
                NULL,
                NULL,
                mysql_escape_string($tweet['created']));

      $first = FALSE;
    }

    foreach($instagrams as $instagram)
    {
      $sql .= (!$first) ? "," : "";
      $sql .= sprintf("('%s','%s','%s','%s','%s','%s','%s','%s')",
                NULL,
                '1',
                NULL,
                NULL,
                NULL,
                mysql_escape_string($instagram['instagram_src']),
                mysql_escape_string($instagram['instagram_link']),
                mysql_escape_string($instagram['created']));

      $first = FALSE;
    }

    // If there is data to insert
    if (!$first) {
      global $wpdb;
      $wpdb->query($sql);
    }
  }
}

?>