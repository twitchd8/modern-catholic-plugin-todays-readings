=== Modern Catholic – Today’s Readings ===
Requires at least: 7.0
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Provides a dynamic WordPress block backed by a persistent cache of the USCCB
calendar and RSS feed.

This plugin is part of the Modern Catholic suite. Retrieval is currently
dormant because the upstream USCCB feed presents browser verification to
server-side requests. Existing cached readings remain available.

The rolling cache covers 90 calendar dates: today through 89 days from today.
Calendar days, not feed-item counts, determine the window. Multiple records on
one date are retained individually with their liturgical color and reading
citations.

Vigil Masses keep the following celebration date while being filed under the
actual civil date of the Mass (the preceding late afternoon or evening).

== Changelog ==

= 0.5.1 =
* Add block usage and Create Block Theme export guidance to the settings page.
* Explain the no-cache fallback behavior.

= 0.5.0 =
* Make the Today’s Readings block an explicit dynamic text-and-links placeholder.
* Add compact text and card layouts.
* Add editor controls for the heading, date, color, lectionary, citations,
  permitted RSS description, and USCCB source link.
* Keep all reading data out of saved page and template content.

= 0.4.0 =
* Expand the rolling cache from eight to 90 calendar dates.
* Retrieve calendar and RSS data every 30 days at the selected site-local time.
* Migrate and remove the previous daily event automatically.
* Identify WordPress fetch_feed and SimplePie as the built-in RSS method.
* Log RSS retrieval failures separately from calendar connection challenges.

= 0.3.0 =
* Add a Settings > USCCB Readings administration screen.
* Allow administrators to choose the daily refresh time in the site timezone.
* Add safe reload, clear-and-reload, cache coverage, and schedule controls.
* Retain and display the 50 most recent non-sensitive diagnostic events.

= 0.2.0 =
* Cache eight calendar dates with a once-daily background refresh.
* Preserve selector, Vigil, and daytime Mass records as separate entries.
* Recognize Night and Dawn Mass variants without inventing parish-specific times.
* Add liturgical colors, lectionary numbers, and reading citations.
