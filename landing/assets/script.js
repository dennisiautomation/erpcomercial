// ERP Comercial IA365 — landing interactions
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
    // Failsafe: se algo não disparar (aba em segundo plano), mostra tudo.
    window.addEventListener("load", function () {
      setTimeout(function () {
        reveals.forEach(function (el) { el.classList.add("in"); });
      }, 1600);
    });
  }

  // Formulário de demonstração: monta a mensagem e abre WhatsApp (sem backend).
  var form = document.getElementById("demoForm");
  if (form) {
    var WHATS = "5511917120940"; // trocar pelo número comercial real
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      if (!form.reportValidity()) return;

      var data = new FormData(form);
      var msg =
        "Olá! Quero agendar uma demonstração do ERP Comercial.\n\n" +
        "Nome: " + (data.get("nome") || "") + "\n" +
        "Empresa: " + (data.get("empresa") || "") + "\n" +
        "E-mail: " + (data.get("email") || "") + "\n" +
        "WhatsApp: " + (data.get("whats") || "") + "\n" +
        "Lojas: " + (data.get("lojas") || "");

      var url = "https://wa.me/" + WHATS + "?text=" + encodeURIComponent(msg);

      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.textContent = "Abrindo o WhatsApp...";
        btn.disabled = true;
      }
      window.open(url, "_blank", "noopener");
      setTimeout(function () {
        if (btn) {
          btn.textContent = "Pedido enviado! Veja o WhatsApp";
          btn.disabled = false;
        }
      }, 1200);
    });
  }
})();
