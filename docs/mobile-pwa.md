# Mobile / PWA

`/mobile/*` is a dedicated, session-authenticated mobile surface (bottom-nav shell: Home / Scan / Tasks / Stock / More). It reuses desktop's exact Fortify session auth and `ResolveActiveOrganization` tenant resolution; there is no separate mobile auth flow.

## Location memory

- Session key: `mobile_location_by_org` (array keyed by `organization_id => location_id`).
- Resolved by `App\Http\Middleware\ResolveMobileLocation`, applied only to `mobile.*` routes.
- No remembered location yet → hard redirect to `GET /mobile/location?next=<originally requested mobile URL>` before any other `mobile.*` page renders.
- Remembered location later deactivated/deleted → silent fallback to the first active location (alphabetical) plus a flashed toast notice, no redirect to the picker.
- Zero active locations in the organization → `mobileActiveLocation` is `null`; `HomeController` (and `MobileController::requireActiveLocation()`) render a "no locations configured" empty state instead of looping to the picker.

For a support ticket where a user is stuck on the picker or on the wrong location, check `session('mobile_location_by_org')` for their organization ID.

## Scan camera permission troubleshooting

`resources/js/pages/mobile/scan/index.tsx` requests camera access on mount. Two environment issues look identical to "the user tapped Deny" and both fall back to the manual-entry sheet (`resources/js/components/mobile/scanner/manual-entry-sheet.tsx`), which is the intended behavior but worth ruling out during QA:

- **iOS Safari requires HTTPS and an explicit user gesture** before `getUserMedia` will even prompt. A staging/demo environment served over plain HTTP will fail silently into the manual-entry fallback with no permission dialog ever shown.
- **Some Android WebViews used by embedded/kiosk deployments block camera access by policy**, regardless of user consent, and will also fail straight into the fallback.

If Scan never shows a live viewfinder on a specific device, check the origin's scheme (must be `https://`) and whether the surrounding app shell (a WebView wrapper) grants camera permission to its content, before assuming the scanner itself is broken.

`BarcodeDetector` (the native detection engine) is not shipped by iOS Safari as of this repo's target browser matrix; the `@zxing/browser` fallback is the **primary** path on iOS, not a rare fallback, so scan accuracy should be validated on a real iPhone, not just Android.

## Testing install on device

**Android Chrome**: visit `https://<host>/mobile` over HTTPS while logged in. Chrome's install affordance (menu → "Install app", or the `beforeinstallprompt`-driven banner rendered by `resources/js/components/mobile/install-hint.tsx`) becomes available once `manifest.json` and the `/mobile-sw.js` service worker (scope `/mobile/`) are both present. Run a Lighthouse "Installable" audit against `/mobile` to verify.

**iOS Safari**: Safari never fires `beforeinstallprompt`. Use Share → "Add to Home Screen" manually; the installed icon uses `public/icons/mobile-192.png`.

## Regenerating icons

Icons (`public/icons/mobile-192.png`, `mobile-512.png`, `mobile-512-maskable.png`) are generated from `public/favicon.svg` via Imagick. Re-run after changing the source mark:

```bash
php -r '
$root = getcwd();
$svg = file_get_contents($root."/public/favicon.svg");
function renderPng($svg,$size,$bg,$scale,$out){
    $im=new Imagick();$im->setBackgroundColor(new ImagickPixel("transparent"));
    $im->readImageBlob($svg);$im->setImageFormat("png32");
    $logo=(int)round($size*$scale);$im->resizeImage($logo,$logo,Imagick::FILTER_LANCZOS,1,true);
    $c=new Imagick();$c->newImage($size,$size,new ImagickPixel($bg));$c->setImageFormat("png32");
    $off=(int)(($size-$logo)/2);$c->compositeImage($im,Imagick::COMPOSITE_OVER,$off,$off);
    $c->writeImage($out);
}
renderPng($svg,192,"#111827",0.7,$root."/public/icons/mobile-192.png");
renderPng($svg,512,"#111827",0.7,$root."/public/icons/mobile-512.png");
renderPng($svg,512,"#111827",0.5,$root."/public/icons/mobile-512-maskable.png");
'
```
