/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/**
 * tiger.page-builder.js — boots the GrapesJS visual builder for one CMS page and saves
 * through the Tiger /api (Cms_Service_Page::saveDesign). Zero build step: GrapesJS + the
 * webpage preset are vendored UMD bundles. Config arrives on window.TIGER_BUILDER:
 *   { pageId, api, project|null, seedHtml }
 * The canvas restores losslessly from the project blob when present, else it imports the
 * page's current body HTML as a starting point.
 */
(function () {
  'use strict';

  var cfg = window.TIGER_BUILDER || {};
  if (!window.grapesjs || !document.getElementById('gjs')) { return; }

  var preset = window['grapesjs-preset-webpage'];

  var editor = grapesjs.init({
    container: '#gjs',
    height: '100%',
    fromElement: false,
    storageManager: false,            // Tiger owns persistence, via /api
    canvas: { styles: (cfg.canvasStyles || []).concat(cfg.canvasCss || []) },   // base front-end CSS (bootstrap/default/skin/FA) + theme block CSS, injected into the canvas iframe
    // Choosing/uploading an image opens the shared Tiger Media Library (TigerMediaPicker
    // over Media_Service_Media) instead of GrapesJS's default uploader — so page media is
    // real TigerMedia (public URL / CDN), never a base64 blob inlined into the body.
    assetManager: {
      custom: {
        open: function (props) {
          if (!window.TigerMediaPicker) { return; }
          window.TigerMediaPicker.open({
            kind: 'image',
            onSelect: function (item) {
              if (item && item.url) {
                try { editor.AssetManager.add({ src: item.url, name: item.filename }); } catch (e) {}
                try { props.select({ src: item.url, name: item.filename }, true); } catch (e) {}
              }
              if (props && props.close) { props.close(); }
            }
          });
        },
        close: function () {}
      }
    },
    plugins: preset ? [preset] : [],
    pluginsOpts: preset ? { 'grapesjs-preset-webpage': { modalImportTitle: 'Import HTML' } } : {}
  });

  // Tiger additions: a live-rendering Menu component + a Bootstrap 5 block library. Base set —
  // enough to show where this goes (a Divi/Elementor-class kit); dress up later.
  registerMenuComponent(editor);
  registerPartialComponent(editor);
  registerBootstrapBlocks(editor);
  registerVideoPicker(editor);
  registerThemeBlocks(editor);

  // Seed the canvas: prefer the lossless project blob, else import the body HTML.
  try {
    if (cfg.project) {
      editor.loadProjectData(cfg.project);
    } else if (cfg.seedHtml) {
      editor.setComponents(cfg.seedHtml);
    }
  } catch (e) {
    if (cfg.seedHtml) { try { editor.setComponents(cfg.seedHtml); } catch (e2) {} }
  }

  function setStatus(text, cls) {
    var el = document.getElementById('tb-status');
    if (el) { el.textContent = text || ''; el.className = 'tb-status ' + (cls || ''); }
  }

  var saving = false;
  var dirty  = false;
  var suppressUpdate = false;   // set while save() pulls/re-adds the chrome, so that churn isn't "dirty"

  function save() {
    if (saving) { return; }
    saving = true;
    setStatus('Saving…', 'saving');

    // Remove the header/footer preview from ALL saved data (body, css, AND the project blob) — it's
    // view-only chrome, and its deep nesting blows past MariaDB's 32-level JSON limit (the meta
    // json_valid CHECK). Capture with it gone, then re-inject for continued editing. suppressUpdate
    // keeps this churn from marking the doc dirty / flickering the status.
    suppressUpdate = true;
    editor.getWrapper().find('[data-tiger-chrome]').forEach(function (c) { c.remove(); });
    var outHtml    = editor.getHtml();
    var outCss     = editor.getCss();
    var outProject = JSON.stringify(editor.getProjectData());
    tbInjectChrome();
    suppressUpdate = false;

    var body = new URLSearchParams();
    body.set('module', 'cms');
    body.set('service', 'page');
    body.set('method', 'saveDesign');
    body.set('page_id', cfg.pageId || '');
    body.set('html', tbStripChrome(outHtml));   // belt-and-suspenders (chrome already removed above)
    body.set('css', outCss);
    body.set('project', outProject);

    fetch(cfg.api || '/api', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.result) { dirty = false; setStatus('Saved ✓', 'ok'); }
        else { setStatus('Save failed', 'err'); }
      })
      .catch(function () { setStatus('Save failed', 'err'); })
      .finally(function () { saving = false; });
  }

  var saveBtn = document.getElementById('tb-save');
  if (saveBtn) { saveBtn.addEventListener('click', save); }

  // Canvas light/dark — mirror the site's data-bs-theme INSIDE the canvas iframe (Bootstrap reads it
  // on the iframe's <html>; our skins define both modes), toggleable from the top-bar sun/moon.
  var tbMode = 'light';
  // GrapesJS hardcodes a white "paper" bg on the canvas body, which ignores --bs-body-bg — so the body
  // stayed white while token-driven sections went dark. Tie html/body to the Bootstrap body token so
  // it follows the theme (background only forced; text-* utilities keep their own colors).
  function tbApplyCanvasBg() {
    try {
      var doc = editor.Canvas.getDocument();
      if (!doc || doc.querySelector('style[data-tiger="canvas-bg"]')) { return; }
      var st = doc.createElement('style');
      st.setAttribute('data-tiger', 'canvas-bg');
      st.textContent = 'html,body{background-color:var(--bs-body-bg)!important;color:var(--bs-body-color);}';
      (doc.head || doc.documentElement).appendChild(st);
    } catch (e) {}
  }
  function tbCanvasTheme(mode) {
    tbMode = mode;
    try {
      var doc = editor.Canvas.getDocument();
      if (doc && doc.documentElement) { doc.documentElement.setAttribute('data-bs-theme', mode); }
    } catch (e) {}
    var icon = document.querySelector('#tb-theme i');
    if (icon) { icon.className = 'fa-solid ' + (mode === 'dark' ? 'fa-moon' : 'fa-sun'); }
  }
  // Default the canvas to whatever the builder itself is in (theme-init.js set it from the cookie),
  // and re-apply after a device switch (which reloads the canvas frame).
  editor.on('load', function () { tbApplyCanvasBg(); tbCanvasTheme(document.documentElement.getAttribute('data-bs-theme') || 'light'); });
  editor.on('canvas:frame:load', function () { tbApplyCanvasBg(); tbCanvasTheme(tbMode); });
  var themeBtn = document.getElementById('tb-theme');
  if (themeBtn) { themeBtn.addEventListener('click', function () { tbCanvasTheme(tbMode === 'dark' ? 'light' : 'dark'); }); }

  // ---- Non-editable theme chrome: render the real header + footer in the canvas for context
  // (Elementor-style), LOCKED so they can't be edited/moved/deleted, and STRIPPED from the saved
  // body so they never bake into the page (the live layout provides the real header/footer). ----
  function tbLock(c) {
    c.set({ selectable: false, editable: false, hoverable: false, highlightable: false, draggable: false,
            droppable: false, removable: false, copyable: false, layerable: false, badgable: false });
    var kids = c.components && c.components();
    if (kids && kids.forEach) { kids.forEach(tbLock); }
  }
  function tbInjectChrome() {
    var w = editor.getWrapper();
    if (!w) { return; }
    try { w.find('[data-tiger-chrome]').forEach(function (c) { c.remove(); }); } catch (e) {}   // drop any stale copy restored from the project
    try {
      if (cfg.footerHtml) { w.append('<div data-tiger-chrome="footer">' + cfg.footerHtml + '</div>'); }
      if (cfg.headerHtml) { w.append('<div data-tiger-chrome="header">' + cfg.headerHtml + '</div>', { at: 0 }); }
      w.find('[data-tiger-chrome]').forEach(tbLock);
    } catch (e) {}
  }
  function tbStripChrome(html) {
    try {
      var d = new DOMParser().parseFromString('<body>' + (html || '') + '</body>', 'text/html');
      var nodes = d.querySelectorAll('[data-tiger-chrome]');
      for (var i = 0; i < nodes.length; i++) { nodes[i].parentNode.removeChild(nodes[i]); }
      return d.body.innerHTML;
    } catch (e) { return html; }
  }
  editor.on('load', function () { tbInjectChrome(); });

  // Ctrl/Cmd+S saves without leaving the builder.
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') { e.preventDefault(); save(); }
  });

  // Track unsaved edits; warn before closing. GrapesJS fires 'update' after its initial
  // seed, so arm the flag only once the editor has settled.
  editor.on('load', function () {
    setTimeout(function () { editor.on('update', function () { if (suppressUpdate) { return; } dirty = true; setStatus('Unsaved changes', ''); }); }, 400);
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  // ---- the live-rendering Menu component (renders in the canvas, exports the [menu] shortcode) ----
  function registerMenuComponent(editor) {
    var menus = (window.TIGER_BUILDER && window.TIGER_BUILDER.menus) || {};
    var names = Object.keys(menus);

    editor.Components.addType('tiger-menu', {
      isComponent: function (el) { return el && el.getAttribute && el.getAttribute('data-tiger-menu') !== null; },
      model: {
        defaults: {
          menuKey: names[0] || 'primary',
          draggable: true, droppable: false, editable: false, highlightable: true,
          attributes: { 'data-tiger-menu': '' },
          traits: [{
            type: names.length ? 'select' : 'text', name: 'menuKey', label: 'Menu', changeProp: 1,
            options: names.map(function (n) { return { id: n, name: n }; })
          }]
        },
        init: function () { this.on('change:menuKey', function () { if (this.view) { this.view.render(); } }); },
        // Export the SHORTCODE, not the preview — the menu stays dynamic + auth-filtered at view time.
        toHTML: function () { return '[menu name="' + (this.get('menuKey') || 'primary') + '"]'; }
      },
      view: {
        onRender: function () {
          var key = this.model.get('menuKey') || 'primary';
          var m = (window.TIGER_BUILDER && window.TIGER_BUILDER.menus) || {};
          var inner = (m[key] != null && m[key] !== '')
            ? '<nav class="tb-menu-preview">' + m[key] + '</nav>'
            : '<div class="p-2 small text-muted border rounded bg-light">[menu name="' + key + '"] — no preview</div>';
          // pointer-events off on the PREVIEW only — so GrapesJS can still hover/select/move the component.
          this.el.innerHTML = '<div style="pointer-events:none;">' + inner + '</div>';
        }
      }
    });
  }

  // The PARTIAL widget: drop it, pick a named partial (a type=partial page), it renders a LIVE preview
  // and exports [partial name="x"] — dynamic at view time, so editing the partial updates everywhere.
  // The composition primitive for "everything is a partial" (header/footer/hero/section/…). Twin of the
  // Menu widget above.
  function registerPartialComponent(editor) {
    var partials = (window.TIGER_BUILDER && window.TIGER_BUILDER.partials) || {};
    var names = Object.keys(partials);

    editor.Components.addType('tiger-partial', {
      isComponent: function (el) { return el && el.getAttribute && el.getAttribute('data-tiger-partial') !== null; },
      model: {
        defaults: {
          partialName: names[0] || '',
          draggable: true, droppable: false, editable: false, highlightable: true,
          attributes: { 'data-tiger-partial': '' },
          traits: [{
            type: names.length ? 'select' : 'text', name: 'partialName', label: 'Partial', changeProp: 1,
            options: names.map(function (n) { return { id: n, name: (partials[n] && partials[n].label) || n }; })
          }]
        },
        init: function () { this.on('change:partialName', function () { if (this.view) { this.view.render(); } }); },
        // Export the SHORTCODE, not the preview — the partial stays dynamic + editable in its own editor.
        toHTML: function () { return '[partial name="' + (this.get('partialName') || '') + '"]'; }
      },
      view: {
        onRender: function () {
          var name = this.model.get('partialName') || '';
          var p = (window.TIGER_BUILDER && window.TIGER_BUILDER.partials) || {};
          var html = (p[name] && p[name].html != null) ? p[name].html : '';
          var inner = (html !== '')
            ? html
            : '<div class="p-3 small text-muted border rounded bg-light">[partial name="' + (name || '?') + '"] — pick a partial in the trait panel</div>';
          // pointer-events off on the PREVIEW only — GrapesJS keeps hover/select/move/delete on the widget.
          this.el.innerHTML = '<div style="pointer-events:none;">' + inner + '</div>';
        }
      }
    });
  }

  // ---- Bootstrap 5 block library (base set — dress up later) ----
  function registerBootstrapBlocks(editor) {
    var bm = editor.BlockManager;
    var add = function (id, label, category, content, icon) {
      bm.add(id, { label: label, category: category, content: content,
        media: '<i class="fa-solid ' + (icon || 'fa-cube') + '"></i>' });
    };
    var cols = function (n) {
      var out = '';
      for (var i = 0; i < n; i++) { out += '<div class="col-md-' + (12 / n) + '"><p>Column</p></div>'; }
      return '<div class="row g-4">' + out + '</div>';
    };

    add('tb-section', 'Section', 'Layout', '<section class="py-5"><div class="container"><h2>Section title</h2><p>Section content…</p></div></section>', 'fa-square');
    add('tb-container', 'Container', 'Layout', '<div class="container py-3"><p>Container…</p></div>', 'fa-box');
    add('tb-row2', '2 Columns', 'Layout', cols(2), 'fa-table-columns');
    add('tb-row3', '3 Columns', 'Layout', cols(3), 'fa-table-columns');

    add('tb-heading', 'Heading', 'Content', '<h2>Heading</h2>', 'fa-heading');
    add('tb-text', 'Text', 'Content', '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>', 'fa-align-left');
    // Image → the native GrapesJS image component; `activate` fires its asset manager on drop, which
    // Tiger routes to TigerMediaPicker (see the assetManager.custom config) — so you drop Image and the
    // Media Library opens immediately. `select` leaves the new image selected for styling.
    bm.add('tb-image', { label: 'Image', category: 'Content', activate: true, select: true,
      media: '<i class="fa-solid fa-image"></i>', content: { type: 'image' } });
    // Video → the native GrapesJS video component (HTML5 file URL, YouTube, or Vimeo via its traits).
    bm.add('tb-video', { label: 'Video', category: 'Content', select: true,
      media: '<i class="fa-solid fa-film"></i>', content: { type: 'video' } });
    add('tb-button', 'Button', 'Content', '<a href="#" class="btn btn-primary">Button</a>', 'fa-hand-pointer');
    add('tb-buttons', 'Buttons', 'Content', '<div class="d-inline-flex gap-2"><a href="#" class="btn btn-primary">Primary</a> <a href="#" class="btn btn-outline-secondary">Secondary</a></div>', 'fa-hand-pointer');
    add('tb-list', 'List', 'Content', '<ul><li>One</li><li>Two</li><li>Three</li></ul>', 'fa-list');
    add('tb-divider', 'Divider', 'Content', '<hr>', 'fa-minus');

    add('tb-card', 'Card', 'Components', '<div class="card" style="max-width:22rem"><div class="card-body"><h5 class="card-title">Card title</h5><p class="card-text">Some quick example text to build on.</p><a href="#" class="btn btn-primary">Go</a></div></div>', 'fa-address-card');
    add('tb-cards', 'Card grid', 'Components', '<div class="row g-4">' + [1, 2, 3].map(function () { return '<div class="col-md-4"><div class="card h-100"><div class="card-body"><h5 class="card-title">Card</h5><p class="card-text">Text.</p></div></div></div>'; }).join('') + '</div>', 'fa-grip');
    add('tb-alert', 'Alert', 'Components', '<div class="alert alert-primary" role="alert">A simple primary alert.</div>', 'fa-circle-exclamation');
    add('tb-badge', 'Badge', 'Components', '<span class="badge text-bg-primary">Badge</span>', 'fa-certificate');
    add('tb-hero', 'Hero', 'Components', '<section class="py-5 text-center bg-body-tertiary"><div class="container"><h1 class="display-5 fw-bold">Hero headline</h1><p class="lead">A short supporting line for the hero.</p><a href="#" class="btn btn-primary btn-lg">Call to action</a></div></section>', 'fa-panorama');
    add('tb-carousel', 'Carousel', 'Components', carouselHtml(), 'fa-images');
    add('tb-accordion', 'Accordion', 'Components', accordionHtml(), 'fa-bars-staggered');

    bm.add('tb-menu', { label: 'Menu', category: 'Components', media: '<i class="fa-solid fa-bars"></i>', content: { type: 'tiger-menu' } });
    bm.add('tb-partial', { label: 'Partial', category: 'Components', media: '<i class="fa-solid fa-puzzle-piece"></i>', content: { type: 'tiger-partial' } });
  }

  // ---- Video ↔ Media Library. The native video component takes its src from a trait, not the
  // asset manager, so we bridge it: a command opens TigerMediaPicker (kind:video) and applies the
  // pick as an HTML5 source. Fires on drop (like Image) and from a component toolbar button. ----
  function registerVideoPicker(editor) {
    editor.Commands.add('tiger-video-pick', {
      run: function (ed, sender, options) {
        var target = (options && options.target) || ed.getSelected();
        if (!target || !window.TigerMediaPicker) { return; }
        window.TigerMediaPicker.open({
          kind: 'video',
          title: 'Select a video',
          onSelect: function (item) {
            if (item && item.url) { target.set({ provider: 'so', src: item.url }); }   // 'so' = HTML5 <source> (an uploaded file)
          }
        });
      }
    });

    // A toolbar button on any selected video component → (re)pick from the library.
    editor.on('component:selected', function (component) {
      if (!component || component.get('type') !== 'video') { return; }
      var tb = (component.get('toolbar') || []).slice();
      if (tb.some(function (t) { return t.command === 'tiger-video-pick'; })) { return; }
      tb.unshift({ attributes: { class: 'fa-solid fa-photo-film', title: 'Choose video from the Media Library' }, command: 'tiger-video-pick' });
      component.set('toolbar', tb);
    });

    // Dropping the Video block opens the picker immediately (only a fresh, src-less drop).
    editor.on('block:drag:stop', function (component) {
      if (component && component.get && component.get('type') === 'video' && !component.get('src')) {
        editor.runCommand('tiger-video-pick', { target: component });
      }
    });
  }

  // ---- theme-provided blocks: the ACTIVE theme's components/*.phtml (Tiger_Theme::components) ----
  // Passed on window.TIGER_BUILDER.themeBlocks by the CMS designAction; each drops the vendor's own
  // markup, and the canvas CSS (cfg.canvasCss) makes it preview in the theme's style.
  function registerThemeBlocks(editor) {
    var blocks = (window.TIGER_BUILDER && window.TIGER_BUILDER.themeBlocks) || [];
    if (!blocks.length) { return; }
    var bm = editor.BlockManager;
    blocks.forEach(function (b) {
      bm.add('theme-' + b.id, {
        label:    b.label,
        category: b.category || 'Theme',
        media:    b.media ? '<i class="fa-solid ' + b.media + '"></i>' : '<i class="fa-solid fa-cube"></i>',
        content:  b.content
      });
    });
  }

  // Bootstrap markup helpers (fixed ids — one carousel/accordion per page for now; base set).
  function carouselHtml() {
    var slide = function (active, n) {
      return '<div class="carousel-item' + (active ? ' active' : '') + '"><div class="ratio ratio-21x9 bg-secondary-subtle d-flex align-items-center justify-content-center text-muted">Slide ' + n + '</div></div>';
    };
    return '<div id="tbCarousel" class="carousel slide" data-bs-ride="carousel"><div class="carousel-inner">' +
      slide(true, 1) + slide(false, 2) + slide(false, 3) + '</div>' +
      '<button class="carousel-control-prev" type="button" data-bs-target="#tbCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>' +
      '<button class="carousel-control-next" type="button" data-bs-target="#tbCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button></div>';
  }
  function accordionHtml() {
    var item = function (i, show) {
      return '<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button' + (show ? '' : ' collapsed') + '" type="button" data-bs-toggle="collapse" data-bs-target="#tbAcc' + i + '">Item ' + i + '</button></h2>' +
        '<div id="tbAcc' + i + '" class="accordion-collapse collapse' + (show ? ' show' : '') + '" data-bs-parent="#tbAccordion"><div class="accordion-body">Content for item ' + i + '.</div></div></div>';
    };
    return '<div class="accordion" id="tbAccordion">' + item(1, true) + item(2, false) + '</div>';
  }

  window.TigerBuilder = { editor: editor, save: save };
})();
