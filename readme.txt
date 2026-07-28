=== USCCB Today’s Readings ===
Requires at least: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Provides a dynamic WordPress block backed by a persistent cache of the USCCB
calendar and RSS feed.

The cache covers today through seven days from today, inclusive. Calendar days,
not feed-item counts, determine the window. Multiple records on one date are
retained individually with their liturgical color and reading citations.

Vigil Masses keep the following celebration date while being filed under the
actual civil date of the Mass (the preceding late afternoon or evening).

== Changelog ==

= 0.2.0 =
* Cache eight calendar dates with a once-daily background refresh.
* Preserve selector, Vigil, and daytime Mass records as separate entries.
* Recognize Night and Dawn Mass variants without inventing parish-specific times.
* Add liturgical colors, lectionary numbers, and reading citations.
