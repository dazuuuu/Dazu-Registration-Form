/**
 * Pentagon UI ↔ Dazu Registration-System backend bridge.
 *
 * Loaded on static Pentagon HTML pages after sync. Wires:
 * - Contact / Elementor forms → POST /api/contact.php
 * - Package grids → GET /api/packages.php
 * - Offer carousels → GET /api/offers.php
 * - Contact details → GET /api/site/contact.php
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var API_BASE = (script && script.getAttribute('data-api-base')) || detectApiBase();

  function detectApiBase() {
    var meta = document.querySelector('meta[name="pentagon-api-base"]');
    if (meta && meta.content) return meta.content.replace(/\/$/, '');
    var path = window.location.pathname.replace(/\/[^/]*$/, '');
    if (/\/public$/i.test(path)) return path + '/api';
    return '/api';
  }

  function apiUrl(endpoint) {
    return API_BASE.replace(/\/$/, '') + '/' + endpoint.replace(/^\//, '');
  }

  async function apiGet(endpoint) {
    var res = await fetch(apiUrl(endpoint), { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  async function apiPost(endpoint, data) {
    var res = await fetch(apiUrl(endpoint), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    });
    return res.json();
  }

  function showFormMessage(form, message, isError) {
    var existing = form.querySelector('.pentagon-form-message');
    if (existing) existing.remove();
    var el = document.createElement('div');
    el.className = 'pentagon-form-message';
    el.setAttribute('role', 'alert');
    el.style.cssText = 'margin:1rem 0;padding:1rem 1.25rem;border-radius:8px;font-family:Poppins,sans-serif;font-size:14px;' +
      (isError ? 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;' : 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;');
    el.textContent = message;
    form.appendChild(el);
  }

  function ensurePhoneField(form) {
    if (form.querySelector('[name="phone"], [name="form_fields[phone]"], [name="form_fields[tel]"]')) return;
    var wrapper = form.querySelector('.elementor-form-fields-wrapper');
    if (!wrapper) return;
    var group = document.createElement('div');
    group.className = 'elementor-field-group elementor-column elementor-col-100';
    group.innerHTML =
      '<label for="pentagon-phone" class="elementor-field-label elementor-screen-only">Phone</label>' +
      '<input type="tel" name="form_fields[phone]" id="pentagon-phone" class="elementor-field elementor-size-sm elementor-field-textual" placeholder="Phone / WhatsApp" required>';
    var submitGroup = wrapper.querySelector('.e-form__buttons');
    if (submitGroup) wrapper.insertBefore(group, submitGroup);
    else wrapper.appendChild(group);
  }

  function readFormPayload(form) {
    var fd = new FormData(form);
    var payload = { name: '', email: '', phone: '', service: '', message: '' };
    fd.forEach(function (value, key) {
      var v = String(value).trim();
      if (!v) return;
      if (key === 'form_fields[name]' || key === 'name') payload.name = v;
      else if (key === 'form_fields[email]' || key === 'email') payload.email = v;
      else if (key.indexOf('phone') !== -1 || key.indexOf('tel') !== -1) payload.phone = v;
      else if (key.indexOf('field_c4ab847') !== -1 || key.indexOf('subject') !== -1) payload.service = v;
      else if (key.indexOf('message') !== -1) payload.message = v;
    });
    return payload;
  }

  function wireContactForms() {
    document.querySelectorAll('form.elementor-form').forEach(function (form) {
      if (form.dataset.pentagonWired) return;
      form.dataset.pentagonWired = '1';
      ensurePhoneField(form);
      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        e.stopPropagation();
        var btn = form.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.dataset.origText = btn.textContent; btn.textContent = 'Sending…'; }
        try {
          var payload = readFormPayload(form);
          var result = await apiPost('contact.php', payload);
          if (result.success) {
            showFormMessage(form, result.message || 'Thank you! We will get back to you soon.', false);
            form.reset();
          } else {
            showFormMessage(form, result.message || 'Could not send message. Please try again.', true);
          }
        } catch (err) {
          showFormMessage(form, 'Network error. Please call us or try again later.', true);
        } finally {
          if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origText || 'Send'; }
        }
      }, true);
    });
  }

  function renderPackageCard(pkg) {
    var a = document.createElement('a');
    a.href = pkg.url || '#';
    a.className = 'pentagon-package-card';
    a.style.cssText = 'display:block;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 20px rgba(0,0,0,.08);text-decoration:none;color:inherit;transition:transform .3s ease;';
    a.innerHTML =
      '<img src="' + esc(pkg.image) + '" alt="" style="width:100%;height:200px;object-fit:cover" loading="lazy">' +
      '<div style="padding:1.25rem;font-family:Poppins,sans-serif">' +
        '<div style="font-size:11px;color:#D4AF37;font-weight:700;text-transform:uppercase;letter-spacing:.08em">' + esc(pkg.duration) + '</div>' +
        '<h3 style="margin:.5rem 0;font-size:1.1rem;color:#0A4322">' + esc(pkg.title) + '</h3>' +
        '<p style="font-size:13px;color:#666;line-height:1.5;margin:0 0 .75rem">' + esc(pkg.description).slice(0, 120) + '…</p>' +
        '<strong style="color:#228B22">' + esc(pkg.price_label || ('KES ' + Number(pkg.price).toLocaleString())) + '</strong>' +
      '</div>';
    a.addEventListener('mouseenter', function () { a.style.transform = 'translateY(-5px)'; });
    a.addEventListener('mouseleave', function () { a.style.transform = ''; });
    return a;
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  async function loadPackages() {
    var container = document.getElementById('packages-container') || document.getElementById('pentagon-packages-grid');
    if (!container) return;
    container.innerHTML = '<p style="text-align:center;padding:2rem;color:#888">Loading courses…</p>';
    try {
      var res = await apiGet('packages.php?limit=12');
      var items = res.data || [];
      container.innerHTML = '';
      if (!items.length) {
        container.innerHTML = '<p style="text-align:center;padding:2rem">No courses available yet.</p>';
        return;
      }
      var grid = document.createElement('div');
      grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;padding:1rem 0';
      items.forEach(function (pkg) { grid.appendChild(renderPackageCard(pkg)); });
      container.appendChild(grid);
    } catch (e) {
      container.innerHTML = '<p style="text-align:center;padding:2rem;color:#c00">Could not load courses.</p>';
    }
  }

  async function loadOffers() {
    var container = document.getElementById('pentagon-offers-grid') || document.getElementById('offers-container');
    if (!container) return;
    try {
      var res = await apiGet('offers.php?limit=6');
      var items = res.data || [];
      if (!items.length) return;
      container.innerHTML = '';
      var grid = document.createElement('div');
      grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.25rem';
      items.forEach(function (pkg) { grid.appendChild(renderPackageCard(pkg)); });
      container.appendChild(grid);
    } catch (e) { /* optional section */ }
  }

  async function hydrateContactInfo() {
    try {
      var res = await apiGet('site/contact.php');
      var d = res.data || {};
      document.querySelectorAll('[data-pentagon-phone]').forEach(function (el) {
        el.textContent = d.phone || el.textContent;
        if (el.tagName === 'A' && d.phone_tel) el.href = 'tel:' + d.phone_tel;
      });
      document.querySelectorAll('[data-pentagon-email]').forEach(function (el) {
        el.textContent = d.email || el.textContent;
        if (el.tagName === 'A') el.href = 'mailto:' + d.email;
      });
      document.querySelectorAll('[data-pentagon-address]').forEach(function (el) {
        el.textContent = d.address || el.textContent;
      });
      if (d.whatsapp) {
        document.querySelectorAll('a[href*="wa.me"], .joinchat__button').forEach(function (el) {
          if (el.href && el.href.indexOf('wa.me') !== -1) el.href = 'https://wa.me/' + d.whatsapp;
        });
      }
    } catch (e) { /* use static HTML fallbacks */ }
  }

  function init() {
    wireContactForms();
    loadPackages();
    loadOffers();
    hydrateContactInfo();
    console.info('[Pentagon Bridge] API base:', API_BASE);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
