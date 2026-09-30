# Appneck SDK for WordPress plugins

The client library a plugin embeds to talk to Appneck: signed telemetry, consent,
uninstall surveys, announcements and licensing. This is the whole SDK surface —
every endpoint in the `/sdk/v1/*` zone has a client-side helper: registration and
lifecycle, a local event queue with `track()`/`track_error()`, the consent
prompt, the deactivation survey, announcement fetch/display, and licence
activation/validation with a local cache.

Requires PHP 7.2+. No runtime dependencies.

*(If you're working inside the Appneck monorepo rather than consuming this as
a distributed package: this was built in stages, tracked in
`docs/sdk-roadmap.md` §S4. This guide doesn't follow that phase order; it
follows the order you'll actually touch these pieces in as a plugin author.)*

## Contents

- [Quickstart](#quickstart) — under 10 minutes, from nothing to a real event on your dashboard
- [Installing it in a plugin](#installing-it-in-a-plugin) — bundled vs. Composer, in full
- [Version safety](#version-safety-why-the-loader-exists)
- [It will not take down the host site](#it-will-not-take-down-the-host-site) — error handling
- [Lifecycle](#lifecycle) — activation, deactivation, uninstall.php
- [Telemetry](#telemetry) — `track()`, custom events, the heartbeat
- [Consent](#consent) — what's automatic, what you configure, what not to do
- [Deactivation survey](#deactivation-survey) — configured in the Org Panel, not code
- [Announcements](#announcements) — same: authored in the Org Panel, not code
- [Licensing](#licensing) — `is_valid()`, fail-open, and the retry backoff
- [Signing](#signing)
- [Storage](#storage)
- [Filters reference](#filters-reference) — every customization hook this SDK exposes
- [Troubleshooting](#troubleshooting)
- [Known limitations](#known-limitations)
- [Tests](#tests)

---

## Quickstart

The minimal path to a plugin that registers itself and reports a real event,
start to finish. You'll need a product API key and secret from your Appneck
Org Panel (Product → API Keys) before you start.

### 1. Install the SDK

**Composer** (if your plugin can assume it):

```bash
composer require appneck/wordpress-sdk
```

**Bundled** (no Composer — download the SDK and copy its directory into your
plugin, conventionally at `vendor/appneck-sdk/`):

```bash
git clone https://github.com/magepeopleteam/appneck-wordpress-sdk.git vendor/appneck-sdk
```

Or download a zip of a specific tag/release from
[github.com/magepeopleteam/appneck-wordpress-sdk](https://github.com/magepeopleteam/appneck-wordpress-sdk)
if you'd rather not vendor a `.git` directory into your plugin.

Either way, see [Installing it in a plugin](#installing-it-in-a-plugin) below
before you ship — there's one detail (the version-safe loader) that matters
even if you only ever read this Quickstart.

### 2. Wire it into your plugin's main file

```php
<?php
/**
 * Plugin Name: Acme Bookings
 */

// Bundled only — Composer's autoloader already resolves these classes,
// so skip this require if you installed via Composer.
require_once __DIR__ . '/vendor/appneck-sdk/appneck-sdk.php';

// Force the loader to resolve NOW rather than waiting for `plugins_loaded`
// — required here, not optional. See the callout right below this snippet.
appneck_sdk_load_latest();

$GLOBALS['acme_sdk'] = \Appneck\Sdk\Sdk::bootstrap(
	'pk_your_product_key',
	'sk_your_product_secret',
	'https://appneck.com',
	__FILE__          // your plugin's main file
);
```

`Sdk::bootstrap()` wires everything by itself — activation, deactivation, the
registration cron, the consent prompt, the deactivation survey, announcements,
and licensing. There is nothing else to call for any of those. (The one thing
it deliberately does *not* print anywhere is the licence settings panel — see
[Licensing](#licensing).)

**Call this at your plugin's top level, not inside `add_action('plugins_loaded', ...)`
— confirmed by running this exact pattern against real WordPress core, not
assumed.** `register_activation_hook()`/`register_deactivation_hook()` (which
`bootstrap()` calls on your behalf) only work when WordPress sees them during
your plugin file's own synchronous top-level execution: WordPress runs your
plugin file directly, as a one-off include, for exactly that purpose, and
that happens *before* `plugins_loaded` fires for that same request — deferring
the call means the registration happens too late to catch that request's
activation/deactivation event, silently. Verified two ways: activating a
plugin with `bootstrap()` deferred to `plugins_loaded` created no local event
table and scheduled no registration cron event at all — `on_activate()`
never ran; moving the same call to the top level fixed both immediately, no
other change.

This does cost something: `appneck_sdk_load_latest()` forces this copy to
load now rather than waiting for every bundled copy on the site to register
first (see [Version safety](#version-safety-why-the-loader-exists)), so in
the rare case where an *older* copy of this plugin loads before a *newer*
copy bundled by a different, not-yet-loaded plugin, the older one can win
for this one request. In practice this only matters for the activation/
deactivation request itself — by then, every other already-active plugin's
own top-level code (and version registration) has already run earlier in
that same request, so the race is narrow: two brand-new SDK-bundling plugins
being activated for the very first time in the same request. Normal page
loads are unaffected either way, since `bootstrap()`'s other behaviour
(telemetry, consent, announcements) doesn't depend on this timing — only the
activation/deactivation hooks do.

### 3. Add `uninstall.php`

At your plugin's root, next to the main file — **not** inside a subdirectory,
WordPress only looks for it at the top level:

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/appneck-sdk/appneck-sdk.php';

\Appneck\Sdk\Sdk::uninstall(
	'pk_your_product_key',
	'sk_your_product_secret',
	'https://appneck.com'
);
```

Skipping this isn't a silent gap — see [Lifecycle](#lifecycle) for exactly why
`register_uninstall_hook` doesn't work as a substitute.

### 4. Activate the plugin, and confirm registration happened

Activation itself makes **no** network call — it schedules the real
registration for the next page load (see [Lifecycle](#lifecycle) for why). So:

1. Load any `wp-admin` page once (this is what fires WP-Cron in the normal
   case), or force it immediately with WP-CLI:
   ```bash
   wp cron event run appneck_sdk_register
   ```
2. Check your Appneck Org Panel → your product → Installations. Your site
   should appear there, status `active`, within seconds.

If it doesn't show up, pass a `Logger` (see
[Troubleshooting](#troubleshooting)) and check what it says — registration
failures are logged, not swallowed.

### 5. Send a real event

```php
$GLOBALS['acme_sdk']->track( 'plugin_activated', array( 'version' => '1.0.0' ) );
```

`track()` never makes an HTTP call — it queues locally and sends on the next
15-minute heartbeat. To see it arrive immediately instead of waiting:

```php
$GLOBALS['acme_sdk']->flush();
```

**One real thing to know before you go looking for it on the dashboard:** a
brand-new installation starts with consent `pending`. Until the site owner
answers the automatic "Allow usage data?" prompt (or you answer it yourself,
while testing), the server correctly **refuses telemetry with a 403** — this
is the fail-closed consent gate working as designed, not a bug in your
integration. Click **Allow usage data** on your own test site, then `flush()`
again, and the event will arrive. See [Consent](#consent) for the full
behaviour.

That's the whole loop: install → bootstrap → activate → registers itself →
`track()` → shows up. Everything past this point is what each piece does in
more detail, and how to customize it.

---

## Installing it in a plugin

Two supported paths. **Both work; neither is preferred.** A plugin distributed
through wordpress.org usually cannot assume Composer, so the SDK is built to
work identically without it.

### 1. Bundled (no Composer)

Get the SDK from its standalone repository —
[github.com/magepeopleteam/appneck-wordpress-sdk](https://github.com/magepeopleteam/appneck-wordpress-sdk)
(`git clone` it, or download a release zip) — copy its directory into your
plugin, and require the loader — **not** any file in `src/`:

```php
require_once __DIR__ . '/vendor/appneck-sdk/appneck-sdk.php';
```

`appneck-sdk.php` defines no classes. It registers this copy's version and
arranges for exactly one copy on the site — the newest — to load its classes on
`plugins_loaded` priority 0. See "Version safety" below for why that matters.

Because the SDK is loaded on `plugins_loaded` priority 0, use it from priority 1
or later — **this deferred form is only correct for `Sdk::client()`**, which
has no activation/deactivation hooks to register:

```php
add_action( 'plugins_loaded', function () {
    $client = \Appneck\Sdk\Sdk::client(
        'pk_your_product_key',
        'sk_your_product_secret',
        'https://appneck.com'
    );
}, 20 );
```

If you genuinely need the SDK earlier, call `appneck_sdk_load_latest()` yourself.
It is idempotent and safe — but copies belonging to plugins that have not loaded
yet cannot have registered, so an older copy may win. That is the trade-off, and
it is why the default waits.

**`Sdk::bootstrap()` is the exception, and needs the SDK earlier, always** —
see the Quickstart's step 2 and the callout right after it. `bootstrap()`
calls `register_activation_hook()`/`register_deactivation_hook()` on your
behalf, and WordPress only honours those calls during your plugin file's own
synchronous top-level execution — deferring `bootstrap()` to `plugins_loaded`
silently breaks activation and deactivation. This was found by running the
deferred pattern against real WordPress core, not assumed: no local event
table was created and no registration cron was scheduled on activation until
the call moved to the top level.

### 2. Composer

```bash
composer require appneck/wordpress-sdk
```

Composer's PSR-4 autoloader resolves `Appneck\Sdk\*` from `src/`, so the classes
are available with no `require` of ours at all. If your plugin also ships to
users who install it without Composer, require the loader as well — it is safe
under both, since the loader's own bootstrap is guarded against classes that
already exist.

---

## Version safety (why the loader exists)

Several plugins on one site may each bundle their own copy of this SDK. If each
one simply required its classes, the second would raise
`Cannot redeclare class Appneck\Sdk\Client` — a fatal that takes down **the whole
site**, not just one plugin.

Guarding each class with `class_exists()` avoids the fatal but picks the wrong
winner: whichever plugin loads first wins, even if its copy is a year old, so a
plugin shipping a newer SDK silently runs on older code.

This package uses the **version-registry** pattern (the approach Action Scheduler
uses, and a variant of what Freemius does):

1. At include time, every copy appends `version => bootstrap path` to
   `$GLOBALS['appneck_sdk_versions']`. Nothing else happens — no classes, no I/O.
2. On `plugins_loaded` priority 0, `appneck_sdk_load_latest()` sorts the registry
   with `version_compare()` and loads the **highest version only**.

Two things in `appneck-sdk.php` are frozen and must stay backward compatible
forever, because the copy that defines them may be any version present on the
site — including one written before the version you are editing:

- the shape of `$GLOBALS['appneck_sdk_versions']`, and
- the behaviour of `appneck_sdk_load_latest()`.

To find out which copy actually won: `\Appneck\Sdk\Sdk::loaded_version()`.

---

## It will not take down the host site

This library runs inside other people's production WordPress sites. An uncaught
exception there is a white screen on a site whose owner has never heard of
Appneck, and no telemetry heartbeat is worth that.

So: **no public method throws.** Every call returns an
`Appneck\Sdk\Http\Response`, including for network failure, non-2xx responses,
malformed bodies, and any `Throwable` raised inside the SDK itself (`Error` as
well as `Exception`).

```php
$response = $client->get( '/sdk/v1/announcements' );

if ( ! $response->ok() ) {
    // Never throws. Inspect and move on.
    $response->status();            // 0 when no HTTP response was received
    $response->error_message();     // the server's own message where there is one
    $response->is_unauthorized();   // 401
    $response->is_rate_limited();   // 429
    $response->is_retryable();      // transport error, 429 or 5xx
    $response->rate_limit()->retry_after();
    return;
}

$announcements = $response->get( 'announcements', array() );
```

Logging is opt-in — pass a `Logger` to `Sdk::client()` if you want SDK failures
in your log. The default writes nothing, because filling a site owner's error log
is not ours to decide.

---

## Lifecycle

```php
appneck_sdk_load_latest(); // required here — see the Quickstart's step 2

\Appneck\Sdk\Sdk::bootstrap(
    'pk_your_product_key',
    'sk_your_product_secret',
    'https://appneck.com',
    __FILE__          // your plugin's main file
);
```

That wires activation, deactivation, the registration cron and the
admin_init fallback — **but only if this call happens at your plugin's top
level**, not inside `add_action('plugins_loaded', ...)`. For uninstall, add
`uninstall.php` to your plugin root:

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/appneck-sdk/appneck-sdk.php';

\Appneck\Sdk\Sdk::uninstall( 'pk_...', 'sk_...', 'https://appneck.com' );
```

`uninstall.php` rather than `register_uninstall_hook`, and the reason
matters: if `uninstall.php` exists WordPress **ignores**
`register_uninstall_hook` entirely, and that hook's callback has to survive
being serialized into the `uninstall_plugins` option — so it can only ever
be a static function name, never a closure or an instance method.
WordPress also loads **nothing** of your plugin for `uninstall.php` except
that one file, which is why it has to require the loader itself.

### Activation never waits on the API

`register_activation_hook` performs **no network I/O**. It marks the site
as needing registration and schedules an immediate cron event; the first
attempt runs on the next page load, in a request nobody is watching.

Calling the API during activation with a short timeout was considered and
rejected: if the API is slow or a firewall blackholes the connection,
every activation stalls for the full timeout and the person doing it
experiences that as "this plugin is broken". Deferring also means the
first attempt and every retry run the same code, so the path that matters
is the one exercised every time.

Failures retry on a widening backoff (1m → 5m → 15m → 1h → 6h → 24h),
capped at 12 attempts. A `403` (archived product) stops immediately —
that is a permanent refusal, and retrying for a day changes nothing.
WP-Cron only fires on traffic, which is correct: a site nobody visits has
nothing to report. `admin_init` is a fallback for sites with
`DISABLE_WP_CRON`, rate-limited to one attempt per hour.

### Reactivation

There is no client-side reactivation logic. The server's
`POST /sdk/v1/installations` is create-or-reactivate, so reactivating just
runs the registration flow again with the **stored** id — the server
reactivates that record instead of creating a duplicate, and correctly
declines to re-issue its secret, which the SDK expects and keeps the
stored one.

### Multisite: lazily, once per site

Each site in a network registers **itself**, the first time the cron or
`admin_init` path runs in its context.

Network activation fires the activation hook exactly once, so the
tempting implementation is to loop the network's sites and register each.
That is wrong at any real scale: a 500-site network becomes 500
synchronous API calls inside one activation request — a guaranteed
timeout, and exactly the kind of thing that gets an SDK blamed for taking
a network down.

Lazy per-site registration falls out of how WordPress already works.
`wp_options` is per-site, so `has_credentials()` is naturally answered
per-site: each site independently observes that it has none and enrols.
That also handles sites **created later** with no site-creation hook, and
spreads registrations across real traffic instead of one burst.

One site per installation is also what the data model already says: the
server's Site is keyed by domain and Installation is (site, product), and
every subsite of a network has its own domain. `is_multisite` is reported
so the backend can tell these apart.

**Known limitation:** network-wide *deactivation* fires once and cannot
feasibly notify every subsite, so subsites go silent rather than
reporting `deactivated`. The server's lost-installation detection
(journal 8.4) is what covers that case.

---

## Telemetry

```php
$sdk = \Appneck\Sdk\Sdk::bootstrap( 'pk_…', 'sk_…', 'https://appneck.com', __FILE__ );

$sdk->track( 'booking_created', array( 'source' => 'checkout', 'total' => 42 ) );
$sdk->track_error( 'Payment gateway timeout', array( 'gateway' => 'stripe' ) );
```

**`track()` never makes an HTTP call.** It writes one row to a local
queue and returns, so it is safe to call anywhere in a page load. An API
that is slow to call is one that careful developers move to a background
job and everyone else calls inline — so it simply isn't slow to call.
Sending happens on a scheduled flush.

`$sdk->flush()` forces a send now. That one *does* make a request, so
don't call it from a page load a visitor is waiting on.

### Local queue

A small custom table (`{prefix}appneck_sdk_events`), created with
`dbDelta` at activation — **not** `wp_options`. An option holding a
growing array is wrong three times over: every push means read →
unserialize → append → re-serialize → write the whole list (O(n) work on
a page load we're a guest in); two simultaneous `track()` calls silently
lose one event; and clearing only the events the server accepted means
rewriting the whole option again. A table gives O(1) appends, ordered
reads with a LIMIT, and deletes by id.

Capped at **1000 events**, dropping the **oldest** when full. That is ten
full batches — over two hours of complete backlog at the default
interval, and far more in practice. Dropping oldest rather than refusing
new ones means a site recovering from an outage reports what is happening
*now*; the alternative keeps a snapshot of whenever the outage started
and then goes blind.

If the table can't be created (hosts that revoke `CREATE`), the queue
degrades to storing nothing and the SDK simply never sends. Losing
analytics is our bad day; a fatal error would be the site owner's.

### Heartbeat

Every 15 minutes by default, filterable:

```php
add_filter( 'appneck_sdk_flush_interval', fn() => 30 * MINUTE_IN_SECONDS );
```

Journal §9.1 sizes the server's rate limits around "one heartbeat every
five minutes per installation", so 15 sits comfortably inside what the
server expects; §8.4 notes WP-Cron only fires on page load (so any
interval is a ceiling, not a promise) and sets the lost-installation
threshold at 24 hours — 15 minutes leaves ~96x margin, so a quiet site
can miss many ticks before it looks lost. There is a 60-second floor so a
filter can't turn into a request storm.

A heartbeat is an ordinary event of type `heartbeat` on the same queue,
sent in the same batch — not a private code path. That way the retry and
partial-success behaviour is exercised constantly by the most common
event there is, rather than being a rarely-tested branch.

### What happens to each response

| Response | Queue | Sending |
|---|---|---|
| `202` accepted | cleared | continues |
| `202` partially rejected | accepted **and** rejected cleared, rejections logged | continues |
| `422` all invalid | cleared, logged | continues |
| `429` | kept | paused for the server's `Retry-After` |
| `403` consent | kept, keeps accumulating | paused 1h — consent may be granted |
| `403` inactive install | purged | stopped until re-registration |
| `401` / `5xx` / network | kept | retries next tick |

Permanently-invalid events are dropped rather than retried: they can never
become valid, and keeping them blocks the queue behind events that can
never leave it.

Every `202` also carries a `config_version` field the SDK reads to notice
when an org admin has changed announcements or survey questions — nothing
you need to act on; it feeds the realtime-config-delivery machinery covered
under Announcements below. Registration and status responses carry it too.
A server that predates this field, or a response missing it for any reason,
is treated the same as "no change" — never an error.

---

## Consent

Nothing extra to wire: `Sdk::bootstrap()` registers the prompt, the
`admin-post.php` handler and the retry hooks. Two things are worth doing
by hand.

Tell the prompt which policy it is asking about — the server records a
privacy policy version on every consent decision, and only you know yours:

```php
add_filter( 'appneck_sdk_privacy_policy_version', fn() => '2026-08-06' );
```

And give site owners a way to change their mind, inside your own settings
page:

```php
$sdk->consent_notice()
    ->set_privacy_policy_url( 'https://acme.test/privacy' )
    ->render_settings_section();
```

### The prompt

An admin notice, shown until it is answered, with **Allow usage data** /
**No thanks**. Not a settings page of our own: an embedded library must
not add a top-level menu to somebody else's plugin, and two plugins
bundling this SDK would each add one. It is also **not dismissible** — a
dismiss button is a third answer meaning neither yes nor no, and the state
it leaves behind is the one where buffering continues, so it would read as
a way to make the question go away while collection quietly carried on.

Both buttons are form submits to `admin-post.php`, nonce-checked, gated on
`manage_options`, on a per-product action name (a shared action would mean
one plugin's Accept click answering for every other SDK copy on the site).

### The three states, and what `track()` does in each

| State | `track()` | Local queue | Sending |
|---|---|---|---|
| `pending` (never asked) | buffers | kept | attempted; server refuses with 403, events survive |
| `accepted` | buffers | kept | normal |
| `rejected` | **no-op** | **purged** | nothing sent, nothing collected |

The server is the enforcement (`/sdk/v1/telemetry` fails closed on
anything but `accepted`, journal §5.4). The client-side behaviour above is
about behaving decently on the site owner's own machine.

**Why a reject stops collecting rather than parking events:** continuing to
write rows into their database that can never be sent is still behaving
like a tracker on a system that said no — "we collect but don't transmit"
is not a defence anyone accepts, and the local buffer's only justification
was imminent transmission. Purging rather than parking also matters: a
retained backlog would mean a later change of mind shipping events
collected during exactly the window they had refused.

**Why `pending` is not treated the same way:** never-asked and said-no are
different facts. While the question is open the prompt is on screen, a
grant may be seconds away, and the backlog is exactly what should go out
when it comes — which is the behaviour the telemetry phase already proved.

### If the API is unreachable when they click

The decision is written locally **first**, then sent. So the click always
lands: the prompt does not come back, the local consequences (stop
collecting, or lift the consent back-off) apply immediately, and the site
owner is redirected back to the page they were on rather than to an error.
The unsent decision retries on a widening backoff (1m → … → 24h, capped at
12 attempts), plus an hourly `admin_init` fallback for sites where WP-Cron
cannot run.

A decision made in the first seconds after activation — before
registration has finished, since activation performs no network I/O —
costs no attempts at all: there is nothing to sign with yet, so nothing is
attempted, and it goes out once credentials exist.

### Privacy policy versions

A version change re-shows the prompt for a previously **accepted**
decision, with different wording, and the re-confirmation records a fresh
consent event under the new version. It does **not** block telemetry while
unanswered: the server stores the version each decision was made under but
has no notion of a *current* version to compare, so blocking client-side
would leave an installation the server reads as `accepted` while it
silently stopped reporting — which trips lost-installation detection
(journal §8.4) for a healthy site. A **rejected** decision is never
re-prompted by a version change. Bumping a version over a typo:

```php
add_filter( 'appneck_sdk_reprompt_on_policy_change', fn() => false );
```

### Reading it yourself

```php
$sdk->consent()->is_accepted();   // whether Appneck may collect telemetry
$sdk->consent()->status();        // pending|accepted|rejected
$sdk->consent()->decided_at();
$sdk->consent()->is_sync_pending();
```

The decision lives in one autoloaded `wp_options` row per product
(status, date and policy version together — a status without them is a
fragment, not a consent record). Autoloaded, unlike the credentials,
because `track()` reads it on page loads where the credentials are never
touched. `Sdk::uninstall()` deletes it; the server keeps the permanent
`consent_events` history regardless.

### What this consent is *not*

`is_accepted()` answers exactly one question: **may Appneck collect
telemetry for this installation?** It is scoped to that, and only that.

**Do not** wire it up as your plugin's general-purpose consent flag —
don't gate your own analytics, your own third-party integrations, GDPR
cookie banners, marketing opt-ins, or any other feature that has nothing
to do with Appneck's own data collection on this signal. Two independent
reasons this matters, not just style:

- **The site owner didn't agree to that.** They answered one specific
  question ("share usage data with Appneck?"). Reusing that answer to
  silently gate something unrelated means acting on a consent they never
  gave, which is the kind of scope-creep that erodes the reason consent
  prompts are trustworthy at all.
- **The two can legitimately diverge**, and code that conflates them will
  behave wrong when they do. A site owner may reject Appneck telemetry
  while still wanting your plugin's own opt-in email marketing, or the
  reverse. If your plugin needs its own consent state for its own
  purposes, build that as its own flag — it is a few lines of
  `wp_options`, not a reason to overload this one.

---

## Deactivation survey

**Configured in your Appneck Org Panel (Product → Survey Questions), not in
code.** This SDK does not author questions — it fetches whatever your
organization has configured there, renders them in the deactivation modal,
and submits the answers back. If you're looking for how to add or edit
questions, that's an Org Panel task, not a code change in this plugin.

Nothing to wire. `Sdk::bootstrap()` registers the modal, and if your product
has no survey configured in Appneck, nothing appears at all.

When someone clicks **Deactivate** on the plugins screen, the click is
intercepted and a modal asks your configured questions — radio, checkbox,
rating, dropdown and free text, rendered from whatever the organization set
up. Then the plugin deactivates.

If the SDK could not read your plugin's name from its file header, set it so
the prompt can say who is asking:

```php
$sdk->deactivation_survey()->set_product_name( 'Acme Bookings' );
```

### Deactivation always wins

| The site owner… | What happens |
|---|---|
| answers and submits | the answers are sent, then the plugin deactivates |
| clicks **Skip & deactivate** | nothing is sent, the plugin deactivates |
| submits and the API is down | the failure is logged, the plugin deactivates |
| has no survey configured | no modal at all, the plugin deactivates |
| closes the modal (X, Escape, backdrop) | **cancelled** — the plugin stays active |

Only the last one stops a deactivation, and it is the one the owner asked
for. Everything else proceeds: this is feedback collection, never a gate —
the same rule as "activation never waits on the API", at the other end of
the lifecycle. Closing the box is deliberately a *cancel* rather than a
skip, because treating a stray Escape key as "yes, deactivate this" would
be worse than asking again.

### One attempt, no retry

Unlike telemetry there is no queue and no retry. The moment has passed —
the plugin is being deactivated as the request goes out — and the server
records one response per installation anyway, so a resurrected submission
days later would be a duplicate at best.

**A failed submission is never shown to the site owner**, and that is
forced rather than chosen: the only place to show it would be the admin
screen loaded *after* deactivation, and a deactivated plugin runs no code,
so it cannot render a notice. Holding the modal open to apologise makes our
failure into their delay. So the failure goes to the `Logger` (opt-in) and
nowhere else.

### Questions are fetched live, not cached

Fetched from `GET /sdk/v1/survey-questions` **at the moment the modal opens**
— never from a stale cache. The modal itself never waits on this: it appears
instantly, then shows a brief loading state while the fetch runs, with a
3-second timeout. If the fetch fails, times out, or the SDK's internal
circuit breaker is already open from repeated recent failures, it falls back
instantly to whatever was last successfully fetched on this site — and if
nothing has ever been fetched at all, deactivation simply proceeds with no
survey rather than trapping the site owner in a broken modal. An edit made in
the Org Panel is therefore visible to the very next click, on any site — not
"eventually, once the cache expires."

### Validation happens twice, on purpose

`Survey::validate()` mirrors the server's rules (a choice must be one of the
configured choices, a rating within its configured max, free text under 2000
characters) so a mistake appears next to the field instead of as a rejection
nobody sees. It is a UX affordance, not a boundary — the server re-checks
everything, and if the two ever disagree the deactivation still proceeds.

An unanswered question is not an error: every question is optional, blank
fields are omitted from the submission, and an entirely blank form is
treated as a skip rather than stored as an empty response.

### Assets

The markup, CSS and JS print inline in the footer of `plugins.php` only —
no enqueued files, because a bundled SDK cannot know its own URL (it may
live in `vendor/`, a custom directory, or a mu-plugin), and no requests on
any other admin screen. The script is ES5 and uses `XMLHttpRequest`, so it
needs no build step and works on whatever browser wp-admin is being driven
from.

---

## Announcements

**Authored in your Appneck Org Panel (Product → Announcements), not in
code.** Same relationship as the survey above: this SDK is a display client,
not an authoring tool. To publish, schedule, or retract an announcement, do
that in the Org Panel — the SDK's job starts at fetching what's published
there.

One authoring option is worth knowing about even though it's not a code
change: marking an announcement **urgent** in the Org Panel is a
delivery-speed setting, not a content category. It's a separate axis from
`type` (security/feature/discount/update) — an urgent discount and a
non-urgent security notice are both real combinations. Urgent reaches an
already-open wp-admin tab within about a minute (see the 60-second poll
below); everything else waits for that site's next page load, which is
already automatic.

**Rendering is now fully automatic too.** `Sdk::bootstrap()` calls
`render_globally()` for you: announcements print on every wp-admin page for
any `manage_options` user, kept fresh by a background request after the page
has already painted, with no reload needed when something changes. There is
nothing left to call for the standard case.

The original, narrower methods are still there if you deliberately want that
instead — one specific screen, no background refresh between page loads:

```php
// one screen only, no automatic background refresh
$sdk->announcement_notices()->render_on_screen( 'settings_page_acme' );

// or call it directly inside your own settings page callback
$sdk->announcement_notices()->render();
```

> ⚠️ Don't call either of these **in addition to** the automatic default —
> `bootstrap()` already wired the global version, so doing both prints the
> same announcement twice on whichever screen `render_on_screen()`/`render()`
> targets.

Either way it prints **nothing at all** when there is nothing to show — no
empty container — so it is safe to call unconditionally.

Want to render them yourself instead?

```php
foreach ( $sdk->announcements()->visible() as $announcement ) {
    // id, type, title, body, starts_at, expires_at
}
```

### Not consent-gated

Announcements are authenticated but **not** gated on the site owner's
telemetry consent, and the SDK deliberately does not add a gate the server
doesn't have. Consent governs data collected *from* a site; this is content
sent *to* it, and someone who declined telemetry has not asked to stop being
told about a security release.

### Two refresh paths now, not one

The original cron-driven refresh still exists unchanged — it hangs off the
**existing** heartbeat tick (`appneck_sdk_flush`, 15 minutes by default) as
one more listener, and there is still no announcements schedule of its own to
create, clear or reason about.

`render_globally()` adds a second, faster path on top of it: a background
admin-ajax request fires after every wp-admin page load (never during the
page's own render) and refreshes if the cache is stale or older than 60
seconds — enforced by a single-flight lock, so ten tabs or ten page loads in
the same window still produce at most one upstream call, not ten. On top of
that, a lightweight 60-second poll (`GET /sdk/v1/config`, the cheapest call
this SDK makes) checks only whether anything **urgent** has appeared; a
non-urgent change waits for the next page load rather than being pulled
mid-session.

For sites where WP-Cron cannot run at all, the original fallback also still
exists: if the cache is over 12 hours old **and** the site owner is on your
settings screen (via `render_on_screen()`/`render()`), one refresh is
attempted, rate-limited to once an hour whether it succeeds or not.

### What a failed refresh does: nothing visible, ever

| Response | Cached list |
|---|---|
| `200` with announcements | replaced |
| `200` empty (all unpublished or expired) | replaced — they stop showing |
| `403` (installation not active) | **kept** |
| `500` / network failure / timeout | **kept** |

A failed poll is not evidence that you stopped announcing anything, and
blanking the list because a request timed out would make a security notice
vanish. Expiry is the server's job: it evaluates the validity window on
every request, and the SDK deliberately does not re-check it locally — a
site clock a few minutes out would otherwise hide something you chose to
send.

**A shared circuit breaker covers all of the realtime paths above** (the
60-second poll, the page-load refresh, and the live survey-questions fetch
covered earlier): after 3 consecutive failures, the SDK stops attempting any
of them for about 15 minutes, then quietly tries again. Nothing about this
is visible to the site owner — no error, no console warning, no change in
page load time — and a self-hosted or lagging Appneck build that has never
even deployed `GET /sdk/v1/config` degrades the same way (a 404 counts as a
failure like any other) rather than breaking anything.

### Display

All undismissed announcements stack, **most urgent type first**, and the
server's own recency decides within a type — so a Security Notice is never
queued behind a discount. At most three print at once; the rest surface as
earlier ones are dismissed. Type maps onto WordPress's own notice levels:

| Type | Notice |
|---|---|
| `security` | `notice-error` |
| `update` | `notice-warning` |
| `feature` | `notice-info` |
| `discount` | `notice-success` |

Titles and bodies are escaped and line breaks preserved; no HTML from the
server is ever rendered.

### Dismissal

Per announcement, stored on the site — the WordPress option is the **source
of truth for what displays**, so dismissing hides a notice instantly with no
network dependency, and it stays hidden even if Appneck is unreachable at
that exact moment. The dismissal lives in its own option, **not** in the
cached list, which is the point: the cache is replaced wholesale on every
refresh, so a dismissal kept inside it would be forgotten on the next tick
and the announcement would come back while still inside its validity window.

On the `render_globally()` path, dismissing also fires a best-effort report
to Appneck afterwards (purely so the Org Panel can eventually show how many
installations dismissed something instead of acting on it) — its failure is
silent and changes nothing about what the site owner sees. On the original
`render_on_screen()`/`render()` path, the Dismiss control is still the
original nonced POST gated on `manage_options`, unchanged — not core's
dismissible X, since core's X is added by its own JS and only hides the box
for that page view, which is the opposite of what a stored dismissal means.

---

## Licensing

For selling a premium plugin. Wired by `bootstrap()`; nothing to schedule.

```php
if ( $sdk->license()->is_valid() ) {
    // premium feature
}
```

### `is_valid()` is safe to call on every page load

That is a design guarantee, not a hope. Four properties make it true:

- One **autoloaded** `wp_options` read. WordPress already fetches that row
  in the single query it makes at the start of every request, so the
  common path costs **no extra query**.
- **No network call** unless the cached result is older than the TTL (24
  hours by default) *and* the server isn't already known to be
  unreachable.
- **Never throws.** Every path returns a bool.
- **No key stored → `false`, and zero HTTP, forever.** A site that never
  entered a key generates no licensing traffic at all.

The exact order it evaluates:

1. No key stored → `false`, no call.
2. Cache fresh → the cached flag, no call.
3. Inside the retry backoff → the fail-mode answer, no call.
4. Otherwise → one `validate` call (5s timeout), then answer.

### The public API

| Method | Returns | Notes |
|---|---|---|
| `is_valid()` | `bool` | The hot path. Above. |
| `get_status()` | `array` | For rendering. Never makes a network call. |
| `activate( $key )` | `Http\Response` | Human-triggered. No caching, no backoff. |
| `deactivate( $key )` | `Http\Response` | Same. |
| `validate( $key )` | `Http\Response` | Force a re-check now. |
| `failure_count()` / `next_attempt_at()` | `int` | Backoff introspection. |

`get_status()` keys: `has_license`, `license_key` (masked to the last 4
characters), `status`, `reason`, `customer_name`, `expires_at`,
`activation_limit`, `activations_used`, `valid`, `last_checked_at`,
`stale`, `next_attempt_at`, `fail_mode`.

Two rules that each prevent a support ticket:

- **A rejected key is not stored** — otherwise the site spends its life
  re-validating a key the server already refused.
- **A failed deactivation does not clear local state.** The server still
  counts this domain as holding a slot; forgetting the key locally would
  strand it with the customer holding nothing to release it with.

### The admin panel

```php
$sdk->license_form()->render();   // on YOUR settings page
```

Prints **nowhere** until you call it. A licence key input appearing
unbidden on a screen the site owner didn't associate with your product is
indistinguishable from a phishing field. `admin-post.php`, nonce-verified,
`manage_options`, no enqueued assets, no build step.

### The complete license page — one call

For a whole license screen (menu item, every state, the Activate/
Deactivate form, all handled), rather than a fragment on a page you build
yourself:

```php
$sdk->license_page( array(
    'parent'       => 'options-general.php',           // null = top-level menu
    'page_title'   => 'License',
    'menu_title'   => 'License',
    'capability'   => 'manage_options',
    'purchase_url' => 'https://example.com/pricing',    // shown when there's no license
    'renew_url'    => 'https://example.com/account',    // shown when expired
    'support_url'  => 'https://example.com/support',    // shown when suspended/cancelled/refunded/revoked
    'notice'       => true,                             // opt-in unlicensed nag, see below
) );
```

Every argument is optional; calling `$sdk->license_page()` with nothing
registers a working top-level "License" menu item. `menu_slug` defaults
to one derived from your own product's API key, so two different plugins
on one site that each bundle this SDK never collide on a menu slug even
if neither developer thought about it.

This is a **different** thing from `license_form()` above, not a
replacement for it — see `Admin\LicensePage`'s own class doc for the full
reasoning. Use `license_form()->render()` if you already have a settings
page and want the license panel to live inside it; use `license_page()`
if you want the SDK to register and own the whole page.

**Call it before `admin_menu` fires — at your plugin's top level or on
`plugins_loaded`, never from inside an `admin_menu` callback.**
`license_page()` registers its own `add_menu_page()` call on `admin_menu`
at the normal default priority. If you call `license_page()` from *inside*
a LATER-priority `admin_menu` callback, WordPress has already passed the
default priority for that request by the time your callback runs, so the
menu item is silently skipped — no error, no warning, the page just never
appears. (This is a real WordPress hook-ordering rule, not something
specific to this SDK, but it is an easy trap to fall into if menu
registration feels like an "admin_menu thing" you'd naturally reach for.)

**What a customer sees, per state:**

| State | What it shows |
|---|---|
| No license | A key input and Activate button; a "buy one" link if you gave `purchase_url`. |
| Active | Masked key (last 4 characters only), who it's licensed to, expiry as a real date **and** a relative phrase ("expires in 11 months"), sites used ("2 of 5" or "2 of Unlimited" for an unlimited plan), a Deactivate button with a confirmation explaining the site loses its pro features. |
| Expired | The key stays visible (needed to renew), a plain explanation that the plugin keeps working and only updates/support stop, and a Renew link if you gave `renew_url`. |
| Suspended | An explanation that the publisher suspended it and support can help — no Activate button, because retrying cannot fix this. |
| Cancelled / refunded / revoked | Wording specific to each — read from the server's own reason, never a guessed label. |
| Activation limit reached | Shown as the result of a failed Activate attempt, not a stored page state — says the plan's limit is reached and to either deactivate elsewhere or upgrade. |
| Server unreachable, last known valid | The last confirmed status, clearly labelled as such, when it was last confirmed, and a note that this keeps working and retries automatically — deliberately not alarming, since this is almost always a transient network blip on a paying customer's site. |
| Server unreachable, never confirmed | A different message from the above — there is no license key that ever validated, so it says the connection could not be verified and to check it, without implying anything was ever working. |

### Gating a pro feature

```php
if ( ! $sdk->license()->require_valid() ) {
    return; // prints its own explanatory notice; never exits
}
```

Call it at the top of a pro feature's own admin page. It prints a styled
notice (linking to the license page, once you've called `license_page()`)
and returns `false` when the license isn't valid; returns `true` and
prints nothing otherwise. It never calls `exit`/`wp_die` — what happens to
the rest of the page after a `false` is entirely your call. Pass a string
to override the default message: `require_valid( 'Upgrade to Pro to use this report.' )`.

### The unlicensed nag — opt-in, and narrow on purpose

`license_page( array( 'notice' => true ) )` turns on a dismissible admin
notice prompting activation. It is **off by default** and, even enabled,
prints in exactly two places: your license page's own screen, and the
Plugins screen — never site-wide. A dismissal persists (stored in an
option, not a transient — see `License`'s own class doc for why a
persistent-cache-backed store is the wrong layer for anything that must
not silently reappear a day early) and the notice stays hidden for about
a week before it can show again. It never appears at all while the
license is valid, and — just as importantly — it stays silent while the
license is merely *unreachable but was last confirmed valid*: a network
blip must never nag a paying customer.

### The SDK never hands your code the raw key

Not `get_status()`, not `license_form()`, not `license_page()`, not any
public method on `License`. The key is stored (it has to be, to sign
`validate`/`deactivate` calls) but nothing in this package's public API
returns it — `get_status()['license_key']` is always masked to the last 4
characters. Deactivating the stored license goes through
`deactivate_stored()`, which reads the raw key out of storage internally
and never gives it back to the caller, precisely so a page that only ever
renders a masked key never has the real one to leak.

### A complete reference plugin

```php
<?php
/**
 * Plugin Name: Acme Bookings Pro
 */
require_once __DIR__ . '/vendor/appneck/wordpress-sdk/appneck-sdk.php';
appneck_sdk_load_latest();

$sdk = \Appneck\Sdk\Sdk::bootstrap(
    'pk_your_product_api_key',
    'sk_your_product_secret',
    'https://appneck.com',
    __FILE__
);

// A full license screen, under Settings, with the unlicensed nag on.
add_action( 'plugins_loaded', function () use ( $sdk ) {
    $sdk->license_page( array(
        'parent'       => 'options-general.php',
        'purchase_url' => 'https://example.com/pricing',
        'renew_url'    => 'https://example.com/account',
        'support_url'  => 'https://example.com/support',
        'notice'       => true,
    ) );
} );

// One pro feature, gated.
add_action( 'admin_menu', function () use ( $sdk ) {
    add_submenu_page( 'options-general.php', 'Acme Reports', 'Acme Reports', 'manage_options', 'acme-reports', function () use ( $sdk ) {
        echo '<div class="wrap"><h1>Acme Reports</h1>';

        if ( $sdk->license()->require_valid() ) {
            echo '<p>Your premium report goes here.</p>';
        }

        echo '</div>';
    } );
} );
```

### Fail-open is the default, and it is narrow

Unreachable server → the customer keeps their features. Precisely:

- It returns the **last definitive answer**, never a blanket `true`. An
  outage does not upgrade a known-invalid licence to valid — otherwise the
  whole system would be defeatable by blocking one hostname.
- A key that has **never once validated successfully** → `false`.
- A definitive answer is always honoured, including a negative one. Only
  a 2xx whose JSON parsed is definitive: a 5xx, a 429, a 401 from a
  rotated secret and an HTML error page from a WAF are all "no answer",
  because none of them is the server saying anything about this licence.

To invert it — features locked when the server can't be reached:

```php
$sdk = \Appneck\Sdk\Sdk::bootstrap(
    'pk_...', 'sk_...', 'https://appneck.com', __FILE__,
    null, null, null, null,
    array( 'license_fail_mode' => 'closed' )
);
```

### Why isn't it retrying?

Because it is **deliberately** waiting. After a failed check:

| Consecutive failures | Wait |
|---|---|
| 1 | 5 minutes |
| 2 | 15 minutes |
| 3 | 1 hour |
| 4 | 6 hours |
| 5+ | 24 hours (hard ceiling — jitter cannot push past it) |

±20% jitter, rolled **once** when the failure is recorded and stored as an
absolute timestamp — re-rolling it per read would make the wait random per
page load rather than per failure, which is noise, not a spread. Any
success resets the counter.

Without this, every page of a site whose licence server is down would
block for the request timeout, and every affected site retrying on every
page load would turn an outage into a flood aimed at the recovering
server. `failure_count()` and `next_attempt_at()` expose both halves of
the answer.

### Options

Ninth argument to `bootstrap()`:

| Option | Default | Meaning |
|---|---|---|
| `license_fail_mode` | `'open'` | `'closed'` locks features when unreachable. Anything not exactly `'closed'` is open — a typo must not lock a customer out. |
| `license_cache_ttl` | `86400` | Seconds before a cached result is re-checked. |
| `license_domain` | *(`home_url()`)* | Override the activation domain, e.g. so a staging clone doesn't consume the production slot. |

### Domain normalisation

Byte-for-byte the server's rule (journal §22.8): trim, strip any scheme,
keep everything before the first `/`, lowercase. **`www.` is not stripped,
and neither is a port** — `www.example.com` is a genuinely different host,
and merging it would collapse a real multi-site setup into one slot. This
*must* match the server: it enforces "one domain, one active activation"
with a Postgres partial unique index on the normalised string, so a client
that normalised differently would let one install quietly burn two slots.

Pinned by `LicenseTest::test_domain_normalization_matches_the_servers_cases`
against the same 17 fixtures the server's own implementation was run over
side by side.

### It works with no analytics consent

Licensing authenticates at the **product** level and requires no
installation at any point (journal §23.1). A customer who declined
telemetry has no installation row, and their licence still works — a
paying customer is never held hostage by an unrelated opt-in.

### Uninstall

`Sdk::uninstall()` makes one best-effort, short-timeout deactivation call
so the slot is freed, then deletes the stored row regardless of the
outcome. This is the one place the keep-state-on-failure rule is inverted,
deliberately: the plugin is going away either way, so there is no local
state left for a retry to live in.

---

## Signing

Every telemetry request is HMAC-signed per journal §9.2a:

```
X-Signature = HMAC_SHA256(base_string, secret)
base_string = METHOD \n /path \n installation-id \n timestamp \n raw-body
```

`Appneck\Sdk\Signer` is pure and deterministic. Two signing modes:

- **bootstrap** — `POST /sdk/v1/installations` only, signed with the product
  secret shipped in your plugin.
- **installation** — everything else, signed with the per-installation secret the
  server issues at registration and never re-discloses.

The client will **not** fall back to the product secret when no installation
secret is stored. That fallback would let any installation sign as any other
installation of the same product, which is precisely the hole per-installation
secrets exist to close.

### Licensing signs differently (journal §23.1)

`/sdk/v1/licenses/*` uses a **second, separate** scheme —
`Appneck\Sdk\LicenseSigner`, not `Signer`:

```
X-Signature = HMAC_SHA256( raw-body . timestamp, product secret )
```

No method, no path, no installation id, and no newlines. Headers are
`X-Api-Key`, `X-Timestamp`, `X-Signature` — deliberately **no**
`X-Installation-Id`, because the server neither reads nor requires one.

The two are not interchangeable and must not be merged: licensing has no
installation to bind against (a customer who declined analytics consent
never gets one, and their licence must still work), and it is signed with
the product secret for every call rather than only at bootstrap.
`LicenseSignerTest` pins the output against a hardcoded fixture vector, so
a refactor that changed the base string by one byte fails the suite
instead of 401ing every site in the field.

---

## Storage

`installation_id` and `installation_secret` are stored **together in one
`wp_options` row** (`WpOptionsCredentialStore`, `autoload = no`). Together,
because a pair written separately can be half-restored from a backup, leaving an
id with no secret — an unauthenticatable state with no recovery, since the secret
is issued once and never re-issued. Supply your own `CredentialStore` if you need
different persistence.

The **licence** state (`WpOptionsLicenseStore`) is a separate row and is
`autoload = yes`, which is the opposite call for the opposite reason:
`is_valid()` reads it on every page load, so leaving it out of the blob
WordPress already fetches would add a query to every request. Autoload
follows read frequency — Consent's row makes the same trade.

It is deliberately **not** a transient. A transient backed by a persistent
object cache can be evicted at any moment, and eviction is
indistinguishable from expiry: on a fail-open product that costs an
unnecessary network call, and on a fail-closed one it locks a paying
customer out of features at a moment nobody can correlate with anything.
An option row is durable, so the TTL is arithmetic the SDK does itself
against a stored timestamp rather than something the storage layer is
trusted to enforce. Supply your own `LicenseStore` if you need different
persistence.

---

## Filters reference

Every customization point this SDK exposes. This is the complete list —
verified by grepping the package for every `apply_filters()` call, not
assembled from memory, so if a filter you were expecting isn't here, it
doesn't exist yet.

| Filter | Default | Arguments | What it controls |
|---|---|---|---|
| `appneck_sdk_flush_interval` | `900` (15 minutes, in seconds) | `$interval, $api_key` | How often the heartbeat cron flushes the local event queue. Floored at 60 seconds regardless of what the filter returns — a filter returning something silly cannot turn into a request storm on the site owner's server. |
| `appneck_sdk_privacy_policy_version` | `'1.0'` | `$version, $api_key` | The privacy policy version recorded on every consent decision. Set this to your own version string so a later change can trigger a re-prompt (see below). |
| `appneck_sdk_reprompt_on_policy_change` | `true` | `$reprompt, $api_key` | Whether bumping the privacy policy version re-shows the prompt to a site that already **accepted**. Set to `false` if you're correcting a typo in the version string rather than actually changing the policy — you don't want a re-prompt for that. A **rejected** decision is never re-prompted by this filter either way. |

All three receive the product's `$api_key` as a second argument, so a
plugin bundling more than one Appneck-instrumented product can tell them
apart inside the same callback:

```php
add_filter( 'appneck_sdk_flush_interval', function ( $interval, $api_key ) {
	return 'pk_your_product_key' === $api_key ? 30 * MINUTE_IN_SECONDS : $interval;
}, 10, 2 );
```

There are no `do_action()` hooks fired outward by this package — nothing to
subscribe to, only these three values to override.

---

## Troubleshooting

**Multiple plugins bundle different SDK versions, and I'm not sure which one
is actually running.**

```php
echo \Appneck\Sdk\Sdk::loaded_version();
```

Exactly one copy loads per site — the highest version registered by any
plugin present, per [Version safety](#version-safety-why-the-loader-exists).
This tells you which one won. If your own copy is newer than what's loaded
and you need to confirm your code is even the code running, this is the
first thing to check — a bug that looks like "my change didn't take effect"
is often "a different plugin's older bundled copy won."

**Telemetry isn't arriving on the dashboard.**

Logging is opt-in (see [It will not take down the host site](#it-will-not-take-down-the-host-site)) — the default `Logger` writes nothing, so a
silently-failing SDK call looks identical to a working one until you turn
logging on:

```php
\Appneck\Sdk\Sdk::bootstrap(
	'pk_...', 'sk_...', 'https://appneck.com', __FILE__,
	null, null,
	new \Appneck\Sdk\Logging\ErrorLogLogger( 'Acme Bookings' )
);
```

Then check `wp-content/debug.log` (or wherever `error_log()` goes on that
host). Once logging is on, work through these in order:

1. **Is the installation registered?** `$sdk->is_registered()` — if false,
   registration hasn't completed yet (see the Quickstart's step 4: it needs
   a page load or `wp cron event run appneck_sdk_register` after
   activation, since activation itself makes no network call).
2. **Is consent `accepted`?** `$sdk->consent()->status()` — a fresh install
   starts `pending`, and the server fails telemetry closed (403) until the
   site owner answers the prompt. This is by far the most common reason a
   brand-new integration "isn't sending anything": nothing is wrong, nobody
   has clicked **Allow usage data** yet.
3. **Is the log showing an actual error?** A 401 means the signature or API
   key is wrong — double check you're using the *installation* secret path
   correctly (see [Signing](#signing); the client never falls back to the
   product secret once an installation secret exists, by design). A 429
   means you're being rate-limited — the SDK already backs off on
   `Retry-After` automatically, so this should self-resolve. A `5xx` or a
   transport error is kept and retried on the next tick; nothing to do.

**A request I expected to succeed came back `401 Invalid signature.`**

The single most common cause is signing with the wrong secret for the
route: `POST /sdk/v1/installations` (registration) signs with the
*product* secret; every other endpoint signs with the *per-installation*
secret the server issued at registration. If you're calling `Client`
directly rather than through `Sdk::bootstrap()`/`Plugin`, make sure
credentials are actually stored before the first non-registration call —
`$sdk->is_registered()` tells you.

**A registration attempt returns `409 An installation already exists for
this site and product.`**

A normal uninstall → reinstall on the same site self-heals automatically
(journal §9.2b — see [Known limitations](#known-limitations)) as long as it
happens within the reclaim grace window (15 minutes by default), so a `409`
here usually means either that window closed, or the credentials were lost
some other way (backup restore, a `wp_options` row deleted by another tool)
without a clean uninstall ever happening — no reclaim token was ever issued
either way. The SDK stops retrying on a `409` automatically rather than
burning through its backoff schedule for nothing; see Known limitations for
the current, honest state of recovery from this.

---

## Known limitations

Stated plainly, the same way the rest of this project logs gaps — these are
real, current edges, not hedging:

- **Self-service recovery exists for the common case (a clean uninstall),
  not for credential loss.** journal §9.2b: `uninstall.php` reporting
  `removed` (signed with the real installation secret — proof of
  possession) issues a short-lived, single-use reclaim token, which this
  SDK stores and automatically presents on the next activation for the
  same site. A normal uninstall → reinstall now just works — same
  installation id, same history, no `409`, nothing for a plugin author to
  do. What is still **not** self-service: a site whose credentials were
  lost some other way (a partial backup restore, a plugin conflict
  clearing options, manual `wp_options` surgery) never went through that
  signed removal, so it never received a token, and still gets a clean
  `409` with no client-side path back — recovery there still requires an
  Appneck operator resolving the conflict server-side. The reclaim token
  is also only valid for 15 minutes after removal by default
  (`installation_reclaim_grace_minutes` server-side) — a reinstall well
  after that window falls back to the same operator-recovery path.
- **The production event queue's SQL is not covered by this package's own
  test suite.** `TableEventQueue` uses `dbDelta` against a real `$wpdb` —
  this package's test harness has PHP but no real WordPress/MySQL to run
  it against, so only the in-memory `EventQueue` contract
  (`ArrayEventQueue`) is exercised in tests, and the integration scripts
  substitute it too. The schema, eviction-when-full, and delete-by-id logic
  need one real pass on an actual WordPress install before you'd want to
  lean on them at real scale. If you need a different persistence strategy
  meanwhile, `Sdk::bootstrap()` accepts any `EventQueue` implementation.
- **The deactivation survey modal's browser JS is untested.** No
  browser/build-step test harness exists for this package, so only the
  server side (the admin-ajax handler, the questions endpoint, submission)
  has coverage. The riskiest untested part is the link-matcher that
  intercepts the **Deactivate** click on the plugins screen — if a future
  WordPress core release changes that screen's markup, the survey could
  silently stop appearing (deactivation itself still works either way,
  which is the safe direction, but you'd lose the feedback silently rather
  than with an error).
- **Multisite network-wide deactivation doesn't notify every subsite.**
  Each subsite registers itself lazily and independently (see
  [Lifecycle](#lifecycle)), which is deliberate and works well for
  activation and normal use — but a **network-wide** deactivation fires
  once and cannot feasibly loop every subsite to tell the server each one
  went dark. Those subsites go quiet rather than reporting `deactivated`;
  the server's own lost-installation detection is what eventually notices.
- **PHP 7.2 minimum, tested primarily against modern PHP.** The version
  floor is deliberate (WordPress's own broad hosting reality), but day-to-day
  development and testing happens on current PHP — if you're deploying to a
  genuinely old PHP 7.2 host, treat that combination as less exercised than
  the rest.

None of these block using the SDK — they're the honest state of what's
solid versus what has an open edge, so you can decide what matters for your
own deployment rather than finding out the hard way.

---

## Tests

```bash
# from the repo root, with the dev stack up
docker compose run --rm --no-deps -v "$(pwd)/packages:/packages" \
  -w /packages/wordpress-sdk backend \
  /var/www/html/vendor/bin/phpunit --cache-directory /tmp/pu
```

Or, if you've run `composer install` inside `packages/wordpress-sdk` (its own
`vendor/`, not this monorepo's): `composer test`.

The unit suite runs with **no Composer autoloader and no WordPress**, on purpose:
that is the environment a bundled copy actually runs in. It never touches a
network and needs nothing configured — this is what CI runs, and what
`composer test` (no `:integration` suffix) always means.

Style is **WordPress Coding Standards** (`phpcs.xml.dist`), not this monorepo's
Pint/PSR-12 setup — see that file for the reasoning.

### Integration tests

A second suite (`tests/integration/`) runs the same classes above against a
**real backend** instead of polyfills — the thing worth proving isn't "does
this class behave correctly against a fake HTTP layer" but "does this
client's signature actually verify, does this batch actually get accepted,
does this consent decision actually persist." It's a separate PHPUnit
config, `composer test:integration`, deliberately **not** part of the
default `composer test`:

```bash
# from the repo root, with the dev stack up — packages/ isn't bind-mounted
# into `backend` by default (see Known limitations), so mount it for this
# one run the same way the plain unit-test command above does
docker compose run --rm --no-deps -v "$(pwd)/packages:/packages" \
  -e APPNECK_SDK_TEST_API_KEY=pk_... \
  -e APPNECK_SDK_TEST_PRODUCT_SECRET=sk_... \
  -e APPNECK_SDK_TEST_SECOND_API_KEY=pk_... \
  -e APPNECK_SDK_TEST_SECOND_PRODUCT_SECRET=sk_... \
  -e APPNECK_SDK_TEST_ORGANIZATION_ID=<uuid> \
  -e APPNECK_SDK_TEST_PRODUCT_ID=<uuid> \
  -w /packages/wordpress-sdk backend \
  /var/www/html/vendor/bin/phpunit -c phpunit-integration.xml
```

`APPNECK_SDK_TEST_BASE_URL` defaults to `http://nginx`, the right hostname
from *inside* this Docker network — that's why this runs through
`docker compose run`/`exec` rather than from the host.

Every test **skips cleanly** — not a failure — when it can't run, so this
never breaks `composer test` for a developer without any of this configured;
a plain `composer test:integration` with nothing set reports 7 skipped, 0
failed. Two independent gates, both checked in `setUp()`:

| Env var | Default | What it's for |
|---|---|---|
| `APPNECK_SDK_TEST_BASE_URL` | `http://nginx` | The backend to test against. The default matches this monorepo's own Docker network. |
| `APPNECK_SDK_TEST_API_KEY` / `APPNECK_SDK_TEST_PRODUCT_SECRET` | *(none — required)* | A real product's SDK credentials. Not set → every test skips before touching the network. |
| `APPNECK_SDK_TEST_SECOND_API_KEY` / `APPNECK_SDK_TEST_SECOND_PRODUCT_SECRET` | *(none)* | A **second** product's SDK credentials, with nothing configured on it — used for the cross-product-isolation and zero-announcements/zero-survey cases. Not set → those specific assertions report `markTestIncomplete` rather than being silently skipped whole-test, so a missing second product doesn't quietly hide a real gap. |
| `APPNECK_SDK_TEST_ORGANIZATION_ID` / `APPNECK_SDK_TEST_PRODUCT_ID` | *(none)* | The organization/product ids matching the primary API key above. Needed only by the tests that author real fixtures through the Org Panel API (survey questions, announcements, and licensing's plan + licence) — not set → those tests skip, the rest still run. |
| `APPNECK_SDK_TEST_ORG_EMAIL` / `APPNECK_SDK_TEST_ORG_PASSWORD` | `demo@example.com` / `password` | A real dashboard user, logged in via `POST /app/v1/auth/login` to get a bearer token for authoring those same fixtures. The default is this monorepo's own seeded dev fixture (`DemoSeeder` prints it to the console as seeded, not a secret) — override for anything else. |

**One environment choice, not a per-run manual step:** the primary product
(`APPNECK_SDK_TEST_API_KEY`) should be a product **dedicated to this test
suite** — the survey and announcements tests assert exact counts (`exactly
5 questions`, `exactly the 2 currently-visible announcements`) that would
break against a product carrying real demo/production data alongside the
suite's own fixtures. The second product should stay permanently empty.
Pick these once when setting up an environment; nothing about running the
suite requires touching the Org Panel by hand.

**What used to be manual and no longer is:** every fixture these tests need
— registered installations, consent decisions, survey questions,
announcements — is created in `setUp()` through the real API and removed in
`tearDown()`. The old `tests/integration/*-check.php` scripts these classes
were converted from documented standing preconditions ("author 5
announcements in the Org Panel first," in one case); none of that survived
the conversion. Each test also uses a freshly randomized site domain
(`random_domain()`), so repeated runs — including two CI jobs racing — never
collide on the server's one-installation-per-(site,product) constraint.
