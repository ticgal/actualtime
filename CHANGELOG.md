# Actualtime

## [5.0.0-beta.2] - 2026/10/01
### Security
- Includes the security fixes from 4.1.4
- Planning only shows timers of tasks the user can view
- Dashboard cards and running timers could lose the entity restriction when the user had no active entity
- Saving the plugin settings requires re-authentication, like GLPI setup
- Escape item link and name in the running timer pop-up
### Changed
- Removed CSRF token fields, GLPI 12 validates CSRF with request headers
- JavaScript files registered without the deprecated /public prefix
- Reports restrict entities without the deprecated getEntitiesRestrictRequest()
- Dashboard date filters use bound query values
- TAM, Waypoint and GappExtended integrations support their PSR-4 class names
- Classes moved to src/ with the GlpiPlugin\Actualtime namespace. The PluginActualtime* class names remain as deprecated aliases for other plugins
- Planning filters saved for the old class name keep their color and visibility
- Fixed the key of the "Top 20 % Actualtime usage per day" dashboard card, saved dashboards are updated
- Task timers, "Assign to me" button and autostart switch rendered with Twig templates
- Tabler icons instead of Font Awesome
- Settings are loaded once per request
- Removed table.js, GLPI 12 already shows the statistics dates block on changes and problems
- PHPStan configuration works both locally and in CI. Removed the obsolete atoum tests and Travis configuration
- Unique Composer autoloader suffix: GLPI 12 does not load a plugin whose autoloader class collides with another plugin's
### Fixed
- Settings tab failed to render the form buttons
- Task form in the running timer pop-up failed to render
- Minimum and maximum end dates were never applied when modifying timer segments
- Reports failed when the Reports plugin is not active
- "Assign to me" task button form was malformed and ignored the GLPI base URL
- Starting a timer replaced the main database connection with the read replica
- Starting, pausing or stopping a timer failed when TAM, Waypoint or GappExtended classes were missing
- Settings getters failed when the settings row was missing
- Automatically opening the task with a running timer did not open its form
- Stopping a timer did not mark the task as done in the timeline
- Scheduled task message only showed the year of the date
- "Duration Diff" and settings labels were never translated

## [5.0.0-beta.1] - 2026/09/18
### Added
- GLPI 12 support
### Changed
- Requires GLPI 12.0
- Typed $rightname properties and Glpi\DBAL query classes, required by GLPI 12
- Replaced Html::displayNotFoundError(), removed in GLPI 12
- PHPStan and CI run against GLPI 12
### Fixed
- getActualBegin() returned null against its string return type when no timer was running
- stopTimer() returned no type when called by another user, and failed with a time step of 0
- getSegment() on a modified segment without its source timer
- Usage by day cards failed on MySQL with NO_ZERO_DATE

## [4.1.4] - 2026/09/30
### Security
- Check task rights and entity access on timer start, pause, stop and duration queries
- Check task rights and entity access when viewing or editing past timer segments
- Hide running timers of items the user cannot view
- Escape user, entity, location and item names in timer views, planning and statistics
- Restrict the "less actualtime usage by day" dashboard card and the reports to the user's entities
- Harden date filters in dashboard queries

### Fixed
- Reports failed with an SQL error on the current database schema

## [4.1.3] - 2026/09/09
### Fixed
- Fixed partial session destruction when the password expired.

## [4.1.2] - 2026/09/09
### Fixed
- Require the plugin_actualtime_running right in ajax/running.php, matching the check already enforced in front/running.php

## [4.1.1] - 2026/08/06
### Fixed
- Actualtime Usage Card don't show correct dates
- Problem & Changes statistics view is centered

## [4.1.0] - 2026/07/21
### Added
- Deletes tables upon uninstall
### Fixed
- Fix pauseTimer to synchronize actiontime in glpi_tickettasks without closing the task

## [4.0.2] - 2026/06/25
### Fixed
- Update table field to match integration with plugin taskview

## [4.0.1] - 2026/04/14
### Fixed
- Automatic timer
### Changed
- Add history logs to profile rights

## [4.0.0] - 2026/01/22
### Added
- GLPI 11 support

## [3.2.3] - 2025/08/20
### Added
- Search options for plugin integrations

## [3.2.2] - 2025/08/20
### Fixed
- Limit and better control for timer modification

## [3.2.1] - 2025/06/10
### Fixed
- ProjectTask parent key to start the timer

## [3.2.0] - 2025/02/27
### Added
- Actualtimes for Problem tasks, contributed by Gambware

### Fixed
- Task in progress message redirecting to the correct ticket

## [3.1.3] - 2025/01/27
### Fixed
- Conflict with pending reasons with glpi solutions
- Fix ChangeTasks foreign key and integration
- Better control over null values

## 3.1.2 - 06/11/2024
### Bugfixes
- Fix active task alert showing and redirecting to the correct page

## 3.1.1 - 10/10/2024
### Bugfixes
- Fix change tech
- Fix warning
- Fix solution check
- Fix tam check

## 3.1.0 - 30/04/2024
### Features
- Modify the end date of timers
### Bugfixes
- Fix permissions check of the timers running

## 3.0.1 - 15/04/2024
### Features
- Use global functions
### Bugfixes
- Fix field update

## 3.0.0 - 16/02/2024
### Features
- Add compatibility with project task
- Add compatibility with change task
### Bugfixes
- Fix autostart css
- Fix disabled button

## 2.2.0 -15/09/2023
### Features
- Add block timer on different day
- Add block timer on planned task
- Permission to view activated timers
- Filter activated timers by entities
- Show location and assets in activated timers
### Bugfixes
- Fix warning string
- Fix update actiontime

## 2.1.2 - Internal - 05/09/2023
### Features
- Add block timer on different day
- Add block timer on planned task
### Bugfixes
- Fix warning string

## 2.1.1 - Internal - 12/07/2023
### Features
- Permission to view activated timers
- Filter activated timers by entities
- Show location and assets in activated timers
### Bugfixes
- Fix update actiontime

## 2.1.0 - 15/03/2023
### Features
- Show ticket and task id in planning
- Stop timer when ticket delete
- Change pop-up
- Add button with task closed
### Bugfixes
- Fix delete timer when purge task
- Check user login
- Fix widget sql

## 2.0.0 - 12/08/2022
### Features
- Update the time of the task by marking it as done
- UI enhancements
- Drop auto open new task option
### Bugfixes
- Fix sql ticket statistics
