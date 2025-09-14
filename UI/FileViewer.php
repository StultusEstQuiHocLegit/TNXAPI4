<?php
require_once('header.php');
require_once('../config.php');

$table = $_GET['table'] ?? '';
$idpk  = $_GET['idpk'] ?? '';
$file  = $_GET['file'] ?? '';
$ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$originEntry = isset($_GET['OriginEntry']);

$backLink = $originEntry
    ? './entry.php?table=' . urlencode($table) . '&idpk=' . urlencode($idpk)
    : './index.php';
$downloadUrl = $ExtendedBaseDirectoryCode . 'UPLOADS/' . rawurlencode($table) . '/' . rawurlencode($file);
$downloadName = $CompanyNameCompressed . '_attachment_' . $table . '_' . $file;
?>












<style>
    .viewer-container { min-width: 500px; max-width: 1200px; width: 100%; margin: auto; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; }
    #file-block { min-height: 500px; width: 100%; border: 1px solid var(--border-color); border-radius: 4px; background: var(--input-bg); display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; margin-top: 10px; padding: 10px; }
    pre.code-view { width: 100%; overflow: auto; }
    .line-number { opacity: 0.3; user-select: none; text-align: right; padding-right: 8px; }
    table.csv-table {
        border-collapse: collapse;
        width: 100%;
        margin: 0.5rem 0;
    }
    table.csv-table th,
    table.csv-table td {
        border: 1px solid var(--border-color);
        padding: 0.5rem;
        text-align: left;
    }
    table.csv-table th { background-color: var(--bg-color); }
    table.csv-editor-table th { cursor: pointer; user-select: none; }
    table.csv-editor-table td:focus, table.csv-editor-table th:focus { outline: 2px solid var(--primary-color); }
    .csv-editor-table th.selected,
    .csv-editor-table td.selected {
        color: var(--primary-color);
        font-weight: bold;
    }
    td.negative { color: red !important; }
    #csv-table-menu {
        display: none;
        position: absolute;
        background: var(--input-bg);
        color: var(--text-color);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        z-index: 9999;
        font-size: 14px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        min-width: 160px;
    }
    .metadata { opacity: 0.3; font-size: 0.9em; }
    .metadata-bar { margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }
    .metadata-bar .entry-link { margin-left: auto; }
    textarea.text-editor { width: 100%; height: 100%; flex: 1; border: none; background: transparent; font-family: monospace; resize: none; text-align: left; }
    /* Stretch EasyMDE over the entire file-block */
    #file-block .EasyMDEContainer {
        flex: 1;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    #file-block .EasyMDEContainer .CodeMirror,
    #file-block .EasyMDEContainer .CodeMirror-scroll {
        flex: 1;
        height: 100%;
        min-height: 100%;
    }
    /* invert markdown editor colors for dark mode */
    [data-theme="dark"] #file-block .EasyMDEContainer {
        filter: invert(1) hue-rotate(180deg);
    }
</style>










<div class="container viewer-container">
    <div class="top-bar">
        <a id="BackLink" href="<?= htmlspecialchars($backLink) ?>"><strong>◀️ RETURN</strong></a>
        <a id="DownloadLink" href="<?= htmlspecialchars($downloadUrl) ?>" style="opacity: 0.5;" download="<?= htmlspecialchars($downloadName) ?>">🔽 DOWNLOAD <?= strtoupper($ext) ?></a>
        <span id="SavingIndicator" style="opacity: 0.3;">✔️ saved</span>
    </div>
    <div id="file-block"></div>
    <div class="metadata-bar">
        <div id="metadata" class="metadata"></div>
        <?php if (!$originEntry): ?>
            <div class="entry-link">
                <a href="./entry.php?table=<?= urlencode($table) ?>&idpk=<?= urlencode($idpk) ?>"
                   title="open entry: <?= htmlspecialchars(strtoupper($table)) ?> (<?= htmlspecialchars($idpk) ?>)">🟦 <?= htmlspecialchars(strtoupper($table)) ?> (<?= htmlspecialchars($idpk) ?>)</a>
            </div>
        <?php endif; ?>
    </div>
    <div id="csv-table-menu"></div>
</div>






























<!-- Syntax highlighting -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/default.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>

<!-- EasyMDE for Markdown -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css" />
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>

<!-- 3D viewer with three.js as ES Module -->
<script type="module">
  import * as THREE from 'https://esm.sh/three@0.152.2';
  import { OrbitControls } from 'https://esm.sh/three@0.152.2/examples/jsm/controls/OrbitControls.js';
  import { STLLoader } from 'https://esm.sh/three@0.152.2/examples/jsm/loaders/STLLoader.js';
  import { OBJLoader } from 'https://esm.sh/three@0.152.2/examples/jsm/loaders/OBJLoader.js';
  import { GLTFLoader } from 'https://esm.sh/three@0.152.2/examples/jsm/loaders/GLTFLoader.js';
  import { FBXLoader } from 'https://esm.sh/three@0.152.2/examples/jsm/loaders/FBXLoader.js';
  import { PLYLoader } from 'https://esm.sh/three@0.152.2/examples/jsm/loaders/PLYLoader.js';

  const fileUrl = <?= json_encode($downloadUrl) ?>;
  const ext = <?= json_encode($ext) ?>;
  const fileBlock = document.getElementById('file-block');
  const metadataEl = document.getElementById('metadata');
  const table = <?= json_encode($table) ?>;
  const idpk = <?= json_encode($idpk) ?>;
  const fileName = <?= json_encode($file) ?>;
  const companyNameCompressed = <?= json_encode($CompanyNameCompressed ?? '') ?>;
  const downloadName = `${companyNameCompressed}_attachment_${table}_${fileName}`;
  const savingIndicator = document.getElementById("SavingIndicator");
  const backLink = document.getElementById("BackLink");
  const downloadLink = document.getElementById("DownloadLink");
  if (downloadLink) {
    downloadLink.setAttribute('download', downloadName);
  }
  const skipSave = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'pdf'].includes(ext);
  let editorGetter = null;
  let objectUrl = null;

  function parseCsv(text) {
    const rows = [];
    let row = [];
    let val = '';
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
      const c = text[i];
      if (inQuotes) {
        if (c === '"') {
          if (text[i + 1] === '"') { val += '"'; i++; }
          else { inQuotes = false; }
        } else {
          val += c;
        }
      } else {
        if (c === '"') {
          inQuotes = true;
        } else if (c === ',') {
          row.push(val);
          val = '';
        } else if (c === '\n') {
          row.push(val);
          rows.push(row);
          row = [];
          val = '';
        } else if (c === '\r') {
          // ignore
        } else {
          val += c;
        }
      }
    }
    row.push(val);
    if (row.length > 1 || row[0] !== '' || rows.length === 0) rows.push(row);
    return rows;
  }

  function generateCsvFromTable(table) {
    const out = [];
    for (const tr of table.rows) {
      const vals = Array.from(tr.cells).map(td => {
        let raw = td.textContent.trim();
        // remove sort arrows that may be present in headers
        raw = raw.replace(/[⇅↑↓]/g, '').trim();
        let t = raw.replace(/"/g, '""');
        if (/[",\n]/.test(raw)) t = `"${t}"`;
        return t;
      });
      out.push(vals.join(','));
    }
    return out.join('\n');
  }


  function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return bytes.toFixed(1) + ' ' + units[i];
  }
  function debounce(func, wait) {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func(...args), wait);
    };
  }

  function updateTextMetadata(text, opts = {}) {
    const bytes = new TextEncoder().encode(text).length;
    const size = formatSize(bytes);
    const chars = text.length;
    let info = `file type: ${ext.toUpperCase()} | file size: ${size} | ${chars} characters`;
    if (typeof opts.rows === 'number' || typeof opts.cols === 'number') {
      if (typeof opts.rows === 'number') info += ` | ${opts.rows} rows`;
      if (typeof opts.cols === 'number') info += ` | ${opts.cols} columns`;
    } else {
      const lines = text.split(/\n/).length;
      info += ` | ${lines} lines`;
    }
    metadataEl.textContent = info;
  }

  function makeTableSortable(table) {
    const headers = table.querySelectorAll('thead th');
    headers.forEach((th, colIndex) => {
      let arrow = th.querySelector('.sort-arrow');
      if (!arrow) {
        arrow = document.createElement('span');
        arrow.className = 'sort-arrow';
        arrow.textContent = ' \u21C5';
        arrow.style.cursor = 'pointer';
        arrow.style.fontSize = '0.8rem';
        arrow.contentEditable = false;
        arrow.style.userSelect = 'none';
        arrow.addEventListener('mousedown', e => e.stopPropagation());
        th.appendChild(arrow);
      }

      th.addEventListener('blur', () => addSortArrowIfMissing(th));

      let sortState = 0; // 0 none,1 asc,2 desc
      arrow.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        sortState = (sortState + 1) % 3;
        headers.forEach(h => {
          const a = h.querySelector('.sort-arrow');
          if (a && h !== th) a.textContent = ' \u21C5';
        });
        arrow.textContent = sortState === 1 ? ' \u2191' : sortState === 2 ? ' \u2193' : ' \u21C5';

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const getVal = r => r.children[colIndex].textContent.trim();
        if (sortState === 1) {
          rows.sort((a, b) => getVal(a).localeCompare(getVal(b), undefined, { numeric: true }));
        } else if (sortState === 2) {
          rows.sort((a, b) => getVal(b).localeCompare(getVal(a), undefined, { numeric: true }));
        }
        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '';
        rows.forEach(r => tbody.appendChild(r));
        if (typeof updateCsv === 'function') updateCsv();
      });
    });
  }

  function cleanHeaderCell(th) {
    const arrow = th.querySelector('.sort-arrow');
    if (arrow) arrow.remove();
    const text = th.textContent.replace(/[⇅↑↓]/g, '').trim();
    th.innerHTML = '';
    th.textContent = text;
    if (arrow) th.appendChild(arrow);
  }

  function addSortArrowIfMissing(th) {
    cleanHeaderCell(th);
    if (!th.querySelector('.sort-arrow')) {
      const table = th.closest('table');
      if (table) makeTableSortable(table);
    }
  }

  function saveFile() {
    if (!editorGetter) return Promise.resolve({ success: true });
    if (savingIndicator) {
      savingIndicator.style.opacity = "0.3";
      savingIndicator.textContent = "☁️ saving...";
    }
    const content = editorGetter();
    const formData = new FormData();
    formData.append("action", "update");
    formData.append("table", table);
    formData.append("idpk", idpk);
    formData.append("file", fileName);
    formData.append("content", content);
    return fetch("AjaxFileViewer.php", { method: "POST", body: formData })
      .then(r => r.json())
      .then(d => {
        if (savingIndicator) {
          if (d.success) {
            setTimeout(() => {
              savingIndicator.textContent = "✔️ saved";
              savingIndicator.style.opacity = "0.3";
            }, 300);
            setTimeout(() => { savingIndicator.style.opacity = "0.3"; }, 1500);
          } else {
            savingIndicator.textContent = "❌ saving failed";
            savingIndicator.style.opacity = "1";
          }
        }
        return d;
      })
      .catch(err => {
        if (savingIndicator) {
          savingIndicator.textContent = "❌ saving failed";
          savingIndicator.style.opacity = "1";
        }
        return { success: false, error: err.toString() };
      });
  }

  const debouncedSave = debounce(saveFile, 700);

  function downloadFile() {
    const trigger = url => {
      const a = document.createElement('a');
      a.href = url;
      a.download = downloadName;
      document.body.appendChild(a);
      a.click();
      a.remove();
    };

    if (objectUrl) {
      trigger(objectUrl);
    } else {
      fetch(fileUrl, { cache: 'no-store' })
        .then(r => r.blob())
        .then(b => {
          const url = URL.createObjectURL(b);
          trigger(url);
          URL.revokeObjectURL(url);
        });
    }
  }

  function addLineNumbers(codeEl) {
    const lines = codeEl.innerHTML.split(/\n/);
    codeEl.innerHTML = lines.map((line, i) => `<span><span class='line-number'>${i + 1}</span>${line}</span>`).join('\n');
  }

  async function render() {
    const res = await fetch(fileUrl, { cache: 'no-store' });
    let blob = await res.blob();
    if (ext === 'pdf' && blob.type !== 'application/pdf') {
      const ab = await blob.arrayBuffer();
      blob = new Blob([ab], { type: 'application/pdf' });
    }
    objectUrl = URL.createObjectURL(blob);
    metadataEl.textContent = `file type: ${ext.toUpperCase()} | file size: ${formatSize(blob.size)}`;
    fileBlock.innerHTML = '';
    fileBlock.style.alignItems = 'flex-start';
    fileBlock.style.justifyContent = 'flex-start';

    if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'].includes(ext)) {
      fileBlock.style.alignItems = 'center';
      fileBlock.style.justifyContent = 'center';
      const img = document.createElement('img');
      img.src = objectUrl;
      img.style.maxWidth = '100%';
      img.style.maxHeight = '100%';
      fileBlock.appendChild(img);

    } else if (ext === 'pdf') {
      fileBlock.style.alignItems = 'center';
      fileBlock.style.justifyContent = 'center';
      const iframe = document.createElement('iframe');
      iframe.src = objectUrl;
      iframe.style.width = '100%';
      iframe.style.height = '100%';
      iframe.style.border = 'none';
      iframe.loading = 'lazy';
      iframe.title = fileName;
      fileBlock.appendChild(iframe);

    } else if (['stl', 'obj', 'ply', 'fbx', 'gltf', 'glb', 'stp', 'step'].includes(ext)) {
      fileBlock.style.alignItems = 'center';
      fileBlock.style.justifyContent = 'center';
      const container = document.createElement('div');
      container.style.width = '100%';
      container.style.minHeight = '500px';
      container.style.height = fileBlock.clientHeight
        ? fileBlock.clientHeight + 'px'
        : '500px';
      fileBlock.appendChild(container);

      const adjustSize = () => {
        const w = container.clientWidth;
        const h = container.clientHeight;
        renderer.setSize(w, h);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
      };

      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
      const renderer = new THREE.WebGLRenderer();
      adjustSize();
      container.appendChild(renderer.domElement);
      window.addEventListener('resize', adjustSize);

      const controls = new OrbitControls(camera, renderer.domElement);
      const light = new THREE.DirectionalLight(0xffffff, 1);
      light.position.set(1, 1, 1);
      scene.add(light);

      let loader;
      if (ext === 'stl') loader = new STLLoader();
      else if (ext === 'obj') loader = new OBJLoader();
      else if (ext === 'ply') loader = new PLYLoader();
      else if (ext === 'fbx') loader = new FBXLoader();
      else loader = new GLTFLoader();

      loader.load(objectUrl, g => {
        const obj = g.isBufferGeometry ? new THREE.Mesh(g, new THREE.MeshNormalMaterial()) : (g.scene || g);
        scene.add(obj);

        const box = new THREE.Box3().setFromObject(obj);
        const size = new THREE.Vector3();
        box.getSize(size);
        metadataEl.textContent += ` | dimensions: ${size.x.toFixed(2)} x ${size.y.toFixed(2)} x ${size.z.toFixed(2)}`;
        camera.position.z = Math.max(size.x, size.y, size.z) * 2;

        function animate() {
          requestAnimationFrame(animate);
          controls.update();
          renderer.render(scene, camera);
        }
        animate();

      }, undefined, () => {
        fileBlock.textContent = ext.toUpperCase() + " could not be loaded, please download it and view and or or edit locally intead";
      });

    } else if (ext === 'csv') {
      const text = await blob.text();
      fileBlock.style.height = '100%';
      const tableEl = document.createElement('table');
      tableEl.className = 'csv-table csv-editor-table sortable-markdown-table';
      const rows = parseCsv(text);
      const thead = tableEl.createTHead();
      const headerRow = thead.insertRow();
      const clean = str => str.replace(/[⇅↑↓]/g, '').trim();
      (rows[0] || []).forEach(v => {
        const th = document.createElement('th');
        th.textContent = clean(v);
        th.contentEditable = true;
        headerRow.appendChild(th);
      });
      const tbody = tableEl.createTBody();
      for (let r = 1; r < rows.length; r++) {
        const tr = tbody.insertRow();
        rows[r].forEach(v => {
          const td = tr.insertCell();
          td.textContent = clean(v);
          td.contentEditable = true;
          td.classList.toggle('negative', !isNaN(v) && parseFloat(v) < 0);
          td.addEventListener('input', () => {
            const val = td.textContent.trim();
            td.classList.toggle('negative', !isNaN(val) && parseFloat(val) < 0);
          });
        });
      }
      fileBlock.appendChild(tableEl);
      makeTableSortable(tableEl);

      tableEl.addEventListener('focusin', e => {
        const cell = e.target.closest('td,th');
        if (cell) highlightSelection(cell);
      });
      tableEl.addEventListener('focusout', () => {
        if (!tableEl.contains(document.activeElement)) clearHighlights();
      });

      function highlightSelection(cell) {
        clearHighlights();
        if (!cell) return;
        const row = cell.parentElement;
        if (row && row.cells[0]) row.cells[0].classList.add('selected');
        const index = cell.cellIndex + 1;
        const th = tableEl.querySelector('thead th:nth-child(' + index + ')');
        if (th) th.classList.add('selected');
      }

      function clearHighlights() {
        tableEl.querySelectorAll('th.selected, td.selected').forEach(el => el.classList.remove('selected'));
      }

      const csvMenu = document.getElementById('csv-table-menu');
      let currentCell = null;

      const updateCsv = () => {
        const csv = generateCsvFromTable(tableEl);
        const rowsCount = tableEl.rows.length > 0 ? tableEl.rows.length - 1 : 0;
        const colsCount = tableEl.rows[0] ? tableEl.rows[0].cells.length : 0;
        updateTextMetadata(csv, { rows: rowsCount, cols: colsCount });
        debouncedSave();
      };

      tableEl.addEventListener('blur', e => {
        if (e.target.matches('td,th')) updateCsv();
      }, true);

      tableEl.addEventListener('contextmenu', e => {
        const td = e.target.closest('td,th');
        if (!td) return;
        e.preventDefault();
        currentCell = td;
        csvMenu.innerHTML = '';

        const rows = tableEl.rows.length;
        const cols = tableEl.rows[0].cells.length;

        const actions = [
          { label: '➕ ADD ROW BELOW', action: () => modify('add-row') },
          { label: '➕ ADD COLUMN RIGHT', action: () => modify('add-col') },
        ];
        if (rows > 1) actions.push({ label: '❌ REMOVE ROW', action: () => confirmAndModify('delete-row') });
        if (cols > 1) actions.push({ label: '❌ REMOVE COLUMN', action: () => confirmAndModify('delete-col') });

        actions.forEach(({label, action}) => {
          const item = document.createElement('div');
          item.textContent = label;
          item.style.padding = '8px 12px';
          item.style.cursor = 'pointer';
          item.addEventListener('click', () => { csvMenu.style.display='none'; action(); });
          item.addEventListener('mouseenter', () => item.style.background='rgba(255,255,255,0.1)');
          item.addEventListener('mouseleave', () => item.style.background='transparent');
          csvMenu.appendChild(item);
        });
        csvMenu.style.top = `${e.pageY}px`;
        csvMenu.style.left = `${e.pageX}px`;
        csvMenu.style.display = 'block';
      });

      document.addEventListener('click', () => { csvMenu.style.display = 'none'; });

      function confirmAndModify(action) {
        let message = 'Are you sure?';
        if (action === 'delete-row') message = 'Are you sure you want to remove this row?';
        else if (action === 'delete-col') message = 'Are you sure you want to remove this column?';
        if (confirm(message)) modify(action);
      }

      function modify(action) {
        if (!currentCell) return;
        const rowIndex = currentCell.parentElement.rowIndex;
        const colIndex = currentCell.cellIndex;

        if (action === 'add-row') {
          const newRow = tableEl.insertRow(rowIndex + 1);
          const colCount = tableEl.rows[0].cells.length;
          for (let i = 0; i < colCount; i++) {
            const cell = newRow.insertCell(i);
            cell.textContent = '';
            cell.contentEditable = true;
            cell.style.minHeight = '1em';
            cell.style.padding = '4px';
            cell.addEventListener('input', () => {
              const val = cell.textContent.trim();
              cell.classList.toggle('negative', !isNaN(val) && parseFloat(val) < 0);
            });
          }
        } else if (action === 'add-col') {
          for (let i = 0; i < tableEl.rows.length; i++) {
            const row = tableEl.rows[i];
            if (i === 0) {
              const th = document.createElement('th');
              th.contentEditable = true;
              th.textContent = '';
              row.insertBefore(th, row.cells[colIndex + 1]);
            } else {
              const cell = row.insertCell(colIndex + 1);
              cell.textContent = '';
              cell.contentEditable = true;
              cell.style.minHeight = '1em';
              cell.style.padding = '4px';
              cell.addEventListener('input', () => {
                const val = cell.textContent.trim();
                cell.classList.toggle('negative', !isNaN(val) && parseFloat(val) < 0);
              });
            }
          }
        } else if (action === 'delete-row') {
          if (tableEl.rows.length > 1) {
            tableEl.deleteRow(rowIndex);
          }
        } else if (action === 'delete-col') {
          const colCount = tableEl.rows[0].cells.length;
          if (colCount > 1) {
            for (const row of tableEl.rows) {
              row.deleteCell(colIndex);
            }
          }
        }
        makeTableSortable(tableEl);
        updateCsv();
      }

      editorGetter = () => generateCsvFromTable(tableEl);
      const initRows = tableEl.rows.length > 0 ? tableEl.rows.length - 1 : 0;
      const initCols = tableEl.rows[0] ? tableEl.rows[0].cells.length : 0;
      updateTextMetadata(text, { rows: initRows, cols: initCols });

    } else if (['txt','md','markdown','py','php','js','html','css','json','xml','c','cpp','h','java'].includes(ext)) {
      const text = await blob.text();
      // fileBlock.style.height = '500px';
      fileBlock.style.height = '100%';
      const textarea = document.createElement('textarea');
      textarea.className = 'text-editor';
      textarea.value = text;
      fileBlock.appendChild(textarea);
      let getVal = () => textarea.value;
      if (['md','markdown'].includes(ext)) {
        const mde = new EasyMDE({ element: textarea, toolbar: false, status: false, autoDownloadFontAwesome: false });
        getVal = () => mde.value();
        mde.codemirror.on('change', () => { updateTextMetadata(getVal()); debouncedSave(); });
      } else {
        textarea.addEventListener('input', () => { updateTextMetadata(getVal()); debouncedSave(); });
      }
      editorGetter = getVal;
      updateTextMetadata(text);

    } else {
      fileBlock.textContent = ext.toUpperCase() + " could not be loaded, please download it and view and or or edit locally intead";
    }
  }

  render();

  if (downloadLink) {
    downloadLink.addEventListener('click', e => {
      e.preventDefault();
      downloadFile();
    });
  }

  if (backLink) {
    backLink.addEventListener('click', e => {
      e.preventDefault();
      const proceed = () => { window.location.href = backLink.href; };
      if (skipSave) {
        proceed();
      } else {
        saveFile().then(proceed);
      }
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && backLink) {
      e.preventDefault();
      const proceed = () => { window.location.href = backLink.href; };
      if (skipSave) {
        proceed();
      } else {
        saveFile().then(proceed);
      }
    }
  });

  window.addEventListener('beforeunload', () => {
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
    }
  });

  if (window.addTooltipPreviews) {
    window.addTooltipPreviews();
  }
</script>
