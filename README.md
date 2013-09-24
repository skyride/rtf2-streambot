##Reddit /r/tf2 Sidebar Stream Bot

This bot takes data on livestreams and events from [TeamFortress.TV](http://teamfortress.tv/) and places it in the sidebar at regular 2 minute intervals.

The sidebar itself is pulled from [this](http://www.reddit.com/r/tf2/wiki/sidebar) wiki page. A replace is performed on "%%STREAMS%%" for the livestreams and "%%EVENTS%%" for the events list. The user account specified in config.php must be a moderator on the subreddit, and the /wiki/sidebar page must be publicly viewable.

To run the bot as a daemon, just run do.php inside a session, or add updater.php as a cron job.