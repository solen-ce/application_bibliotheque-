# Library Hero Implementation Plan


## Global Constraints

- Use the approved palette: ivory `#F7F4ED`, deep green `#24483F`, terracotta `#C87552`, and anthracite text.
- Keep the same video running behind all slides; each slide text changes with a fade and slight vertical movement.
- Respect `prefers-reduced-motion`; the slider must remain usable without autoplay or animation.
- Keep the Hero readable, responsive, keyboard-operable, and usable when video playback is unavailable.
- Do not invent a real application URL; keep its link easy to configure.

---

### Task 1: Create the page structure and visual treatment

**Files:**
- Create: `index.html`
- Create: `style.css`

**Interfaces:**
- `index.html` loads `style.css` and `script.js` from the same directory.
- `index.html` references `factsoflife123_pindown.io_1791492361.mp4` as a muted, looping, inline, decorative video and `pile livre.jpe` as its poster. JavaScript starts video playback only when reduced motion is not requested.
- The page exposes three slides, catalogue/application CTA links, previous/next buttons, and position indicators.

- [ ] **Step 1: Create semantic markup**
  Add a French document with responsive viewport metadata, a simple library wordmark/navigation, and one full-viewport Hero. Give the Hero a `<video>` with `src="factsoflife123_pindown.io_1791492361.mp4"`, `muted loop playsinline`, `aria-hidden="true"`, and `poster="pile livre.jpe"`; JavaScript starts playback only when reduced motion is not requested. Layer a contrast overlay above it. Add the three exact headline/supporting-copy pairs from the design spec, two CTA links, accessible previous/next buttons, and three labeled slide-selection buttons. Mark slide state with `aria-hidden` and the active state; keep controls outside the changing copy so focus does not jump.
- [ ] **Step 2: Style the static page**
  Implement the approved colors and serif/sans-serif pairing with system font fallbacks. Use a responsive, centered content width, high-contrast gradient over the cover-cropped video, a primary filled CTA and secondary outlined CTA, visible keyboard focus, and a restrained active-slide opacity/translate transition. On narrow screens, stack the copy and controls without horizontal overflow. Use `pile livre.jpe` as the poster when video is unavailable or disabled.
- [ ] **Step 3: Inspect markup and CSS**
  Open `index.html` in a browser at desktop and mobile widths. Confirm the video stays behind the text, all three slides are represented in the document, and the CTA/control hierarchy remains legible over bright and dark frames.

### Task 2: Implement slider behavior and verify accessibility

**Files:**
- Create: `script.js`
- Modify: `index.html`
- Modify: `style.css`

**Interfaces:**
- `script.js` initializes the Hero slider after DOM readiness, finds slides and controls by data attributes, and updates the active slide and indicators.
- The three indicator buttons select slides directly; previous/next wrap around.
- The slider advances every 6000 ms only when motion is permitted and the page is visible. Focus, hover, or a manual control action pauses automatic advancement.

- [ ] **Step 1: Add stable data hooks and motion styling**
  Add `data-slide`, `data-slide-to`, `data-slider-prev`, and `data-slider-next` attributes. Define the active/inactive transition states in CSS and a `prefers-reduced-motion: reduce` override that removes movement and animation.
- [ ] **Step 2: Implement navigation and autoplay**
  In `script.js`, initialize only when the Hero and all required controls exist; switch the active slide and indicator with `aria-hidden`, `aria-current`, and an active class; implement previous/next wrapping and direct selection; advance at 6000 ms; stop slider autoplay and video playback for reduced-motion preference; pause slider autoplay for hidden documents, keyboard focus, pointer hover, or after manual interaction. Guard against starting multiple timers and clear the timer on pause.
- [ ] **Step 3: Verify behavior in a browser**
  Load `index.html`; verify initial slide, next/previous wrapping, direct selection, automatic advance after six seconds, no timer while the tab is hidden, and pausing when focus enters the controls. Emulate reduced motion and verify no automatic changes or animated movement. Disable or block the video and verify the poster/background and all text remain usable. Check keyboard-only operation, visible focus, and narrow/mobile layout; correct any console errors.
