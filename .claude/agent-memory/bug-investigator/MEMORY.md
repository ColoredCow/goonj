# Bug Investigator Agent Memory

## Codebase Patterns

### Service Class Architecture
- All service classes extend `Civi\Core\Service\AutoSubscriber` and implement `getSubscribedEvents()`
- Located in `wp-content/civi-extensions/goonjcustom/Civi/`
- Use static methods for CiviCRM hook handlers
- Common hooks: `hook_civicrm_pre`, `hook_civicrm_post`, `hook_civicrm_custom`, `civi.afform.submit`

### Entity Subtype Architecture
- ECK entity type: `Collection_Camp` with multiple subtypes (`Collection_Camp`, `Dropping_Center`, `Institution_Collection_Camp`, etc.)
- Subtype checked via `CollectionSource` trait's `getEntitySubtypeName()` and `isCurrentSubtype()`
- Key custom groups: `Collection_Camp_Core_Details`, `Collection_Camp_Intent_Details`

### Known Code Patterns to Watch
- Bitwise AND (`&`) used instead of logical AND (`&&`) in some services — potential logic bugs
- `->execute()->single()` used in places where `->execute()->first()` is safer (crashes on 0 results)
- Multiple `error_log()` calls should be `\Civi::log()` — may hide errors in production

### Issue Tracking
- Issues in `ColoredCow/goonj-crm`, code in `ColoredCow/goonj`
- Always use `--repo ColoredCow/goonj-crm` for gh issue commands
