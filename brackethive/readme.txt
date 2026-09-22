=== Brackethive Tournament Manager ===
Contributors: cparfait
Tags: tournament, esports, bracket, competition, leaderboard
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 2.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run an esports tournament from WordPress: 2 to 64 teams, brackets, schedule, sign-ups and a public page that updates itself.

== Description ==

Brackethive turns a WordPress site into the scoreboard of an esports event. It generates the bracket, propagates the results you enter, and publishes everything on a page that players follow from their phones.

The plugin was written for a 16-team tournament played on 8 gaming stations. It now handles several tournaments per site, in three formats, with or without public sign-ups.

**Available in English and French**, and translatable into any language. A setting lets you pick the language of the plugin independently from the site language.

= Formats and bracket =

* **Single elimination**, **double elimination** (losers bracket, grand final, optional bracket reset) and **group stage** followed by a final bracket.
* **2 to 64 teams.** When the count is not a power of two, the top seeds get a bye in the first round. Byes take no time slot and no station.
* **Standard seeding.** For 16 teams: 1-16, 8-9, 4-13, 5-12, 2-15, 7-10, 3-14, 6-11.
* **Best-of per round**: BO1, BO3 or BO5, entered game by game.
* **Cross-group qualification**: two teams from the same group never meet in the first round of the final bracket, and group winners cannot meet before the final.
* **Third place match** and **overall ranking**, from first to last place.

= Running the event =

* The winner moves to the next round automatically, and the loser drops to the losers bracket in double elimination.
* Correcting a result **resets everything that depended on it**, so a later round never rests on a stale score.
* Draws, tied games and extra games past the deciding one are **refused with an explicit message**.
* **Computed schedule** per round, accounting for simultaneous matches, games and breaks. Times you adjust by hand are preserved.
* The public page **refreshes on its own**, pauses when the tab is in the background, and backs off when the network drops.

= Sign-ups and sharing =

* Optional **public form**, complete or reduced to team, nickname, e-mail and phone.
* Entries arrive **pending approval** and stay out of the bracket until validated.
* **Acknowledgement e-mail** to the captain and alert to the organisers.
* **QR codes** for the tournament page and the sign-up page, downloadable as PNG or SVG, computed by your own server.
* **Dedicated sign-up page** created in one click.

= Administration =

* **Four-step wizard** with a summary and an estimated finish time.
* **Several tournaments** per site, each with its own page, teams and settings.
* **Duplicate** a tournament to prepare the next edition.
* **JSON backup and restore**, transactional.
* **Built-in preview** at desktop, tablet and phone widths.

= Privacy =

The sign-up form collects the team name, the captain name, an e-mail address, a phone number and optionally the player nicknames.

The captain name is shown publicly on the teams page, and the players too if you enable that option. The e-mail address and the phone number are never shown. All data stays on your server: the plugin contacts no external service. Uninstalling the plugin deletes nothing unless you tick the corresponding option first.

== Installation ==

1. Upload the `brackethive` folder to `wp-content/plugins/`, or install the ZIP file from Plugins > Add New.
2. Activate the plugin. A **Tournaments** menu appears.
3. Go to **Tournaments > New tournament** and follow the four-step wizard.
4. Add the teams and give each one a position, or use the random draw.
5. The tournament page is created automatically. You can also place the `[brackethive_tournoi]` shortcode in any page.

== Frequently Asked Questions ==

= The tournament pages return a 404 error =

Open Settings > Permalinks and click Save without changing anything. WordPress regenerates its rewrite rules.

= Where is the tournament page? =

Tournaments are a custom post type, so they are listed under **Tournaments > All tournaments**, not under Pages. The dashboard shows the address of every public page in a "Public links" block.

= Sign-up e-mails never arrive =

Use the test button in **Tournaments > Plugin**. If it reports success but nothing arrives, your host does not relay mail: install an SMTP plugin.

= Can I show the bracket somewhere else? =

Yes. Create a page, paste the shortcode you need, then publish it. Every shortcode accepts a `tournoi="slug"` attribute to target a specific tournament.

= What happens if I change the format during the event? =

The bracket is regenerated, and matches that no longer exist in the new format are deleted along with their scores. Export a backup first.

= A group has no qualified team =

Qualified teams are only decided once every match of the group is validated. In manual mode, also check that no group has fewer than two validated teams.

== Shortcodes ==

Every shortcode except `[brackethive_tournois]` accepts `tournoi="slug"` (alias `tournament`, a numeric ID also works). Without it, the shortcode shows the tournament of the current page, otherwise the default tournament of the site.

* `[brackethive_tournoi]` - full tabbed page. Accepts `fit="width"`.
* `[brackethive_tournois]` - list of every published tournament of the site.
* `[brackethive_tableau]` - the bracket alone. `header="no"` hides the title banner; `fit="width"` scales to width only.
* `[brackethive_poules]` - group standings.
* `[brackethive_classement]` - overall ranking.
* `[brackethive_planning]` - schedule.
* `[brackethive_resultats]` - result sheet.
* `[brackethive_equipes]` - registered teams. `players="yes"` shows the line-ups.
* `[brackethive_reglement]` - rules.
* `[brackethive_organisation]` - staff needed.
* `[brackethive_checklist]` - checklist before opening.
* `[brackethive_inscription]` - sign-up form. `simple="yes"` for a reduced form.

Example: `[brackethive_tableau tournoi="we-game-2026" header="no" fit="width"]`

== REST API ==

Two public read-only routes, for a display screen or an external dashboard.

* `GET /wp-json/brackethive/v1/state` - the full state of the tournament as JSON.
* `GET /wp-json/brackethive/v1/render?view=bracket` - the HTML of one view.

Both accept a `tournament` parameter. Draft, private and page-less tournaments are never exposed to visitors.

== Backup ==

Tournaments > Dashboard > Backup exports a JSON file with the teams, matches, scores and games of the selected tournament, and restores it. The restore is transactional: on error nothing is changed.

Uninstalling the plugin keeps all data by default. A setting under Tournaments > Plugin enables a full cleanup instead.

== Screenshots ==

1. The bracket of a 16-team tournament in progress, on the public page. It refreshes on its own during the event.
2. The four-step wizard that creates a tournament, with a summary and an estimated finish time.
3. The dashboard: public links, downloadable QR codes and a preview of the bracket.
4. The schedule, computed from the number of stations, simultaneous matches and breaks.
5. The result sheet, ready to print.

== Changelog ==

= 2.6.0 =
* The plugin is renamed **Brackethive Tournament Manager**. The previous name borrowed a registered trademark, which the plugin directory does not allow.
* Everything the plugin declares or stores is now prefixed `brackethive_`: classes, constants, options, database tables, post meta, shortcodes and script handles.
* Existing sites keep their data. Tables, settings, tournaments and sign-up pages are renamed automatically on the first page load after the update, and the former `[wegame_*]` shortcodes keep working, so published pages are not affected.
* Fixed: the plugin settings screen raised a fatal error in the package distributed through the plugin directory, where the self-hosted update module is absent.
* Styles and scripts of the preview screen now go through the WordPress enqueue API.
* Compiled translation files are no longer shipped in the directory package; translations come from translate.wordpress.org.
* The migration copies rows instead of renaming tables, so it also works on the official SQLite integration.

= 2.5.0 =
* The plugin is now fully translatable. Source strings are in English and a complete French translation ships with the plugin (550 strings).
* New **Language** setting: follow the site language, English, or any installed translation. A notice after activation points to it.
* Icon and banner for the plugin directory.
* Passes the official Plugin Check with no errors and no warnings.

= 2.4.3 =
* Fixed: QR codes did not appear. They are now computed on the server and shown as inline SVG, so a caching plugin that bundles or defers scripts can no longer hide them.
* QR codes download as PNG or SVG.

= 2.4.2 =
* Downloadable QR codes for the tournament page and the sign-up page.
* One-click creation of a dedicated sign-up page.
* Copy button next to every address.
* The shortcode section explains how to create and publish a page.

= 2.4.1 =
* "No dedicated page" option, for sites that prefer their own pages built with shortcodes.
* Public links block on the dashboard.
* Teams screen: the add form sits above the list.
* The theme page title is cleared on tournament pages, whatever the theme.

= 2.4.0 =
* Four-step creation wizard, with a summary and an estimated finish time.
* "Next steps" panel on the dashboard.
* Menu reorganised for multiple tournaments; danger zone moved to the tournament settings.
* Reduced sign-up form: `[brackethive_inscription simple="yes"]`.
* E-mails: acknowledgement to the captain, alert to the organisers, test button.

= 2.3.2 =
* Fixed: with double elimination, the losers bracket stayed empty whenever the team count was not a power of two.
* Fixed: uneven groups produced matches with a single team and blocked qualification.
* Fixed: the "bracket reset" option was never saved.
* Fixed: correcting a result did not always invalidate the next match.
* Fixed: two-group crossing; group winners no longer meet before the final.
* Fixed: the overall ranking takes the second grand final into account.
* Fixed: losers bracket and third place match put back in playing order in the schedule.
* Fixed: tournament pages returned a 404 right after activation.
* Security: draft and private tournaments are no longer visible publicly.
* Security: the update manifest must be served over HTTPS; optional SHA-256 checksum of the package.
* Backup: transactional restore, remapped identifiers, manual groups included.
* Sign-up: client IP detected behind a proxy, consent checked server-side.

= 2.3.1 =
* Fixed: with 2 groups and 2 qualified teams, the first round of the final bracket could pit two teams from the same group against each other.

= 2.3.0 =
* Up to 64 teams, with a round of 64 and its own best-of setting.
* Manual group composition, alongside the default snake seeding.
* Optional second grand final ("bracket reset") in double elimination.

= 2.2.1 =
* Overall ranking of the tournament, from first to last place, whatever the format.

= 2.2.0 =
* Variable formats from 2 to 32 teams, with automatic byes.
* Double elimination with a losers bracket.
* Group stage with automatic standings.
* Best-of setting per round, computed schedule.

= 2.0.0 =
* Multiple tournaments: each tournament is a WordPress content item with its own page and URL.
* Duplication, third place match, best-of per round.
* Automatic migration of existing data.

= 1.0.0 =
* First release: 16-team single elimination bracket, schedule, results, public page.

== Upgrade Notice ==

= 2.6.0 =
The plugin changes name. Your tournaments, teams, results and settings are migrated automatically, and pages using the former shortcodes keep working.

= 2.4.3 =
Fixes QR codes that could stay invisible when a caching plugin bundles or defers scripts.
