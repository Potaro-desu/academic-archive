# Motion map, v1.3

Anime.js 4.5.0 is served from
`assets/vendor/anime/anime.umd.min.js`. The local script is loaded before
`assets/js/app.js`; production markup does not use a CDN.

All animation calls are guarded by `window.anime` and the
`prefers-reduced-motion` media query. Starting values are applied at runtime
only. There are no CSS-hidden animation targets, so content remains visible
with JavaScript disabled or if the library is missing. Anime.js 4 names the
requested cubic and exponential easings `outCubic` and `outExpo`.

| Element | Trigger | Motion | Duration | Easing |
|---|---|---|---:|---|
| Dashboard eyebrow, heading, intro and action | Initial dashboard load | Fade and rise in order, 60ms delay steps | 450ms each | `outCubic` |
| Dashboard and submission totals | Initial load | Count from zero to the real value | 600ms | `outCubic` |
| Dashboard stats, cards, menu tiles | Initial dashboard load only | Fade and rise, 50ms stagger | 450ms | `outCubic` |
| Public catalog cards | Initial load and after fetched results render | Fade and rise, 40ms stagger; never while typing | 400ms | `outCubic` |
| Review action card | Successful async review | Fade and slide out, after the server response | 300ms | `outCubic` |
| Favorite control | Successful-intent submit, before the existing form post | Scale 1 to 1.35 to 1 | 300ms | `outExpo` |
| Flash message | Initial page load | Fade and slide down once | 300ms | `outCubic` |
| Role menu | Each open | Fade and rise | 250ms | `outCubic` |
| Buttons and links | Pointer active state | Scale to 0.97 | 120ms CSS transform only | CSS ease |
| Search status and submit button | While async work is pending | Gentle opacity pulse, stopped on success or error | 500ms cycle | `inOutSine` |
| Detail preview | Initial detail page load | Fade in | 400ms | `outCubic` |

Only transform and opacity are animated. No animation changes layout
dimensions. Existing async submission, review and favorite requests continue
without animation when reduced motion is enabled or Anime.js is unavailable.
CSS transitions are short and disabled in the reduced-motion media query.
