<?php

include("config.php");
include("Snoopy.class.php");
include("Googl.class.php");


//Escape invalid characters that can mess up reddit code
function escape($i)
{
        //$i = str_replace("(", "\\(", $i);
        //$i = str_replace(")", "\\)", $i);
        $i = str_replace("\\", "\\\"", $i);
		$i = str_replace("\n", "", $i);
		
		return $i;
}


//Get shortened URL
function shortUrl($url)
{
	//First check if we already have a hashed URL
	if(file_exists("urls/" . sha1($url)))
	{
		//Grab that URL
		return file_get_contents("urls/" . sha1($url));
	}
	
	//There has been at least 2 occasions in a 6 week period where a shortened URL returned false
	//We'll give it 5 attempts to shorten the URL, if it fails we'll notice. The hashed URL files
	//should have a size of 19 bytes, they'll be 0 if the URL failed to shorten.
	$attempts = 0;
	$short = "";
	while(strlen($short) < 1 && $attempts < 5)
	{
		//Create it since it doesn't exist
		$googl = new Googl('AIzaSyB08OAFSZ5MDgyx3YYt9iBhPkOj0a6HrHM');
		$short = $googl->shorten($url);
		
		//Increment attempts
		$attempts++;
	}
	
	//Write to file
	file_put_contents("urls/" . sha1($url), $short);
	
	//Return
	return $short;
}

function getEvents()
{
	//Download XML from tf.tv
	$xml = file_get_contents("http://teamfortress.tv/rss/events");
	$xml = simplexml_load_string($xml);
	
	$events = "";
	
	//print_r($xml->channel->item[0]);
	//Parse to text
	if(count($xml->channel->item) == 0)
	{
		$events = "* **No Upcoming Events**  \n";
	} else
	{
		foreach($xml->channel->item as $event)
		{
			$now = new DateTime();
			$future = new DateTime($event->pubDate);
			
			//Debug
			$now->setTimestamp($now->getTimestamp() + 7200);
			
			//Build description
			$desc = (strlen($event->description) > 143) ? substr($event->description,0,140).'...' : $event->description;
		
			//Check the event hasn't already passed
			if($future->getTimestamp() > ($now->getTimestamp() - 5400))
			{
				//Check if the event is live
				if($future->getTimestamp() < $now->getTimestamp())
				{
					//Game is live
					
					//Time
					$e = "* [**LIVE - ".escape($event->title)."**](".escape($event->link)." \"".escape($desc)."\")  \n";
					
					//Add to stack
					$liveevents[] = $e;
				} else
				{
					//Game starts in the future
					
					//Calculate time difference
					$interval = $future->diff($now);
					
					//>=1 day
					if(($future->getTimestamp() - $now->getTimestamp()) >= 86400)
					{
						$t = $interval->format("%dd %hh");
						$e = "* [{$t} - ".escape($event->title)."](".escape($event->link)."  \"".escape($desc)."\")  \n";
					}
					
					//<1 day
					if(($future->getTimestamp() - $now->getTimestamp()) < 86400)
					{
						$t = $interval->format("%hh %im");
						$e = "* [{$t} - ".escape($event->title)."](".escape($event->link)."  \"".escape($desc)."\")  \n";
					}
					
					//Add to stack
					$futureevents[] = $e;
				}
			}
		}
		
		//Combine events
		while(count($liveevents) > 0)
		{
			$events .= array_pop($liveevents);
		}
		while(count($futureevents) > 0)
		{
			$events .= array_pop($futureevents);
		}
	}
	
	return $events;
}

function getStreams()
{
	//Download XML from tf.tv
	$xml = file_get_contents("http://teamfortress.tv/rss/streams");
	$xml = simplexml_load_string($xml);
	
	$streams = "";
	
	//Parse to text
	if(count($xml) == 0)
	{
		$streams = "* **No Streams are currently live**  \n";
	} else
	{
		foreach($xml as $stream)
		{
			//$streams .= "* [**" . escape($stream->name) . "** - " . $stream->viewers . " viewers](" . $stream->link . " \"" . escape($stream->title) . "\")  \n";		//With Title
			$streams .= "* [**" . escape($stream->name) . "** - " . $stream->viewers . " viewers](" . $stream->link . ")  \n";		//Without Title
		}
	}
	
	//Return value
	return $streams;
}
	
	//Ok lets do some really messy HTTP stuff
	$snoopy = new Snoopy;
	
	//Set some initialisation
	$snoopy->agent = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:13.0) Gecko/20100101 Firefox/13.0";		//User agent of the latest firefox version
	
	
	//Login to reddit
		$post['user'] = $r_user;
		$post['passwd'] = $r_pass;
		$post['api_type'] = "json";
		$snoopy->submit("http://www.reddit.com/api/login/".$r_user, $post);
	
		//Submit login
		$login = json_decode($snoopy->results);
	
	//Check if login was successful
	if(count($login->json->errors) < 1)
	{
		//Generate Streams List
		$streams = getStreams();
		$events = getEvents();
	
		//Set cookies
		$snoopy->cookies["reddit_session"] = $login->json->data->cookie;
		
		//Get wiki info
		$snoopy->fetch("http://www.reddit.com/r/".$r_subr."/about/edit/.json");
		$admin = json_decode($snoopy->results);
		
		//print_r($admin);
		//print_r($login);
		
		//Create description
		$json = json_decode(file_get_contents("http://www.reddit.com/r/".$r_subr."/wiki/sidebar.json"));
		$description = $json->data->content_md;
		$description = str_replace("%%STREAMS%%", $streams, stripslashes($description));
		$description = str_replace("%%EVENTS%%", $events, stripslashes($description));
		
		//Set post values
		$post = array();
		$post['sr'] = $r_tid;
		$post['title'] = $admin->data->title;
		$post['thing_id'] = '';
		$post['public_description'] = $admin->data->public_description;
		$post['public_description_conflict_old'] = '';
		$post['description'] = $description;
		$post['description_conflict_old'] = '';
		$post['prev_public_description_id'] = $admin->data->prev_public_description_id;
		$post['prev_description_id'] = $admin->data->prev_description_id;
		$post['lang'] = $admin->data->language;
		$post['type'] = $admin->data->subreddit_type;
		$post['link_type'] = $admin->data->content_options;
		$post['wikimode'] = $admin->data->wikimode;
		$post['wiki_edit_karma'] = $admin->data->wiki_edit_karma;
		$post['wiki_edit_age'] = $admin->data->wiki_edit_age;
		$post['allow_top'] = 'on';
		$post['header-title'] = '';
		$post['id'] = '#sr-form';
		$post['r'] = 'tf2';
		$post['uh'] = $login->json->data->modhash;
		$post['show_media'] = 'on';
		$post['renderstyle'] = 'html';
		
		//Set cookies
		//print_r($snoopy->cookies);
		
		//Now submit all of this
		$snoopy->submit("http://www.reddit.com/api/site_admin", $post);
		
		echo "\n\n" . date("[Y/M/d - H:i]: ") . strlen($description);
	}
	
	echo "\n\n" . $snoopy->results . "\n\n";
?>
