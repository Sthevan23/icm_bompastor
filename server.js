const http = require("http");
const fs = require("fs");
const path = require("path");
const os = require("os");
const crypto = require("crypto");
const { exec } = require("child_process");

const ROOT = __dirname;
const DATA_DIR = path.join(ROOT, "data");
const DATA = path.join(DATA_DIR, "scales.json");
const PORT = Number(process.env.PORT) || 8080;

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".ico": "image/x-icon",
};

function emptyList() {
  const list = {};
  for (let i = 0; i < 96; i += 1) {
    const start = i * 15;
    const end = start + 15;
    const clock = (total) => {
      const minutes = total % (24 * 60);
      const h = Math.floor(minutes / 60);
      const m = minutes % 60;
      return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
    };
    list[`${clock(start)} às ${clock(end)}`] = null;
  }
  return list;
}

const SLOT_KEYS = Object.keys(emptyList());

function readDB() {
  try {
    return JSON.parse(fs.readFileSync(DATA, "utf8"));
  } catch {
    return {};
  }
}

function writeDB(db) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
  const tmp = `${DATA}.tmp`;
  fs.writeFileSync(tmp, JSON.stringify(db, null, 2));
  fs.renameSync(tmp, DATA);
}

function publicRecord(record) {
  if (!record) return null;
  return {
    id: record.id,
    igreja: record.igreja,
    motivo: record.motivo,
    date0: record.date0,
    date1: record.date1,
    timestamp: record.timestamp,
    currentList: record.currentList,
  };
}

function lanUrls(port) {
  const urls = [`http://localhost:${port}`];
  const nets = os.networkInterfaces();
  for (const list of Object.values(nets)) {
    for (const net of list || []) {
      const v4 = net.family === "IPv4" || net.family === 4;
      if (v4 && !net.internal) urls.push(`http://${net.address}:${port}`);
    }
  }
  return urls;
}

function send(res, status, body, type) {
  const payload = Buffer.isBuffer(body) || typeof body === "string" ? body : JSON.stringify(body);
  res.writeHead(status, {
    "Content-Type": type || "application/json; charset=utf-8",
    "Cache-Control": "no-store",
  });
  res.end(payload);
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    req.on("data", (chunk) => {
      size += chunk.length;
      if (size > 200000) {
        reject(new Error("too big"));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on("end", () => {
      if (!chunks.length) {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(Buffer.concat(chunks).toString("utf8")));
      } catch (error) {
        reject(error);
      }
    });
    req.on("error", reject);
  });
}

function validDate(value) {
  return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ""));
}

function serveStatic(req, res, pathname) {
  const routes = {
    "/": "index.html",
    "/index.html": "index.html",
    "/escala": "escala.html",
    "/escala.html": "escala.html",
    "/style.css": "style.css",
    "/app.js": "app.js",
    "/logo.svg": "logo.svg",
  };
  const rel = routes[pathname];
  if (!rel) {
    send(res, 404, { error: "not found" });
    return;
  }
  const filePath = path.join(ROOT, rel);
  fs.readFile(filePath, (error, buf) => {
    if (error) {
      send(res, 404, { error: "not found" });
      return;
    }
    send(res, 200, buf, MIME[path.extname(rel)] || "application/octet-stream");
  });
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, "http://localhost");
  const pathname = decodeURIComponent(url.pathname);
  const method = req.method || "GET";

  try {
    if (method === "GET" && pathname === "/api/health") {
      send(res, 200, { ok: true, urls: lanUrls(PORT) });
      return;
    }

    const scaleMatch = pathname.match(/^\/api\/scales\/([A-Za-z0-9_-]{8,64})$/);
    const slotMatch = pathname.match(/^\/api\/scales\/([A-Za-z0-9_-]{8,64})\/slot$/);

    if (method === "POST" && pathname === "/api/scales") {
      const body = await readBody(req);
      const igreja = String(body.igreja || "").trim().slice(0, 39);
      const motivo = String(body.motivo || "").trim().slice(0, 39);
      if (!igreja || !motivo || !validDate(body.date0) || !validDate(body.date1)) {
        send(res, 400, { error: "invalid" });
        return;
      }
      if (body.date1 < body.date0) {
        send(res, 400, { error: "period" });
        return;
      }
      const id = crypto.randomBytes(8).toString("hex");
      const ownerToken = crypto.randomBytes(16).toString("hex");
      const record = {
        id,
        igreja,
        motivo,
        date0: body.date0,
        date1: body.date1,
        timestamp: new Date().toISOString(),
        currentList: emptyList(),
        ownerToken,
      };
      const db = readDB();
      db[id] = record;
      writeDB(db);
      send(res, 200, { record: publicRecord(record), ownerToken });
      return;
    }

    if (slotMatch && method === "POST") {
      const id = slotMatch[1];
      const body = await readBody(req);
      const db = readDB();
      const record = db[id];
      if (!record) {
        send(res, 404, { error: "missing" });
        return;
      }
      const interval = body.interval;
      if (!SLOT_KEYS.includes(interval)) {
        send(res, 400, { error: "interval" });
        return;
      }
      if (body.name == null) {
        if (req.headers["x-owner-token"] !== record.ownerToken) {
          send(res, 403, { error: "forbidden", record: publicRecord(record) });
          return;
        }
        record.currentList[interval] = null;
      } else {
        const name = String(body.name).trim().slice(0, 32);
        if (!name) {
          send(res, 400, { error: "name", record: publicRecord(record) });
          return;
        }
        if (record.currentList[interval]) {
          send(res, 409, { error: "occupied", record: publicRecord(record) });
          return;
        }
        record.currentList[interval] = name;
      }
      record.timestamp = new Date().toISOString();
      writeDB(db);
      send(res, 200, { record: publicRecord(record) });
      return;
    }

    if (scaleMatch && method === "GET") {
      const record = readDB()[scaleMatch[1]];
      if (!record) {
        send(res, 404, { error: "missing" });
        return;
      }
      send(res, 200, publicRecord(record));
      return;
    }

    if (scaleMatch && method === "DELETE") {
      const db = readDB();
      const record = db[scaleMatch[1]];
      if (!record) {
        send(res, 404, { error: "missing" });
        return;
      }
      if (req.headers["x-owner-token"] !== record.ownerToken) {
        send(res, 403, { error: "forbidden" });
        return;
      }
      delete db[scaleMatch[1]];
      writeDB(db);
      send(res, 200, { ok: true });
      return;
    }

    if (method === "GET") {
      serveStatic(req, res, pathname);
      return;
    }

    send(res, 404, { error: "not found" });
  } catch {
    send(res, 500, { error: "server" });
  }
});

server.listen(PORT, "0.0.0.0", () => {
  const urls = lanUrls(PORT);
  console.log("Oração Ininterrupta — Igreja Cristã Maranata");
  urls.forEach((item) => console.log(item));
  if (process.argv.includes("--open")) {
    exec(`cmd /c start "" "${urls[0]}"`);
  }
});
