<i class="fa fa-pencil-square-o"></i> Blog
===

Maintenance report
---

### September 4, 2017
To keep things reasonably speedy many old sessions have been moved to a backup database, meaning that they are no longer available via the site. 

If a session is older than 6 months, had less than 500 viewers and was not streamed by a notable streamer, it is no longer available via the site interface. If this affects you and you would like to see your sessions restored, [contact](/pages/contact) us.

Bla
---

### June 7, 2016

It's been a while since the last update here, but that's more a case of me forgetting to update this page than nothing happening behind the scenes. Some updates:

- Added a Trends page with historic information on the game's overall viewership
- Made it possible to go back further in time for the graphs on stream pages
- Started saving the most-used titles for streaming sessions so you can get a general idea of what was streamed during the session
- Fixed inaccuracies in the ranking algorithms so monthly and weekly numbers should be more accurate
- Started tracking data for other Blizzard games ([Overwatch](//overwatch.fuzic.nl), [Brood War](//broodwar.fuzic.nl), [Heroes of the Storm](//heroes.fuzic.nl) and [Hearthstone](//hearthstone.fuzic.nl))

Some updates
---

### August 20, 2015

TeamLiquid's new event calendar also shows the match being played currently for events that provide this info. These matches are also tracked by Fuzic and event pages show during which part of the event a match was played. This allows you to see who was playing when viewership peaked, or how viewership drops after a match ended.

Due to some problems with Twitch's API (them changing StarCraft II's game ID, specifically), some data for the 9-16 August week was lost unfortunately. Tracking's back to normal now though!

*-Stijn*


Back once again
---

### July 14, 2015

I'm happy to say the after a few weeks of absence, weekly and monthly rankings are back again (and being updated
frequently).

What happened? Life got in the way, as it is wont to do. Recalculating the stats and rankings every so often got too
heavy for the server to handle, and I didn't really have time to fix and optimize things, so I pulled that part of the
site offline for the time being. I've now optimized and rewritten the things that were causing trouble, and everything
seems to work correctly now.

Oh, and I see I forgot to mention this earlier, but Afreeca streams (on the Korean part of the site) are tracked too,
now.

*-Stijn*

Hitbox.tv support
---

### August 14, 2014

Hitbox.tv support is on! As for Goodgame.ru, Dailymotion, Auzubu and Livestream, streams on the service need to be listed on TeamLiquid in order to be tracked.

There were a few hours of downtime last monday due to a server/database crash. As I was on vacation I wasn't really able to figure out what happened, but the sudden influx of visitors following <a href="/streams/destiny/">Destiny</a> linking to the site on TeamLiquid might have been a factor. Thanks to <a href="/streams/desrowfighting/">desRow</a> for reporting the downtime!

*-Stijn*


Goodgame.ru support
---

### July 21, 2014
And Goodgame.ru streams are in too, now. That covers about all streaming services commonly used for SC2 streams, I think. Except for MLG TV, but they don't report viewer numbers, so tracking them is little use...

*-Stijn*



Dailymotion and Livestream support
---

### July 12, 2014
Azubu.tv support worked well, so DailyMotion and Livestream streams are now followed as well. The DailyMotion API unfortunately seems a bit shaky, sometimes reporting 0 viewers for some streams. Let's hope they fix the problems on their end soon.

*-Stijn*


Azubu support
---

### July 5, 2014
Azubu.tv streams are also tracked now. This is experimental! If everything goes well I'll see whether I can also include other stream sources.

*-Stijn*


Fuzic 2.0 (beta!)
---

### June 22, 2014
Fuzic is back! Over the past few weeks the whole site has been remade from the ground up to be more future-proof and reliable.

What's more, the script that gathers data for the site has been running all along, so most of the period between september 2013 and now (june 2014) is covered. So even though Fuzic was down over the past few months, you'll still be able to view data on events and streaming sessions that took place during that period.

However - I'm still putting the "beta" label on the site. While everything more or less works, there's some functionality that's yet to be implemented. So keep an eye out for improvements over the coming months. Things I plan to add:

- Better team pages
- Better franchise pages
- ~~Pagination for session lists on stream pages, so you can view older sessions~~
- Uptime monitor, so you can see precisely which periods of time are covered and which are not
- ~~Timezone selection~~
- Cosmetic tweaks: not everything looks as good as it should yet!
- I'm still on the fence with regards to the main site colour.

There's no telling when these things will be implemented - it depends on how much time I have and how difficult implementing them turns out to be. But the base site is back online, and the rankings and stream statistics should be accurate (taking into account the limitations listed in the [FAQ](/faq/)).

If you have any suggestions or ideas for how to improve the site, don't hesitate to [contact me](/contact/)! I'm especially interested in feature requests from streamers themselves.

That's it for now, I hope you enjoy the site being back up!

*-Stijn*


Down for now
---

### January 2014
Fuzic is down for now (but hopefully not forever). Please read the rest of this message for an explanation.

I originally worked on Fuzic as a project to let off some steam while I was writing my thesis for university. It worked out that Fuzic was more or less finished when my thesis was, which was around September 2013. I set up Fuzic to run mostly on its own, updating stats and the like automatically, so I didn't have to spend much time on maintenance.

When I originally made the site it simply calculated stats per week. This worked fine until 2014 came closer and I realized the site wasn't set up to handle multiple years (after all, after week 52 of 2013 comes week 1 of 2014). I thought fixing this would be relatively easy, but it turned out otherwise.

I have updated part of the site, but right now I simply lack the time and motivation to work much on it. I am about to start a new full-time job and my interest in StarCraft 2 is relatively low at the moment. I do intend to eventually fix things up, but for now the site will unfortunately not be available. Hopefully I will find time again in the future.

Though there will be a few gaps in the data, I do also intend to keep collecting viewer numbers in the meanwhile. The part of the system that collects those numbers still works fine. If the site comes back again, it should be able to show you stats for most of 2014 (and before, obviously).

If you have any further questions, I can be contacted at [@fuzicnl](http://www.twitter.com/fuzicnl).
*-Stijn*
