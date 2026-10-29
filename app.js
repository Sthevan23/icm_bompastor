(function () {
  const STORAGE_KEY = "icm-oracao-v1";

  function apiBase() {
    const base = document.documentElement.dataset.apiBase || "";
    return base.replace(/\/$/, "");
  }

  /** Produção: escalas só no servidor (grupo / vários celulares). */
  function serverOnly() {
    return document.documentElement.dataset.serverOnly === "true";
  }

  function apiUrl(path) {
    const base = apiBase();
    if (path.startsWith("/api/")) {
      const route = path.slice("/api/".length);
      return `${base}/api/index.php?route=${encodeURIComponent(route)}`;
    }
    return `${base}${path}`;
  }

  const SCALE_ID_RE = /^[a-f0-9]{16}$/;

  /** Link estável para WhatsApp (evita perder o #). */
  function scaleHref(id) {
    return `escala.html?id=${encodeURIComponent(id)}`;
  }

  function scaleShareUrl(id) {
    return new URL(scaleHref(id), window.location.href).href;
  }

  function parseScaleId(locationLike) {
    const loc = locationLike || window.location;
    const params = new URLSearchParams(loc.search);
    const fromQuery = params.get("id") || params.get("escala") || "";
    if (SCALE_ID_RE.test(fromQuery)) return fromQuery;
    const fromHash = loc.hash.slice(1);
    if (SCALE_ID_RE.test(fromHash)) return fromHash;
    return "";
  }

  function canonicalizeScaleUrl(id) {
    if (!SCALE_ID_RE.test(id)) return;
    const want = new URL(scaleHref(id), window.location.href);
    if (window.location.pathname === want.pathname && window.location.search === want.search) {
      return;
    }
    history.replaceState(null, "", want.pathname + want.search);
  }

  function emptyList() {
    const list = {};
    for (let i = 0; i < 96; i += 1) {
      const start = i * 15;
      const end = start + 15;
      list[`${clock(start)} às ${clock(end)}`] = null;
    }
    return list;
  }

  function clock(totalMinutes) {
    const minutes = totalMinutes % (24 * 60);
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
  }

  function loadDB() {
    try {
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || "");
      return {
        scales: parsed.scales || {},
        tokens: parsed.tokens || {},
      };
    } catch {
      return { scales: {}, tokens: {} };
    }
  }

  function saveDB(db) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(db));
  }

  let apiOn = null;

  async function hasApi() {
    if (apiOn !== null) return apiOn;
    try {
      const response = await fetch(apiUrl("/api/health"), { cache: "no-store" });
      apiOn = response.ok;
    } catch {
      apiOn = false;
    }
    return apiOn;
  }

  function summary(record) {
    return {
      id: record.id,
      igreja: record.igreja,
      motivo: record.motivo,
      date0: record.date0,
      date1: record.date1,
      timestamp: record.timestamp,
    };
  }

  function remember(record, token) {
    const db = loadDB();
    const previous = db.scales[record.id] || {};
    db.scales[record.id] = {
      ...summary(record),
      currentList: record.currentList || previous.currentList,
    };
    if (token) db.tokens[record.id] = token;
    saveDB(db);
  }

  function forget(id) {
    const db = loadDB();
    delete db.scales[id];
    delete db.tokens[id];
    saveDB(db);
  }

  function isOwner(id) {
    return Boolean(loadDB().tokens[id]);
  }

  function tokenFor(id) {
    return loadDB().tokens[id] || "";
  }

  function listScalesLocal() {
    return Object.values(loadDB().scales).sort((a, b) =>
      String(b.timestamp || "").localeCompare(String(a.timestamp || ""))
    );
  }

  async function listScalesRemote() {
    const response = await fetch(apiUrl("/api/scales"), { cache: "no-store" });
    if (response.status === 404) return [];
    if (!response.ok) return null;
    const data = await response.json();
    return Array.isArray(data.scales) ? data.scales : [];
  }

  async function syncOneScale(id) {
    if (!(await hasApi())) return false;
    const db = loadDB();
    const token = db.tokens[id];
    const local = db.scales[id];
    if (!token || !local) return false;

    const check = await fetch(apiUrl(`/api/scales/${encodeURIComponent(id)}`), {
      cache: "no-store",
    });
    if (check.status !== 404) return false;

    const response = await fetch(apiUrl("/api/scales/sync"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id,
        ownerToken: token,
        record: {
          igreja: local.igreja,
          motivo: local.motivo,
          date0: local.date0,
          date1: local.date1,
          timestamp: local.timestamp || new Date().toISOString(),
          currentList: local.currentList || emptyList(),
        },
      }),
    });
    return response.ok;
  }

  async function syncLocalScales() {
    if (!(await hasApi())) return 0;
    const db = loadDB();
    let synced = 0;

    for (const id of Object.keys(db.scales)) {
      if (await syncOneScale(id)) synced += 1;
    }
    return synced;
  }

  async function listScales() {
    let synced = 0;
    const online = await hasApi();

    if (serverOnly() && !online) {
      return { items: [], synced: 0, apiOff: true };
    }

    if (online) {
      synced = await syncLocalScales();
      const remote = await listScalesRemote();
      if (remote !== null) {
        for (const item of remote) {
          remember(item);
        }
        return {
          items: remote.sort((a, b) =>
            String(b.timestamp || "").localeCompare(String(a.timestamp || ""))
          ),
          synced,
          apiOff: false,
        };
      }
    }

    if (serverOnly()) {
      return { items: [], synced, apiOff: !online };
    }
    return { items: listScalesLocal(), synced, apiOff: false };
  }

  async function createScale(data) {
    const payload = {
      igreja: data.igreja,
      motivo: data.motivo,
      date0: data.date0,
      date1: data.date1,
      timestamp: new Date().toISOString(),
      currentList: emptyList(),
    };

    const online = await hasApi();
    if (serverOnly() && !online) {
      throw new Error("api_off");
    }

    if (online) {
      const response = await fetch(apiUrl("/api/scales"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!response.ok) throw new Error("create");
      const body = await response.json();
      remember(body.record, body.ownerToken);
      return body.record;
    }

    const id = crypto.randomUUID().replace(/-/g, "").slice(0, 16);
    const token = crypto.randomUUID().replace(/-/g, "");
    const record = { ...payload, id };
    remember({ ...record }, token);
    const db = loadDB();
    db.scales[id] = record;
    db.tokens[id] = token;
    saveDB(db);
    return record;
  }

  async function getScale(id) {
    const online = await hasApi();
    if (serverOnly() && !online) {
      throw new Error("api_off");
    }

    if (online) {
      await syncOneScale(id);
      const response = await fetch(apiUrl(`/api/scales/${encodeURIComponent(id)}`), {
        cache: "no-store",
      });
      if (response.status === 404) {
        if (serverOnly()) return null;
        return loadDB().scales[id] || null;
      }
      if (!response.ok) throw new Error("get");
      const record = await response.json();
      remember(record);
      return record;
    }
    return loadDB().scales[id] || null;
  }

  async function setSlot(id, interval, name) {
    const online = await hasApi();
    if (serverOnly() && !online) {
      return { ok: false, status: 503, data: null };
    }
    if (online) {
      const headers = { "Content-Type": "application/json" };
      if (name == null) headers["X-Owner-Token"] = tokenFor(id);
      const response = await fetch(apiUrl(`/api/scales/${encodeURIComponent(id)}/slot`), {
        method: "POST",
        headers,
        body: JSON.stringify({ interval, name }),
      });
      const data = await response.json().catch(() => null);
      if (response.ok && data && data.record) {
        remember(data.record);
      }
      return { ok: response.ok, status: response.status, data };
    }

    const db = loadDB();
    const record = db.scales[id];
    if (!record) return { ok: false, status: 404, data: null };
    if (name == null && !db.tokens[id]) return { ok: false, status: 403, data: record };
    if (name && record.currentList[interval]) {
      return { ok: false, status: 409, data: record };
    }
    record.currentList[interval] = name;
    record.timestamp = new Date().toISOString();
    saveDB(db);
    return { ok: true, status: 200, data: record };
  }

  async function deleteScale(id) {
    const online = await hasApi();
    if (serverOnly() && !online) {
      throw new Error("api_off");
    }
    if (online) {
      const response = await fetch(apiUrl(`/api/scales/${encodeURIComponent(id)}`), {
        method: "DELETE",
        headers: { "X-Owner-Token": tokenFor(id) },
      });
      if (!response.ok && response.status !== 404) {
        throw new Error("delete");
      }
    }
    forget(id);
  }

  function brDate(iso) {
    const [year, month, day] = String(iso || "").split("-");
    if (!year || !month || !day) return "";
    return `${day}/${month}/${year}`;
  }

  function formatDate(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "—";
    const p = (n) => String(n).padStart(2, "0");
    return `${p(date.getDate())}/${p(date.getMonth() + 1)}/${date.getFullYear()} ${p(date.getHours())}:${p(date.getMinutes())}:${p(date.getSeconds())}`;
  }

  function stats(list) {
    const values = Object.values(list || {});
    const filled = values.filter(Boolean).length;
    const total = values.length || 96;
    const percentage = (Math.round((filled / total) * 1000) / 10).toLocaleString("pt-BR");
    return { filled, total, percentage };
  }

  function swalButtons() {
    return {
      confirmButton: "meu-confirmar",
      cancelButton: "meu-cancelar",
      input: "meu-input-estilizado",
    };
  }

  function localToday(offsetDays) {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);
    const p = (n) => String(n).padStart(2, "0");
    return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`;
  }

  window.ICM = {
    apiUrl,
    scaleHref,
    scaleShareUrl,
    parseScaleId,
    canonicalizeScaleUrl,
    emptyList,
    hasApi,
    serverOnly,
    listScales,
    listScalesLocal,
    syncLocalScales,
    createScale,
    getScale,
    setSlot,
    deleteScale,
    forget,
    isOwner,
    remember,
    brDate,
    formatDate,
    stats,
    swalButtons,
    localToday,
  };
})();
