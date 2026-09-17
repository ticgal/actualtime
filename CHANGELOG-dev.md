# Actualtime — developer changelog

Started at 4.1.3; earlier history is in `CHANGELOG.md`.

## [Unreleased]
- `PluginActualtimeTask::isAllowedItemtype()` enforced in `startTimer()`, `pauseTimer()`, `stopTimer()`, `totalEndTime()` and `ajax/timer.php`.
- `PluginActualtimeSourcetimer::canModify()` requires `haveAccessToEntity()` and `can(READ)` on the parent item.
- `front/sourcetimer.form.php` checks each posted row belongs to the request item.
- `htmlescape()` on name accessors in `running.class.php`, `sourcetimer.class.php`, `task.class.php`.
