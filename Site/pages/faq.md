<i class="fa fa-question-circle"></i> Frequently Asked Questions
===

What's this?
---

A site that keeps statistics and viewer numbers for live streams that broadcast people palying video games. It keeps track of how many people are watching these streams and at what times they are broadcasting, and uses these numbers to calculate rankings on, for example, which player has had the most viewers during the past week.

Originally viewers were tracked for StarCraft 2 only, but the site is currently expanding to cover other games as well. The following games are supported:

- [StarCraft 2](http://www.fuzic.nl)
- [StarCraft: Brood War](http://broodwar.fuzic.nl)
- [Heroes of the Storm](http://heroes.fuzic.nl)
- [Hearthstone](http://hearthstone.fuzic.nl)
- [Overwatch](http://overwatch.fuzic.nl)

Support for StarCraft 2 is the most robust; while most streams for the other games should be tracked, other data such as player names and events may be less accurate.

Which stream providers are tracked?
---

The site tracks live streams using the following providers:

- Afreeca (.kr)
- Azubu
- DailyMotion
- DingIt
- GamingLive
- Goodgame.ru
- Hitbox.tv
- Livestream
- Twitch.tv
- YouTube
- Stream.me

How accurate are the viewer numbers?
---

Since the streaming services' APIs are used to retrieve viewer numbers for streams, the numbers will be as accurate as the data APIs supply. Viewer numbers are retrieved approximately once every minute; this means that the very end and very beginning of a streaming session may be missed and hence may not be included in the session's average viewership. Additionally, wild fluctuations within the one minute intervals are not recorded. However, the interval should be short enough that the statistics should be reasonable accurate.

Streaming sessions that had a peak viewer number of less than 10, or last less than 10 minutes, are ignored. These are not included in event stats either. While this may lead to some minor inaccuracies, it is a necessary measure to keep the database at a reasonable size – for each session that is saved, about 7 have to be discarded.

Since the site launched in 2013, gradually more streaming services and games have been tracked and the method through which streams are linked to events has become more reliable. This should be taken into account when comparing events and periods.

According to what number are streams and events ranked?
---

"Viewers&times;hours", or the average amount of viewers multiplied by the amount of hours the streaming session or event lasted. This gives a good indication of popularity and how prolific a streamer is, as opposed to the average amount of viewers (which may be high even if the time streamed is very little). Most monthly, weekly and overall rankings are ranked by this V&times;H statistic. Most tables allow you to sort by average amount of viewers or peak viewers as well.

How do you handle timezones?
---

The site keeps time in UTC (or GMT) at its core. Hence, the cutoff points for months, weeks and days use this timezone as well. There is a timezone selector in the upper right corner of the site to accommodate those in different geographical locations; all times and dates displayed on the site will respect that setting. However, choosing any timezone other than GMT may result in misleading or apparently wrong time information for streaming sessions and event that take place on, for example, a sunday night (near the week cutoff point) or the very first day of the month.

Why is this stream/event/session missing from the stats?
---

A stream is tracked by the site if:

- It is a stream listed as playing one of the supported games, on one of the providers that supplies information about what game a stream is playing (Twitch, Afreeca, Azubu, DingIt, Dailymotion, Goodgame.ru, Hitbox.tv or GamingLive)
- OR it is a Stream.me stream listed on one of the event calendars as playing one of the supported games
- AND it has more than 10 viewers at any point during its streaming session, and the streaming session lasts for at least 10 minutes.

An event is tracked by the site if:

- It is listed on an event calendar tracked by this site (such as TeamLiquid)
- It has at least one stream, that matches the above criteria, listed as broadcasting that event.

Streams will be considered to be broadcasting an event if they are listed as such on a calendar If a streaming session overlaps with an event for at least 95% and the stream is an established event stream (high viewer count or lots of other events) the stream is added as well.

Why is [player x] not linked to the correct team?
---

Liquipedia is used to link streams to prominent players and the players to their teams. If a player is not correctly linked, the best way to fix this is to edit his or her Liquipedia page so it lists the correct Twitch TV stream for that player, and the correct team. This information is refreshed every day, so all you have to do once Liquipedia's information is correct is wait.

The site says there were zero viewers during part of my event/session, but I'm sure there were more. What's wrong?
---
Sometimes the Twitch API (or another API) is unresponsive for reasons beyond our control; there could be a server outage on their part, for example. In these cases the site will report 0 viewers. As it is not possible to request the amount of viewers for an arbitrary point in history through the API, it is unfortunately not feasible to reconstruct the correct viewer numbers retroactively. Therefore these anomalies will be left in.

How can I get in touch?
---

Check the [Contact](/contact/) page!

Why "Fuzic"?
---

Because I already owned this domain and wasn't using it for anything. It doesn't really mean anything! Though given the fact that the site combines a lot of data from different sources, a name that could be mistaken to be derived from "fuse" is quite appropriate.