(() => {
  const slider = document.querySelector("[data-slider]");

  if (!slider) return;

  const slides = Array.from(slider.querySelectorAll("[data-slide]"));
  const video = slider.querySelector("[data-hero-video]");
  const appUrl = slider.dataset.appUrl?.trim();
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  if (slides.length === 0) return;

  if (appUrl) {
    slider.querySelectorAll("[data-app-link]").forEach((link) => {
      link.href = appUrl;
    });
  }

  let activeIndex = Math.max(
    0,
    slides.findIndex((slide) => slide.classList.contains("is-active")),
  );
  let autoplayTimer = null;
  let focusInside = false;

  function showSlide(index) {
    activeIndex = (index + slides.length) % slides.length;

    slides.forEach((slide, slideIndex) => {
      const isActive = slideIndex === activeIndex;
      const heading = slide.querySelector(".hero-slide__title");

      slide.classList.toggle("is-active", isActive);
      slide.setAttribute("aria-hidden", String(!isActive));
      slide.inert = !isActive;

      if (isActive && heading?.id) {
        slider.setAttribute("aria-labelledby", heading.id);
      }
    });

  }

  function stopAutoplay() {
    if (autoplayTimer !== null) {
      window.clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  }

  function syncAutoplay() {
    const mayAutoplay =
      !reducedMotion.matches &&
      !document.hidden &&
      !focusInside;

    if (!mayAutoplay) {
      stopAutoplay();
      return;
    }

    if (autoplayTimer === null) {
      autoplayTimer = window.setInterval(() => {
        showSlide(activeIndex + 1);
      }, 6000);
    }
  }

  slider.addEventListener("focusin", () => {
    focusInside = true;
    syncAutoplay();
  });

  slider.addEventListener("focusout", (event) => {
    focusInside = slider.contains(event.relatedTarget);
    syncAutoplay();
  });

  document.addEventListener("visibilitychange", syncAutoplay);

  function updateMotionPreference() {
    slider.classList.toggle("hero--reduced-motion", reducedMotion.matches);

    if (!video) return;

    if (reducedMotion.matches) {
      video.pause();
      return;
    }

    const playback = video.play();

    if (playback && typeof playback.catch === "function") {
      playback.catch(() => {
        slider.classList.add("hero--video-unavailable");
      });
    }
  }

  if (video) {
    video.addEventListener("error", () => {
      slider.classList.add("hero--video-unavailable");
    });
  }

  if (typeof reducedMotion.addEventListener === "function") {
    reducedMotion.addEventListener("change", () => {
      updateMotionPreference();
      syncAutoplay();
    });
  } else {
    reducedMotion.addListener(() => {
      updateMotionPreference();
      syncAutoplay();
    });
  }

  showSlide(activeIndex);
  updateMotionPreference();
  syncAutoplay();
})();
