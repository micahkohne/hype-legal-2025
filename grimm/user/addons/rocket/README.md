
Rocket automatically works out which channel entries each page uses, and refreshes its cache whenever those entries are updated in the control panel.

## Benefits:

Dramatically improve your TTFB metric.
Reduce server resource load.

## Potential Pitfalls:

- If you encounter timeouts when channel entries are saved, set 'Update on Save' to no.
- If a page has any dynamic content, you will need to load it in via AJAX as the HTML output will load from the cache.
- If your site has plugins which work with channel entries, Rocket might need some extra work to be compatible (currently supports: Low Reorder).
- If your site is using HTTPAUTH, Rocket will be unable to create cache files

## Notes

**Upgrading from 1.x to 2.x** - please uninstall and delete 1.x before installing 2.x, they are quite different in structure and a straight upgrade isn't supported.

Cache is purged whenever the addon settings are saved.
Rocket works with Apache/NGINX. Rocket does not work with the PHP built in server, because 2 request threads are required.

## More

Go even faster, add the following code to the top of index.php for super fast GET requests. Update the path as required to match your EE installation.
Note - this code ignores the 'Website online?' control panel setting. If a cache exists for a page it will be shown even if the site is 'offline'.

```
require_once('system/user/addons/rocket/snippet.rocket.php');
```

## Changelog
2.3.4 - 2024-10-17
 - SQL for the learn feature improved

2.3.3 - 2023-10-10
 - render_url was not ignoring POST requests

2.3.2 - 2023-08-18
 - various bugfixes with assistance from https://jcogs.net/ 💜

2.3.1 - 2023-05-15
- fix shared.php file lines 85 - 106

2.3.0 - 2023-04-20
- support MSM by including hostname in the cache folder structure
- fix db query overload by adding the 'learn' tag

2.2.0 - 2022-06-24
- recording and automatic purging of pages which list channel entries

2.1.5 - 2022-02-26
- bug, cache wasn't cleared when saving a channel entry

2.1.4 - 2022-02-16
- bug where cache file tried to have the same name as folders

2.1.3 - 2022-01-24
- utilise EE caching to reduce db requests

2.1.2 - 2022-01-21
- bug stopped cache clear from working

2.1.1 - 2022-01-18
- Typo in settings field description

2.1.0 - 2022-01-13
- Change cache storage structure

2.0.0 - 2021-12-03
- Querystring exceptions
- Re-factor as a module so permissions can be assigned

1.4.2 - 2021-04-23
- Fix error notification on install (EE6)

1.4.1 - 2020-11-07
- Jump menu commands

1.4 - 2020-11-05
- support EE6
- add ability to toggle cache minification

1.3.2 - 2019-08-05
- tidy up the paths table whenever entries are saved
- optimise settings loading
- 'update on save' toggle

1.3.1 - 2019-07-30
- use a URL parameter for cache requests instead of enable/disable
- better regex pattern for CSRF matching
- the hook to update cache on channel entry save was missing
- **the index.php snippet has been updated**

1.3.0 - 2019-07-18
- fix tables not being dropped when uninstalled
- fix wildcard matching
- new mode option to include or exclude items in the list

1.2.1 - 2019-07-17
- Settings screen UX improvements

1.2.0 - 2019-07-11
- support pages with CSRF tokens (e.g. forms)
- enable the update button on the add-on list page

1.1.1 - 2019-07-10
- suppress error so that 404 pages don't cause problems

1.1.0 - 2019-07-09
- allow form posts to work
- better way of including the setup file
- thanks to @litzinger for the assistance
- general tidy up
- toggle to bypass the cache if a member is logged in

1.0.0 - 2019-07-08
 - initial release

## Credits

[Icon by Freepik](https://www.freepik.com/) from [Flaticon](https://www.flaticon.com) is licensed by [CC 3.0 BY](http://creativecommons.org/licenses/by/3.0/)
