# ByteBack M2 handoff guide

This document is the M2 handoff for M1, M3, and M4. M2 owns the item reporting flow, the student feed with search, the My Reports page, the item details modal, and the reference data (categories and locations). It describes the module **as built**.

---

## 1) M2 file list

| Layer | File | Purpose |
|---|---|---|
| Database | `database/02_reference.sql` | `categories` and `locations` tables with sample rows |
| Database | `database/03_items.sql` | `items` table, FULLTEXT index, 13 sample items across statuses |
| Database | `database/06_view.sql` | `v_approved_feed` view (Approved items only) |
| Database | `database/queries/q2_pending_reports.sql` | Pending reports query |
| Model | `app/models/Item.php` | `item_feed()`, `item_my_reports()`, `item_find()` |
| Model | `app/models/Category.php` | `category_all()` |
| Model | `app/models/Location.php` | `location_all()` |
| Handler | `app/handlers/item_submit_handler.php` | validates and processes report submissions |
| Helper | `app/helpers/search.php` | Keyword cleaning, synonym expansion, LIKE escaping |
| Helper | `app/helpers/upload.php` | Serves uploaded photos to authorized users |
| Page | `public/index.php` | Student feed with tabs, search, and filters |
| Page | `public/student/report_new.php` | Lost/Found report form |
| Page | `public/student/my_reports.php` | The student's own reports and statuses |
| Page | `public/student/item_view.php` | Item details modal fragment (reused by Feed and My Reports) |
| Assets | `public/assets/css/items.css` | M2 styles |
| Assets | `public/assets/js/items.js` | Modal, report form validation, submit confirmation, report preview, required "Other" location and feed tab search persistence |
| Uploads | `uploads/*` | Item photos |

---

## 2) Database

### Run order

Run from the `database/` folder, after M1's files:

1. `02_reference.sql`
2. `03_items.sql`
3. `06_view.sql`

`03_items.sql` has foreign keys to `users`, `categories`, and `locations`, so `01` and `02` must run first. `06_view.sql` needs `items`, `categories`, `locations`, and `users`.

### `categories`

| Column | Type |
|---|---|
| `category_id` | INT UNSIGNED, PK, auto-increment |
| `category_name` | VARCHAR(100), UNIQUE |

Sample rows: Electronics, Bags & Wallets, Clothing & Accessories, School Supplies, IDs & Cards, Personal Items, Sports Equipment, Documents, Keys & Keychains, Other.

### `locations`

| Column | Type |
|---|---|
| `location_id` | INT UNSIGNED, PK, auto-increment |
| `location_name` | VARCHAR(100), UNIQUE |

Sample rows cover BU campus spots (BU Oval, BU Library, CS Canteen, and so on), the other campuses, and a final **`Other`** row. The code treats the exact name `Other` as special (see section 4).

### `items`

| Column | Type | Notes |
|---|---|---|
| `item_id` | INTEGER, PK, auto-increment | |
| `user_id` | INTEGER, NOT NULL | FK to `users.user_id` (the reporter) |
| `item_type` | ENUM('Lost','Found') | |
| `item_name` | VARCHAR(100) | |
| `category_id` | INTEGER UNSIGNED | FK to `categories` |
| `location_id` | INTEGER UNSIGNED | FK to `locations` |
| `custom_location` | VARCHAR(255), NULL | Specific spot, or the location text when `Other` is chosen |
| `event_date` | DATE | Date lost or found |
| `photo_path` | VARCHAR(255), NULL | Stored as `uploads/<filename>` |
| `description` | TEXT | |
| `status` | ENUM('Pending','Approved','Rejected','Matched','Claimed','Returned') | Default `Pending` |
| `reviewed_by` | INT, NULL | FK to `users.user_id` (admin) |
| `review_remarks` | TEXT, NULL | Admin note, shown to the student when Rejected |
| `reviewed_at` | DATETIME, NULL | |
| `created_at` | DATETIME | Default `CURRENT_TIMESTAMP` |

Indexes: `idx_items_status`, `idx_items_user`, and `FULLTEXT ft_items_search (item_name, description)`.

The seed data includes items in every status (Pending, Approved, Rejected, Claimed, Returned) so other modules can test against them.

### `v_approved_feed` (view)

Returns Approved items only, joined with category, location, and reporter name. Columns: `item_id, item_type, item_name, description, photo_path, event_date, created_at, category_id, category_name, location_id, location_name, location, reporter_name, status`.

`location` is the display string: the custom text alone when the location is `Other`, otherwise `Location - custom spot`.

---

## 3) Model functions (`app/models/`)

All functions take a `PDO $pdo` as their first argument. Functions that take user input use prepared statements only. `category_all` and `location_all` take no input and use a plain query.

| Function | Returns | Description |
|---|---|---|
| `item_feed(PDO, array $f = [])` | `array` | Approved items from `v_approved_feed`. Filters: `q`, `category_id`, `location_id`, `type` (`Lost` or `Found`). `q` is split into keywords and matched with `LIKE` on name, description, and location, with synonym expansion (`helpers/search.php`). With `q`, results are ranked by FULLTEXT relevance on name and description, then newest first |
| `item_my_reports(PDO, int $userId)` | `array` | All of one student's reports, any status, newest first. Returns `item_id`, `item_type`, `item_name`, `event_date`, `status`, `review_remarks`, and `category_name` |
| `item_find(PDO, int $id)` | `?array` | One item with `category_name`, `reporter_name`, and the display `location`. Returns `null` if not found |
| `category_all(PDO)` | `array` | `category_id` and `category_name`, A-Z, with `Other` last |
| `location_all(PDO)` | `array` | `location_id` and `location_name`, A-Z |

Example:

```php
require_once __DIR__ . '/../../app/models/Item.php';

$item = item_find($pdo, 12);          // null if missing
$mine = item_my_reports($pdo, $uid);
$feed = item_feed($pdo, ['q' => 'phone', 'type' => 'Lost']);
```

---

## 4) Pages and behavior

### Student feed: `public/index.php`

- Guests are redirected to `login.php`. Admins are redirected to `admin/dashboard.php`. It does **not** call `redirect_by_role()`, so there is no redirect loop.
- Query string: `type` (`all`, `found`, `lost`), `q` (search text), `category`, `location`.
- Runs its own query for Approved items, newest first. It does not use `item_feed()`. Search matches name, description, location, and category with synonym expansion. Dropdowns come from `category_all()` and `location_all()`.
- Shows a card grid of Approved items. Each card has a type badge, a "Your report" badge for the student's own items, a photo (or a "No photo attached" placeholder), category, location, date, and a **View details** button.
- Shows an empty state, with different text when filters are active.
- Includes the `#itemModal` markup that `items.js` fills from `student/item_view.php`.

### Report form: `public/student/report_new.php`

Fields: Lost/Found, item name, location, specific spot, category, date, description, optional photo.

Server-side validation (`validate_item_report()`, `validate_item_photo()`, `validate_custom_location()`):

- `item_type` must be `Lost` or `Found`.
- `item_name` is required, max 100 characters.
- `category_id` and `location_id` must exist in the database.
- `custom_location` max 100 characters. It is **required** when the location is `Other` (matched by name). Whitespace is collapsed, and an empty value is stored as `NULL` (`normalize_custom_location()`).
- `event_date` must be a valid `Y-m-d` date and not in the future.
- `description` is required, max 1000 characters.
- Photo (optional): max 2 MB, MIME checked with `finfo`, and only JPG, PNG, or WebP.

On success: the file is saved as `uploads/<24 random hex chars>.<ext>`, the row is inserted with status `Pending`, a flash message is set, and the student goes to `my_reports.php`. If the insert fails, the saved file is deleted.

Client-side extras (`items.js`):

- **Preview Report** opens a modal built from the form values, including the chosen photo.
- **Submit** opens a confirmation dialog first. The form blocks double submission.
- Choosing `Other` makes the specific location field required.

The form uses its own CSRF token in `$_SESSION['csrf']` (field name `csrf`), checked with `hash_equals`.

### My Reports: `public/student/my_reports.php`

Table of the student's reports (Item, Type, Category, Date, Status). The item name opens the details modal. Rejected reports show the admin's `review_remarks` as "Admin note".

### Item details modal: `public/student/item_view.php`

A fragment, not a full page. `items.js` fetches `item_view.php?id=<id>` and injects the HTML into `#itemModalBody`. It is reused by the feed and My Reports.

Access rules:

- Not logged in: `401`. Admin: `403`.
- Item missing, or neither public nor owned by the user: `404`.
- "Public" means status `Approved`, `Matched`, `Claimed`, or `Returned`. A student can also open their own items in any status (Pending and Rejected included).
- The JS shows one generic "Could not load this item" message for any of these errors.

It shows the photo, type badge, name, category, location, date, reporter ("Reported by you" for the viewer's own items, otherwise the reporter's name or `Unknown`), and description. Action buttons appear only when the item is `Approved` and was **not** reported by the viewer:

- Found item: **Claim this item** links to `student/claim_new.php?item_id=<id>` (M3's page).
- Lost item: **I found this** links to `student/report_new.php?item_id=<id>`.

### How to reuse the modal on another page

1. Add `$extra_css = [url('assets/css/items.css')];` and `$extra_js = [url('assets/js/items.js')];`.
2. Put this markup inside `<main>`:

```php
<div id="itemModal" class="item-modal" hidden role="dialog" aria-modal="true"
     aria-labelledby="itemModalTitle" data-url="<?= e(url('student/item_view.php')) ?>">
    <div class="item-modal-content">
        <button type="button" class="item-modal-close" id="closeItemModal" aria-label="Close">&times;</button>
        <div id="itemModalBody"></div>
    </div>
</div>
```

3. Add a trigger with a `view-detail` class and a `data-item-id`:

```php
<button type="button" class="btn view-detail" data-item-id="<?= (int)$id ?>">View details</button>
```

The modal closes with the X button, a click outside it, or the Escape key.

---

## 5) Photos and uploads

- Photos live in `uploads/` and are stored in the database as `uploads/<filename>`.
- `uploads/.htaccess` denies all direct web access.
- Pages show photos through `app/helpers/upload.php?f=<filename>`, which:
  - requires login (`403` otherwise),
  - accepts only filenames matching `[A-Za-z0-9_-]+.(jpg|jpeg|png|webp)`,
  - lets admins view any photo, and students view a photo only if the item is `Approved`, `Matched`, `Claimed`, `Returned`, or their own,
  - sends the correct `Content-Type`, `nosniff`, and a private cache header.
- **M3** should reuse this pattern for claim proof, and add its own access rule.

---

## 6) Search helper (`app/helpers/search.php`)

| Function | Description |
|---|---|
| `search_keywords(string $text)` | Lowercases, splits on non-letters and non-digits, drops stopwords (`a, an, the, of, my, and, or, in, on, with`), de-duplicates, keeps at most 5 words |
| `search_expand(string $word)` | Returns the synonym group containing the word, or just the word |
| `search_like(string $word)` | Wraps in `%...%` and escapes `\`, `%`, `_` for `LIKE` |

Synonym groups: phone / cellphone / mobile / smartphone; wallet / purse / billfold; id / identification / card; umbrella / brolly; bag / backpack / knapsack; jacket / coat / windbreaker / hoodie; key / keys / keychain; calculator / calc.

M4 may reuse these for the matching helper. Add new synonyms to `SEARCH_SYNONYMS` and tell M2.

---

## 7) Testing checklist

Log in as a student (for example `rafael.cruz@bicol-u.edu.ph`, password `Password123!`).

- [ ] Feed shows only Approved items. Pending and Rejected items do not appear.
- [ ] All / Found / Lost tabs, category filter, and location filter work, alone and combined.
- [ ] Searching `cellphone` finds the phone items (synonyms).
- [ ] A search or filter with no results shows "No matching items".
- [ ] **View details** opens the modal, and Escape / outside click / X close it.
- [ ] Your own items show the "Your report" badge, and their modal has no **Claim this item** or **I found this** button.
- [ ] Another student's Approved item shows the right button: **Claim this item** (Found) or **I found this** (Lost).
- [ ] Report form rejects: blank name, missing category or location, future date, blank description, photo over 2 MB, non-image file. (An oversized photo may show "could not be uploaded" instead of the 2 MB message.)
- [ ] Choosing `Other` without a specific location shows an error.
- [ ] Preview shows the form values and the chosen photo. 
- [ ] A valid submit creates a `Pending` row and redirects to My Reports with a flash message.
- [ ] My Reports lists the new report as Pending, and a Rejected report shows the admin note.
- [ ] Opening `uploads/<file>` directly in the browser is denied.
- [ ] An Admin visiting `student/item_view.php` gets `403`, and `index.php` redirects to the dashboard.

---