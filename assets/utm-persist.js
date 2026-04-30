/**
 * utm-persist.js
 * Captura UTMs da URL na primeira visita e persiste em cookies + localStorage.
 * Funciona em conjunto com o Pixel da Utmify, mas garante fallback independente.
 *
 * UTMs capturados: utm_source, utm_medium, utm_campaign, utm_content, utm_term, src, sck
 * Persistencia: cookie (30 dias) + localStorage (backup)
 */
;(function() {
  'use strict';

  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'src', 'sck'];
  var COOKIE_DAYS = 30;
  var STORAGE_KEY = '_utms_saved';

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    var secure = location.protocol === 'https:' ? ';Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/' + secure + ';SameSite=Lax';
  }

  function getCookie(name) {
    var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m[2]) : null;
  }

  // Capturar UTMs da URL atual
  var params = new URLSearchParams(window.location.search);
  var hasNewUtms = false;
  var utmValues = {};

  for (var i = 0; i < UTM_KEYS.length; i++) {
    var key = UTM_KEYS[i];
    var val = params.get(key);
    if (val && val.trim() !== '') {
      hasNewUtms = true;
      utmValues[key] = val.trim();
    }
  }

  // Se ha UTMs novos na URL, salvar (sobrescreve os anteriores)
  if (hasNewUtms) {
    for (var key in utmValues) {
      if (utmValues.hasOwnProperty(key)) {
        setCookie(key, utmValues[key], COOKIE_DAYS);
        setCookie('utmify_' + key, utmValues[key], COOKIE_DAYS);
      }
    }
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(utmValues));
    } catch (e) {}
  }

  // Funcao global para recuperar UTMs (querystring > cookie > utmify_cookie > localStorage)
  window.getUtmValue = function(key) {
    // 1. Querystring atual
    var p = new URLSearchParams(window.location.search);
    var v = p.get(key);
    if (v && v.trim() !== '') return v.trim();

    // 2. Cookie direto
    v = getCookie(key);
    if (v && v.trim() !== '') return v.trim();

    // 3. Cookie com prefixo utmify_
    v = getCookie('utmify_' + key);
    if (v && v.trim() !== '') return v.trim();

    // 4. localStorage fallback
    try {
      var saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
      if (saved[key] && saved[key].trim() !== '') return saved[key].trim();
    } catch (e) {}

    return null;
  };

  // Funcao global para obter todos os UTMs de uma vez
  window.getAllUtms = function() {
    var result = {};
    for (var i = 0; i < UTM_KEYS.length; i++) {
      result[UTM_KEYS[i]] = window.getUtmValue(UTM_KEYS[i]);
    }
    return result;
  };

  // Propagar UTMs nas querystrings de links internos (para nao perder entre paginas)
  function propagateUtms() {
    var utms = window.getAllUtms();
    var hasAny = false;
    for (var k in utms) {
      if (utms[k]) { hasAny = true; break; }
    }
    if (!hasAny) return;

    var links = document.querySelectorAll('a[href]');
    for (var i = 0; i < links.length; i++) {
      var link = links[i];
      var href = link.getAttribute('href') || '';
      // So links internos (relativos ou mesmo dominio)
      if (href.indexOf('http') === 0 && href.indexOf(location.hostname) === -1) continue;
      if (href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) continue;

      try {
        var url = new URL(href, location.href);
        var changed = false;
        for (var k in utms) {
          if (utms[k] && !url.searchParams.has(k)) {
            url.searchParams.set(k, utms[k]);
            changed = true;
          }
        }
        if (changed) {
          link.setAttribute('href', url.pathname + url.search + url.hash);
        }
      } catch (e) {}
    }
  }

  // Propagar ao carregar e quando o DOM mudar
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', propagateUtms);
  } else {
    propagateUtms();
  }

  // Re-propagar periodicamente para links dinamicos
  setTimeout(propagateUtms, 2000);
  setTimeout(propagateUtms, 5000);
})();
