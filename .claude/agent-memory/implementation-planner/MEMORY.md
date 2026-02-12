# Implementation Planner Agent Memory

## Codebase Patterns

### Service Class Pattern
- All service classes extend `Civi\Core\Service\AutoSubscriber` and implement `getSubscribedEvents()`
- Located in `wp-content/civi-extensions/goonjcustom/Civi/`
- Use static methods for CiviCRM hook handlers
- Common hooks: `hook_civicrm_pre`, `hook_civicrm_post`, `hook_civicrm_custom`, `civi.afform.submit`

### Collection Camp Entity Architecture
- ECK entity type: `Collection_Camp` with multiple subtypes (`Collection_Camp`, `Dropping_Center`, `Institution_Collection_Camp`, etc.)
- Subtype checked via `CollectionSource` trait's `getEntitySubtypeName()` and `isCurrentSubtype()`
- Key custom groups: `Collection_Camp_Core_Details`, `Collection_Camp_Intent_Details`
- Initiator contact stored in `Collection_Camp_Core_Details.Contact_Id`
- Induction link stored in `Collection_Camp_Intent_Details.Initiator_Induction_Id`

### Common Utility Locations
- `Civi/HelperService.php` - Only has `getDefaultFromEmail()` currently
- `Civi/Traits/CollectionSource.php` - Shared logic for all collection source subtypes (ACL, status checks, code generation)
- `Civi/Traits/QrCodeable.php` - QR code generation

### Induction Flow
- `InductionService` creates Induction activities when Volunteer contacts are created or transitioned
- Flow: Contact created -> Address created (with state) -> `createInduction()` -> Activity created
- Induction linked to camp via `linkInductionWithCollectionCamp` (hook_civicrm_custom)

### Known Code Issues (as of 2026-02-12)
- Bitwise AND (`&`) used instead of logical AND (`&&`) in InductionService lines 471, 868
- `->execute()->single()` used where `->execute()->first()` is safer (crashes on 0 results)
- Multiple `error_log()` calls should be `\Civi::log()`

### CLI Scripts
- Located in `wp-content/civi-extensions/goonjcustom/cli/`
- Run with `cv scr <script-name>.php`

### E2E Tests
- Playwright tests in `playwright/e2e/`
- Page objects in `playwright/pages/`
- Key test utilities in `playwright/utils.js`
- Config in `playwright.config.js`

### Issue Tracking
- Issues in `ColoredCow/goonj-crm`, code in `ColoredCow/goonj`
- Always use `--repo ColoredCow/goonj-crm` for gh issue commands
