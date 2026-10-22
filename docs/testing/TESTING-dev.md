# ActualTime — developer testing record
<!-- testing-dev-format: 1 -->

Cumulative regression record for developers. Append new entries; keep the old ones. The human
acceptance checklist is [`QA-TESTING.html`](QA-TESTING.html). Neither file ships in the release
package (`tools/make_release.sh` drops `docs/`).

## Running the PHPUnit suite

The suite needs an installed GLPI 12 (database included) with the plugin installed and
activated. `composer install` in the plugin alone is not enough. The bootstrap runs against
whatever `config/config_db.php` points to, inside a transaction rolled back after every test,
so **always use a disposable database**, never a development instance with real data.

Same images as the shared CI (`glpi-project/plugin-ci-workflows`):

```bash
docker network create at-net
docker run -d --name at-db --network at-net --network-alias db --shm-size=1g \
  -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=yes -e MYSQL_DATABASE=glpi \
  ghcr.io/glpi-project/githubactions-mariadb:11.4
docker run -d --name at-glpi --network at-net \
  ghcr.io/glpi-project/githubactions-glpi-apache:php-8.4-glpi-12.0.x tail -f /dev/null

# copy the plugin (no bind mount: www-data must be able to write vendor/)
tar -C .. --exclude=actualtime/vendor -cf - actualtime | docker exec -i -u root at-glpi tar -C /var/www/glpi/plugins -xf -
docker exec -u root at-glpi chown -R www-data:www-data /var/www/glpi/plugins/actualtime
docker exec -u www-data -w /var/www/glpi/plugins/actualtime at-glpi composer install

docker exec -u www-data -w /var/www/glpi at-glpi bash -lc '
  bin/console database:install --no-interaction --db-host=db --db-name=glpi --db-user=root --db-password="" --strict-configuration &&
  bin/console plugin:install --no-interaction --username=glpi actualtime &&
  bin/console plugin:activate --no-interaction actualtime'

docker exec -u www-data -w /var/www/glpi/plugins/actualtime at-glpi ../../vendor/bin/phpunit --display-incomplete

docker rm -f at-glpi at-db && docker network rm at-net
```

Notes:

- PHPUnit is GLPI's (`../../vendor/bin/phpunit`), not the plugin's. `tests/bootstrap.php` loads only
  GLPI's autoloader. The plugin's `vendor/` holds dev tools (symfony/console…) that would
  redeclare core classes. GLPI registers the plugin's `src/` itself.
- `GLPI_ROOT` overrides the GLPI location (default `../..`).
- If the plugin is not active, the bootstrap installs and activates it.
- The MariaDB image keeps its data in `/dev/shm`: without `--shm-size=1g` it dies at start-up.
- Schema steps (DDL) cannot be tested inside the per-test transaction: MariaDB commits implicitly
  and GLPI throws. Install/uninstall is covered by the CI install cycle; migration *data* steps are
  invoked directly (`MigrationTest`).

## Test fixtures (`tests/ActualtimeTestCase.php`)

Every test runs in a DB transaction and starts as the super-admin `glpi`. A super-admin session
makes every `can*()` and entity check pass, so access tests must switch user:

- `asUser($login, fn)`: run code as another user and always come back to `glpi`.
- `createUser($login, $profile, $entity, $recursive)`: the user gets **only** that profile
  (`_profiles_id` on `User::add()`). Without it core also grants the default Self-Service
  profile and the session opens on Self-Service.
- `createEntity()`, `grantPluginRights($profile)`, `setConfig([...])`.
- `createTicketWithTask($tech)`: the technician is also assigned to the ticket.
- `backdateOpenTimer()`: gives a running segment a duration without sleeping.

## Regression record

### 5.0.0-beta.1 — PHPUnit suite created (security re-audit, 2026-10-22)

Requirement: GLPI 12 port + 4.1.3 security review fixes (Teclib, 5 HIGH) + re-audit
`docs/findings/01-security-audit-2026-10-22-full.md` (local, not tracked).

**Unit (`tests/Unit`)**

- [x] `ItemtypeAllowlistTest`: only TicketTask, ChangeTask, ProblemTask and ProjectTask are
  accepted. `User`, `Ticket`, the plugin class, lower case, a leading backslash, `''`, `null`,
  int and array are rejected, and `getAuthorizedTask()` returns `null` for them and for ids
  `0`, `-1`, `'1 OR 1=1'`, `[]`. (4.1.3 HIGH-1)
- [x] `ConfigDefaultsTest`: without a config row every option is off; values come from the
  row; the helpdesk display is opt-in; settings require re-authentication (GLPI 12 ReAuth).

**Integration (`tests/Integration`)**

- [x] `TimerLifecycleTest`: start → pause → restart → stop; one segment per run; stop marks the
  task done; "automatically update the duration" rounds to the time step; one running timer
  per user; a done task cannot start; only the assigned technician can start; ProjectTask
  updates the effective duration; the segment tooltip shows the duration.
- [x] `AccessControlTest` (restricted users, two entities):
  - a Technician of another entity cannot start/pause/stop, read or modify the segments (4.1.3 HIGH-1/2); a Technician of the same entity can (positive control);
  - the AUTO origin still rejects a non-task itemtype;
  - segments cannot be modified on a closed ticket;
  - Self-Service gets no plugin right even when granted on its profile;
  - the running-timers list, the planning (with a user added to someone else's filters) and the dashboard cards hide other entities;
  - `testTimerRowsCannotBeWrittenFromAnotherEntity` is **incomplete** while SEC-01 is open (core's generic routes write timer rows). It turns into a pass once `Task` denies generic writes.
- [x] `OutputEscapingTest`: a technician whose name is `<img src=x onerror=alert(101)>`, on a
  ticket named `<svg onload=alert(102)>`. The payload is escaped in the running list, the
  modify-timers modal, the segment tooltip, the ticket statistics and the planning (4.1.3
  HIGH-3/4/5). Each test also asserts the escaped text is present (positive control).
- [x] `HooksTest`: marking the task done stops its timer; reassigning it pauses it; adding a
  solution stops it; purging the task deletes its segments; deleting the ticket stops the
  timer; autostart on task creation.
- [x] `MigrationTest`: the 4.x class names resolve (`PluginActualtimeTask`…); the misspelled
  dashboard card key is migrated (idempotent); planning filters saved under the 4.x class
  name are moved to the new key without overwriting an existing one (idempotent).

Last run: 2026-10-22, CI image GLPI 12.0.x / PHP 8.4.26 / MariaDB 11.4, PHPUnit 12.5.37 —
`Tests: 57, Assertions: 345, Incomplete: 1` (the incomplete one is SEC-01, expected).

### Outside the suite

Dynamic batteries used for the 2026-10-22 audit, against GLPI 12.0.0-rc3 in strict mode
(`GLPI_STRICT_ENV`). Kept in the developer's local plugin memory, not in the repo:

- [x] HTTP regression battery (T1–T3), browser battery (16 checks, Playwright). 2026-10-22:
  HTTP 99/102 (the 3 non-OK lines are one harness ordering issue, T1.1, plugin found already
  inactive), browser 16/16.
- [x] Security battery T20–T24: stored XSS in every view, Self-Service against 12 endpoints,
  Profile tab on a helpdesk profile, planning with an arbitrary user, cross-site POST → 403,
  no write via GET, array-shaped parameters rejected.
- [ ] T30 (SEC-01): as a Technician of another entity, `/plugins/actualtime/front/task.form.php`
  GET/add/update/purge and planning `update_event_times`/`delete_event` must be denied.
  **Currently all succeed: open finding.**

### 5.0.0-beta.2 — security fixes SEC-01 to SEC-09 (2026-10-22)

Requirement: audit `docs/findings/01-security-audit-2026-10-22-full.md`, findings SEC-01 to SEC-09
(SEC-10, Reports SQL, left out on purpose). Every denial below has a positive control: the user who
should still see or do the thing still does.

**PHPUnit**

- [x] `AccessControlTest::testTimerRowsCannotBeWrittenFromAnotherEntity` (SEC-01): now a strict
  regression test (fails, no longer *incomplete*, if `Task` lets another entity create, read,
  update or purge timer rows).
- [x] `AccessControlTest::testTimerRowsAreNotListedBySearch` (SEC-01, read side): GLPI's search of
  timer rows returns nothing, for another entity and for the super-admin; a ticket search with the
  ActualTime column 7000 (joining the timer table) still returns the ticket.
- [x] `PrivacyHardeningTest` (SEC-02 to SEC-08). Two Technicians of the same entity; setUp removes
  task SEEPRIVATE from the profile (the stock GLPI 12 Technician has it) and keeps UPDATEALL:
  - running list: another user's private task hidden, the owner still sees it, public tasks still listed;
  - `end` without running timer: a user who may close but not read a private task gets no
    `segment`/`time`/`task_time` and the task is still closed; the assigned technician gets them;
  - statistics: per-technician row of a private task hidden from others, shown to the owner, public tasks listed;
  - the 6 dashboard cards (data provider): private tasks of others excluded (owner still counted);
    tickets in the trash excluded; empty without Ticket right; with Ticket READMY only the tickets
    the reader is an actor of, and being requester **and** observer of a ticket gives the same
    totals as a READALL reader (no row duplication);
  - Profile tab: none on Self-Service, rendered on Technician;
  - `startTimer/pauseTimer/stopTimer` default origin is `WEB`;
  - modify-timers modal renders nothing for another entity, renders for the owner.

Last run: 2026-10-22, CI image GLPI 12.0.x / PHP 8.4.26 / MariaDB 11.4, PHPUnit 12.5.37 —
`OK (85 tests, 796 assertions)`.

**Outside the suite** (same local batteries, GLPI 12.0.0-rc3)

- [x] HTTP t1 15/15, t3 47/47, t5 7/7 (T30.1–T30.6: generic form 403, list and `ajax/search.php`
  with no rows, add/update/purge 403, planning `update_event_times`/`delete_event` without effect,
  plugin timer still works for the assigned technician), browser 16/16.
- [x] HTTP t2 35 OK + 3 LOG: the log lines are `inc/includes.php` deprecations raised by
  `plugins/gappextended/ajax/timer.php:37` (GappExtended's polling), not by this plugin.
  T7.*.count now checks that the removed `count` action answers 400 (SEC-09).
- [x] HTTP t4 20 OK + T21.1: known false positive, the ticket name appears only inside the JSON
  of core's actors component in a `<script>` block, never outside it. T22.99: the Profile tab
  offers nothing on Self-Service (SEC-06).
- [x] CI preflight (GLPI 12.0.x, PHP 8.4): PHPCS, PHP-CS-Fixer, PHPStan, TwigCS, lint, Trivy,
  install/activate/uninstall without residual tables: PASS. Gitleaks: 5 known false positives in
  `tools/phpstan/phpstan.phar`.
