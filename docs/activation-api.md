# Online activation API v1 (preview)

This contract is implemented by Laravel and tested using HTTP, Livewire and independent PHP workers against PostgreSQL. No JUCE client, signed certificate, offline authorization, payment flow or production signing key exists yet. Use HTTPS outside local development. Examples below are schematic placeholders and contain no usable credentials.

## Product and installation identity

Each product has a stable UUID and unique slug. Routes use the slug (for example `rhythm-conductor`); responses also return its UUID. Display names can change. There is no global device quota: each license has its own `max_activations`, installations and entitlements. One customer can own several products or several licenses of one product. The authorization code selects one exact license; the plugin cannot choose another by supplying IDs.

Client contract for `machine_hash`:

1. Generate a cryptographically random UUID once for the local installation. Persist it across plugin launches and updates. Decide with the JUCE integration which local state is shared between plugin instances and products.
2. Lowercase the canonical hyphenated UUID, concatenate ASCII `soundmine-installation:v1:` followed by that UUID, and SHA-256 those exact UTF-8 bytes. Send the lowercase 64-character hexadecimal digest.
3. Show this digest for the customer to enter in the portal alongside `macos` or `windows`. Never send raw hardware identifiers. Reinstalling without restoring the installation UUID produces a new installation and may require a free seat.

The server validates the digest's shape, not its origin. This identifies persisted client state; it does not authenticate hardware or resist copied installation state. The bearer token provides authorization. The same hash across products is allowed and does not share rights. Changing platform for an existing hash is rejected.

## Obtain authorization

Sign in as the verified customer, open the desired license in `/portal`, enter the hash/platform and choose **Generate authorization code**. The license must be active, within its validity interval and attached to an active product. Only its verified owner can issue a code, including when an administrator is signed in. Issuance is limited to 20 attempts per customer per minute.

The `sm_auth_` code expires ten minutes after issuance. It is bound to customer, license, product, hash and platform. Issuing a code does not reserve a seat. Share it only with the intended local plugin. The database retains its SHA-256 digest, never the code itself.

## Exchange a code

`POST /api/v1/products/{slug}/activations`

Headers:

```http
Content-Type: application/json
Accept: application/json
Idempotency-Key: <random UUID generated once for this exchange>
```

Body (JSON only):

```json
{
  "authorization_code": "<sm_auth_ plus 64 alphanumeric characters>",
  "machine_hash": "<64 lowercase hexadecimal characters>",
  "platform": "macos",
  "plugin_version": "1.0.0"
}
```

`plugin_version` is optional, nullable, at most 64 characters. Header values supply idempotency; a body `request_id` is ignored. Customer/license/status fields cannot select or modify a grant. A new installation returns 201; reuse, reactivation and exact retry return 200:

```json
{
  "data": {
    "product": {"id": "<UUID>", "slug": "rhythm-conductor", "name": "Rhythm Conductor"},
    "license": {"id": "<UUID>", "status": "active", "starts_at": "2026-11-17T00:00:00+00:00", "expires_at": null, "max_activations": 2},
    "activation": {"id": "<UUID>", "status": "active", "platform": "macos"},
    "entitlements": {"edition": "full", "formats": ["vst3"]}
  },
  "installation_token": "<sm_install_ plus 64 alphanumeric characters>",
  "token_expires_at": "2026-12-17T00:00:00+00:00"
}
```

Entitlements preserve their JSON types and are always a JSON object, including `{}` when empty. Manual admin grants currently accept string values. There is no global meaning assigned to entitlement keys; interpret them within the returned product. The response excludes customer identity and machine hash.

Persist the request UUID and normalized payload until the response is safely stored. If a response is lost, resend the same code, header and payload before code expiry. That exact retry returns the same activation and bearer token, with current rights. Changed payload/header on a consumed code returns 409. Missing and null `plugin_version` are equivalent. Idempotency is scoped to the authorization code; it is not a global cache keyed only by the UUID.

A failed quota or validation request does not consume the authorization. A different valid authorization for an already active installation rotates its bearer token without allocating another seat. The earlier token and earlier retry authorization cannot recover the rotated credential. Deactivated installations can reuse their record when there is a seat; revoked installations cannot reactivate.

Tokens expire after 30 days or at license expiry, whichever is sooner. Renew by obtaining a fresh portal code for the same hash/platform. There is no dedicated refresh endpoint. Store tokens in the operating system's secure credential storage, never logs, URLs or public plugin settings. Do not embed a shared server secret in the plugin.

## Read current rights

`GET /api/v1/products/{slug}/entitlements`

```http
Authorization: Bearer <installation_token>
Accept: application/json
```

Returns 200 with the same `data` shape, excluding token fields. Every call checks product and customer scope, token expiry/revocation, active installation, current verified customer, active product, active license and validity dates. Successful reads update `last_seen_at`. Session cookies, machine hashes and query tokens do not authorize this endpoint.

Suspending/revoking a license, disabling its product or removing customer verification denies subsequent online reads. Resuming valid state can restore access while the token is still valid. Expired tokens remain allocated installations; expiry does not silently free a seat. Product/customer reassignment invalidates credentials tied to the former scope.

## Release an installation

`DELETE /api/v1/products/{slug}/activations/{activation_UUID}`

Requires the same bearer header. Only that token's activation may be released. Returns 204, marks it deactivated and revokes its live tokens. A seat becomes available atomically. Records are retained.

An unexpired token revoked by that deactivation may repeat DELETE while its installation remains deactivated, returning 204. It cannot read entitlements. After reactivation, the old token cannot deactivate the new session. Rotated, expired and otherwise revoked tokens cannot use this exception. A valid scoped token can release a seat even if the license is now inactive/expired or its product disabled. Customer/product scope still must match. Lost-token/lost-device recovery and a customer installation-management UI are not implemented.

## Errors, limits and operations

Expected errors use:

```json
{"error": {"code": "activation_limit_reached", "message": "This license has reached its device allowance."}}
```

| HTTP | Codes / meaning |
| --- | --- |
| 401 | `invalid_authorization`, `invalid_installation_token` (including expiry, revoked credential or changed ownership) |
| 403 | `installation_mismatch`, `installation_unavailable`, `installation_inactive`, `customer_unverified`, `product_unavailable`, `license_inactive`, `license_not_started`, `license_expired` |
| 404 | `resource_not_found` for unknown routes/products, wrong product scope or another installation |
| 409 | `activation_limit_reached`, `authorization_consumed`, `authorization_superseded` |
| 415 | `unsupported_media_type` |
| 422 | `validation_failed`, with an additional field-keyed `errors` object |
| 429 | `rate_limited`, with `Retry-After` |
| 503 | `activation_unavailable` when the preview is disabled |

Exchange requests are limited to 30 per IP per minute. Installed API calls are limited to 300 per IP and 120 per hashed bearer per minute. Configure trusted proxies correctly before external use and review these preview limits with real client traffic. Retry 429 after `Retry-After`; preserve exchange identity for transient retries. Obtain new customer authorization after code/token expiry or rotation. Do not retry permanent authorization failures indefinitely.

Product API responses use `Cache-Control: no-store, private`. The status endpoint reports online activation separately from complete signed licensing. Set `SOUNDMINE_ONLINE_ACTIVATION_ENABLED=false` to deny the online API and code issuance. No flag enables missing signing/offline functionality.

The license row lock serializes seat allocation, reuse, deactivation and license status changes. All credential actions recheck state inside transactions. Raw secrets are hidden from model serialization. To support lost-response retries, the exchange token is also temporarily encrypted on its authorization using Laravel's encryption key. Never expose that key. Expired codes stop working immediately; the hourly scheduled `model:prune` task deletes expired authorization rows and the encrypted retry material. Run Laravel's scheduler, or invoke the documented pruning command manually. Token digests and installation history are retained. Backups may retain encrypted material according to their own retention policy.

This preview does not supply offline rights, certificate verification, device attestation, automatic token refresh, account-wide device recovery or a JUCE implementation. Those require separate client and operational work before commercial rollout.
