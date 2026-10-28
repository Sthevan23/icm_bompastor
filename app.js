(function () {
  const STORAGE_KEY = "icm-oracao-v1";

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
      const response = await fetch("/api/health", { cache: "no-store" });
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

  function listScales() {
    return Object.values(loadDB().scales).sort((a, b) =>
      String(b.timestamp || "").localeCompare(String(a.timestamp || ""))
    );
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

    if (await hasApi()) {
      const response = await fetch("/api/scales", {
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
    if (await hasApi()) {
      const response = await fetch(`/api/scales/${encodeURIComponent(id)}`, {
        cache: "no-store",
      });
      if (response.status === 404) return null;
      if (!response.ok) throw new Error("get");
      return response.json();
    }
    return loadDB().scales[id] || null;
  }

  async function setSlot(id, interval, name) {
    if (await hasApi()) {
      const headers = { "Content-Type": "application/json" };
      if (name == null) headers["X-Owner-Token"] = tokenFor(id);
      const response = await fetch(`/api/scales/${encodeURIComponent(id)}/slot`, {
        method: "POST",
        headers,
        body: JSON.stringify({ interval, name }),
      });
      const data = await response.json().catch(() => null);
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
    if (await hasApi()) {
      const response = await fetch(`/api/scales/${encodeURIComponent(id)}`, {
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
    emptyList,
    hasApi,
    listScales,
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
