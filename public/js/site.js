/* ia365 ERP landing — progressive enhancement only.
   Content is fully visible without JS; this adds reveal + nav polish. */
(function () {
  "use strict";

  // Mark JS available (CSS keeps .reveal hidden only when JS runs).
  document.documentElement.classList.remove("no-js");

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Scroll reveal: reveal enhances an already-laid-out element.
  var revealables = Array.prototype.slice.call(document.querySelectorAll("[data-reveal]"));
  revealables.forEach(function (el) { el.classList.add("reveal"); });

  if (reduce || !("IntersectionObserver" in window)) {
    revealables.forEach(function (el) { el.classList.add("in"); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var el = entry.target;
          var delay = parseFloat(el.getAttribute("data-reveal-delay") || "0");
          if (delay) { el.style.transitionDelay = delay + "ms"; }
          el.classList.add("in");
          io.unobserve(el);
        }
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.12 });
    revealables.forEach(function (el) { io.observe(el); });
  }

  // Nav: subtle shadow once scrolled.
  var nav = document.querySelector(".nav");
  if (nav) {
    var onScroll = function () {
      nav.style.boxShadow = window.scrollY > 8 ? "0 10px 30px -20px rgba(0,0,0,.8)" : "none";
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }
})();
