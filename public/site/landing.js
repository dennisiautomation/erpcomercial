// ERP Comercial IA365 — landing pública (servida pelo Laravel)
(function () {
  "use strict";
  var root = document.documentElement;
  root.classList.add("js");

  // Ano no rodapé
  var ano = document.getElementById("ano");
  if (ano) ano.textContent = new Date().getFullYear();

  // Sombra/borda na nav ao rolar
  var nav = document.getElementById("nav");
  var onScroll = function () {
    if (!nav) return;
    nav.classList.toggle("scrolled", window.scrollY > 8);
  };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  // Reveal: realça o que já está visível. Respeita prefers-reduced-motion.
  var reveals = document.querySelectorAll(".reveal");
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduce || !("IntersectionObserver" in window)) {
    reveals.forEach(function (el) { el.classList.add("in"); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("in");
          io.unobserve(e.target);
        }
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
    reveals.forEach(function (el) { io.observe(el); });
    window.addEventListener("load", function () {
      setTimeout(function () {
        reveals.forEach(function (el) { el.classList.add("in"); });
      }, 1600);
    });
  }

  // Formulário de demonstração: envia ao backend via fetch e grava o lead.
  var form = document.getElementById("demoForm");
  if (!form) return;

  var endpoint = form.getAttribute("action");
  var tokenMeta = document.querySelector('meta[name="csrf-token"]');
  var token = tokenMeta ? tokenMeta.getAttribute("content") : "";
  var btn = form.querySelector('button[type="submit"]');
  var btnText = btn ? btn.textContent : "";
  var feedback = document.getElementById("formMsg");

  function showMsg(text, ok) {
    if (!feedback) return;
    feedback.textContent = text;
    feedback.className = "form__feedback " + (ok ? "is-ok" : "is-err");
    feedback.hidden = false;
  }

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    if (!form.reportValidity()) return;

    if (btn) { btn.disabled = true; btn.textContent = "Enviando..."; }
    if (feedback) feedback.hidden = true;

    var payload = new FormData(form);

    fetch(endpoint, {
      method: "POST",
      headers: { "X-CSRF-TOKEN": token, "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" },
      body: payload,
    })
      .then(function (r) {
        if (!r.ok) throw new Error("status " + r.status);
        return r.json();
      })
      .then(function (data) {
        showMsg(data.message || "Recebemos seu pedido! Em breve entramos em contato.", true);
        form.reset();
        if (btn) btn.textContent = "Pedido enviado com sucesso";
      })
      .catch(function () {
        showMsg("Não foi possível enviar agora. Tente o WhatsApp abaixo.", false);
        if (btn) { btn.disabled = false; btn.textContent = btnText; }
      });
  });
})();
