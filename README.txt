=== Site Speed Test - SpeedGuard === 
Contributors: sabrinazeidan
Tags: speed, core web vitals, pagespeed, performance, optimization
Requires at least: 5.8
Tested up to: 6.5.5
Stable tag: 2.0
Requires PHP: 7.3.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tracks Core Web Vitals for you. Every single day, for free.

== Description ==

<strong>Track Core Web Vitals for the entire website and for individual URLs.
Mobile and Desktop.
Every day.
Automatically.
For free.</strong>

[youtube https://www.youtube.com/watch?v=5Rq3qvySKtI]

No need to guess whether your website performance needs your attention or not - you will get the definite answer in your WordPress Dashboard in a few minutes.

== With SpeedGuard you get: ==

* <strong>Core Web Vitals (LCP, CLS, INP) testing for individual URLs of your website</strong>
* <strong>PageSpeed Insights (LCP, CLS) for the cases if your website doesn't have Core Web Vitals yet</strong>
* <strong>automatic </strong> everyday monitoring
* <strong>both desktop and mobile</strong> testing
* <strong>links to the Google PageSpeed Insights reports (that include CWV on top as well)</strong> which you can pass to the performance engineer to improve your site speed
* <strong>tests are completely automated</strong> -- you don't need to do anything
* <strong>easy to use</strong> — just pick pages of your website that you would like to monitor
* <strong>It's free :)</strong>



== Test performance of any  content in WordPress :==

* Posts
* Pages
* Events
* WooCommerce Products
* any other Custom Post Type
* Archives
* Categories
* Tags
* any other Custom Taxonomy

= Idea Behind =
There is no need to say that performance IS very important.
What's also important -- is to understand whether you have to worry about your website performance or it's doing fine.

Google Core Web Vitals are the metrics that Google uses to measure the user experience on the web (real user experience!).

If your website passes CWV assessment for Origin -- it means that your website is fast enough for the majority of users.
If it doesn't pass -- it means that you have to fix this.
If it does pass but quite a few specific URLs are not passing -- it's a good time to look into those URLs and improve them to prevent the entrire webstie to be marked as failing CWV.

I wanted a simple easy-to-use tool to warn me in case my clients' websites performance has a bad tendency and needs my attention.

I wanted a native WordPress solution, with all information available from the dashboard, simple but still informative, a guard who will do the monitoring every day and ping me, in case something goes wrong.

I have not found one and that's why I've built this plugin.

I'll be happy to know that you find it useful as well -- please, leave a review.

P.S. Note about PageSpeed Insights: you SHOULD always use CWV data in case it is available for your website. In case it is not available (when the website is new and/or doesn't have enough traffic yet) -- use PageSpeed Insights. But you have to remember, that PSI -- are lab tests, it's just an emulation of real users experience. It's better than nothing, of course.
Watch my talk here if you want to understand [Core Web Vitals Mystery](https://www.youtube.com/watch?v=-80yP6sY0Cg) better.


== Screenshots ==
1. HomePage is added after activation
2. Add pages you want to test
3. See Core Web Vitals for the website in general (for Origin)
4. See Core Web Vitals for individual URLs of your website

== Installation ==
= Automatic plugin installation: =
1. Go to Plugins > Add New in your WordPress Admin
2. Search for SpeedGuard plugin
3. Click Install SpeedGuard
4. Activate SpeedGuard
5. You will be redirected to the Tests page and your Homepage will be tested automatically on activation
6. While the test is running, watch the video in the sidebar to make the most of the plugin


= Configuration: =
SpeedGuard is ready to use right after activation.

Tests are run every day automatically by CRON.

There are 2 tests type: Core Web Vitals and PageSpeed Insights. By default, Core Web Vitals is selected. If Google has this data available for your website, it will be used. If not -- you will see the notice, switch to PageSpeed Insights in SpeedGuard -> Settings then.

== Frequently Asked Questions ==

= How tests are performed? =
Starting from version 1.7 SpeedGuard is using [Google PageSpeed Insights API](https://developers.google.com/speed/pagespeed/insights/) which uses [Lighthouse](https://developers.google.com/web/tools/lighthouse) technology to perform tests.
From there we have Core Web Vitals data for specific URLs of your website and for origin. (Same data, that you can see in [Google Search Console](https://search.google.com/search-console/about) under Core Web Vitals section.)

= Do I need Google PageSpeed Insights API key to use SpeedGuard? =
No, you don't. Just add pages you need to test.

= Are the tests results for desktop or mobile users? =
For both. Automatically.

= Is it compatible with WordPress Multisite? =
Use per-site activation.

= Where can I suggest a new feature or report a bug? =
Here, in the support forum.

= Translations =

* English - default, always included


*Note:* No your language yet? You can help to translate this plugin to your language [right from the repository](https://translate.wordpress.org/projects/wp-plugins/speedguard), no extra software needed.


= Credits =
* Thanx to Baboon designs from the Noun Project for the timer icon.

== Changelog ==
= Version 2.0.1 - July 6, 2024 =

* [Fixed] CWV for Origin are "N/A" after the test deletion

= Version 2.0 - July 4, 2024 =

* [New] Added Core Web Vitals (LCP, CLS, INP) metrics
* [New] PageSpeed Insights (LCP, CLS) stays as a backup for the cases if your website doesn't have Core Web Vitals yet
* [New] No API key needed, plugin works right away
* [New] Automatic everyday monitoring by default
* [New] Both desktop and mobile testing by default
* [Improved]  PSI API v5
* [Improved] Overall plugin performance (especially for making/handling requests)
* [Discontinued] Admin Bar widget

= Version 1.8.5 - June 7, 2021 =
* [Fixed] Compatibility with plugins that disable admin notices
* [Fixed] Error on author archive pages

= Version 1.8.4 - March 22, 2021 =
* [Fixed] Error on date archive pages
* [Fixed] Homepage can be added multiple times
* [Fixed] Site's average is not updated properly when tests are deleted
* [Tweak] jQuery independence: all functions use vanilla JS now

= Version 1.8.3 - November 9, 2020 =
* [Fixed] Threshold error (5 minutes + timezone) after WordPress 5.3
* [Fixed] Settings are being reset to defaults
* [Fixed] PHP Warning: Illegal string offset 'displayValue' in Admin bar when test is in progress
* [Fixed] Critical error on custom post type archive page
* [Fixed] Tests for terms pages were not being deleted on uninstall
* [Fixed] Styles and scripts loaded for not logged-in user after version 1.8
* [Fixed] 504 admin-ajax.php error (or inifinite spinning) on bulk retest
* [Tweak] Backward compatibility with PHP 5.6
* [Tweak] Wait time before retesting reduced to 3 minutes

= Version 1.8.2 - Septemeber 9, 2020 =
* Typo fixed

= Version 1.8.1 - Septemeber 9, 2020 =
* [Fixed] Error happened on some installs: Unexpected end of file in ../speedguard/admin/class-speedguard-admin.php on line 403
* [Fixed] Error happened on CPT's pages in wp-admin:  Object of class WP_Error could not be converted to string in  ../speedguard/admin/includes/class.widgets.php on line 80
* [Tweak] REST API Internal + Auth security improved
* [Tweak] Automatically re-test if monitored page is added again

= Version 1.8 - August 10, 2020 =

* [New] Support for archives is added
* [New] Tests results can be sorted now (by time, URL and speed)
* [New] Homepage test is added automatically on plugin activation
* [Tweak] Tests are run with AJAX in the background
* [Tweak] Already guarded items are excluded from autocomplete
* [Tweak] Type-in validation improved
* [Tweak] Settings and Tests links are added to plugin's tab on the Plugins page
* [Fixed] Homepage can't be added if it an archive
* [Fixed] Sanitization type-in doesn't work in all cases
* [Fixed] Upcoming email notification is sent to the old email after it's been updated
* [Fixed] Notice to wait for 5 minutes before next run stays even after 5 minutes passed
* [Fixed] Email report contains a line with no results if the test is in running at the moment

= Version 1.7 =

* As WebPageTest.org stopped providing public API keys, SpeedGuard switched to make tests using [Google PageSpeed Insights API](https://developers.google.com/speed/pagespeed/insights/) which uses [Lighthouse](https://developers.google.com/web/tools/lighthouse) technology.
* Tracked performance metrics is [Largest Contentful Paint](https://web.dev/lcp/)
* Minor bugs fixed

_If you've got working WebPageTest API key and want to keep using it to run tests, you still can use [SpeedGuard version 1.6](https://github.com/sabrina-zeidan/speedguard/releases/tag/v1.6), but mind that it's not going to be supported/updated anytime soon._

= Version 1.6 =
* Performance of external requests improved (tips and API credits)
* Minor bugs fixed

= Version 1.5.1 =
* Typo update


= Version 1.5 =
* WordPress Multisite support (per site activation)
* Choice of connection type
* Choice of location
* Better report email styling
* Minor bugs fixed

= Version 1.4.1 =
* Language packs update

= Version 1.4 =
* Any URLs from current website can be added directly to the input field
* Fully Loaded in reports changed Speed Index to reflect user experience better https://sites.google.com/a/webpagetest.org/docs/using-webpagetest/metrics/speed-index
* Admin-ajax.php is relaced with WP REST API
* WordPress Multisite support is paused in in this version, but will be provided in the next one with better performance-wise solution for the large networks
* Minor bugs fixed

= Version 1.3.1 =
* Minor bugs fixed

= Version 1.3 =
* Pages and custom post types support added.

= Version 1.2.2 =
* Minor bugs fixed, some notes added.

= Version 1.2.1 =
* Language bug fixed.

= Version 1.2 =
* Multisite support added.

= Version 1.1.0 =
* Tests page view updated.

= Version 1.0.0 =
* Initial public release.
