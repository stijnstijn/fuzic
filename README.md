# Fuzic
From 2013 to 2020, Fuzic aggregated viewer numbers for StarCraft II, Overwatch, Hearthstone, Heroes of the Storm, World of Warcraft and Brood War live streams and turned them into rankings, overviews and statistics. These could then be viewed on the site, and I also posted them on other sites regularly ([example](https://tl.net/forum/brood-war/538148-top-bw-streamers-and-stats-september-2018)).

The idea was that people could easily see who the most popular streamers for a particular game were, and which events drew the most viewers. Unlike other sites that e.g. ranked Twitch streamers, Fuzic combined data from multiple streaming sites and also used external event calendars to combine data from separate streams streaming the same events. It was also used by some eSports teams and by Blizzard to keep track of the popularity of particular streamers and events.

Because I was no longer able to spend as much time on it as needed to keep up the quality of the data, Fuzic stopped collecting data in February 2020.

This is the code behind Fuzic. It runs on PHP7 and MySQL, so it probably should not be too complicated to set up elsewhere should you want to do so, though I haven't tested that. Some proprietary or commercially licensed assets (e.g. fonts) have been removed from the code. In most cases there should be a fallback, else the error messages will point you to where to add files or change the code :-)

## Data
Fuzic's database (i.e. the viewer numbers and so on) are at the time of writing available [here](https://fuzic.nl/assets/fuzic.sql.gz).

## License
This code is licensed under the terms of the Mozilla Public License 2.0. See the `LICENSE` file for more details. Note that it is not allowed to use the name 'Fuzic' for something you set up using this code.

## Contact
If you use this code in some way, I'd be very interested in hearing about it. You can reach me via stijn@fuzic.nl or via [Twitter](https://twitter.com/stijnstijn).