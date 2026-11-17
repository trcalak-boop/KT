"""Převzato z projektu tasstudio.eu (skripty/snimky/cdp.py); úprava: --allow-file-access-from-files pro testy z file://.

Malý klient Chrome DevTools Protocol bez závislostí (jen standardní knihovna Pythonu).

Spustí Chrome bez okna, otevře stránku, umí v ní spustit JavaScript a uložit snímek obrazovky.
Používá ho skripty/snimky/snimky.py pro snímky do galerií portfolia.
"""
import base64, json, os, socket, struct, subprocess, tempfile, time, urllib.request, shutil

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"


class WS:
    """Minimální websocket klient (textové rámce, maskované, bez rozšíření)."""

    def __init__(self, url):
        assert url.startswith("ws://")
        hostport, path = url[5:].split("/", 1)
        host, port = hostport.split(":")
        self.s = socket.create_connection((host, int(port)))
        key = base64.b64encode(os.urandom(16)).decode()
        self.s.sendall(("GET /%s HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
                        "Sec-WebSocket-Key: %s\r\nSec-WebSocket-Version: 13\r\n\r\n" % (path, hostport, key)).encode())
        buf = b""
        while b"\r\n\r\n" not in buf:
            buf += self.s.recv(4096)
        self.rest = buf.split(b"\r\n\r\n", 1)[1]

    def send(self, text):
        data = text.encode()
        head = bytearray([0x81])
        n = len(data)
        if n < 126:
            head.append(0x80 | n)
        elif n < 65536:
            head.append(0x80 | 126); head += struct.pack(">H", n)
        else:
            head.append(0x80 | 127); head += struct.pack(">Q", n)
        mask = os.urandom(4)
        head += mask
        self.s.sendall(bytes(head) + bytes(b ^ mask[i % 4] for i, b in enumerate(data)))

    def _read(self, n):
        while len(self.rest) < n:
            chunk = self.s.recv(1 << 20)
            if not chunk:
                raise EOFError
            self.rest += chunk
        out, self.rest = self.rest[:n], self.rest[n:]
        return out

    def recv(self):
        msg = b""
        while True:
            b1, b2 = self._read(2)
            n = b2 & 0x7F
            if n == 126:
                n = struct.unpack(">H", self._read(2))[0]
            elif n == 127:
                n = struct.unpack(">Q", self._read(8))[0]
            payload = self._read(n)
            msg += payload
            if b1 & 0x80:
                return msg.decode()


class Browser:
    def __init__(self, port=9333):
        self.prof = tempfile.mkdtemp()
        self.proc = subprocess.Popen([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run", "--allow-file-access-from-files",
                                      "--remote-debugging-port=%d" % port, "--user-data-dir=" + self.prof, "about:blank"],
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(400):
            try:
                pages = json.load(urllib.request.urlopen("http://127.0.0.1:%d/json" % port))
                page = [p for p in pages if p.get("type") == "page"][0]
                break
            except Exception:
                time.sleep(.1)
        self.ws = WS(page["webSocketDebuggerUrl"])
        self.i = 0
        self.call("Page.enable")
        self.call("Runtime.enable")

    def call(self, method, **params):
        self.i += 1
        my = self.i
        self.ws.send(json.dumps({"id": my, "method": method, "params": params}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get("id") == my:
                if "error" in m:
                    raise RuntimeError(method + ": " + json.dumps(m["error"]))
                return m.get("result", {})

    def size(self, w, h, scale=1, mobile=False):
        self.call("Emulation.setDeviceMetricsOverride", width=w, height=h, deviceScaleFactor=scale, mobile=mobile)

    def scheme(self, dark):
        self.call("Emulation.setEmulatedMedia", features=[{"name": "prefers-color-scheme", "value": "dark" if dark else "light"}])

    def go(self, url, wait=1.2):
        self.call("Page.navigate", url=url)
        time.sleep(wait)
        self.js("document.fonts ? document.fonts.ready.then(()=>1) : 1")

    def js(self, code, wait=0):
        r = self.call("Runtime.evaluate", expression="(async()=>{%s})()" % code if "return" in code or "await" in code else code,
                      awaitPromise=True, returnByValue=True)
        if "exceptionDetails" in r:
            raise RuntimeError("JS: " + json.dumps(r["exceptionDetails"])[:400])
        if wait:
            time.sleep(wait)
        return r.get("result", {}).get("value")

    def shot(self, path, full=False, quality=88):
        params = {"format": "jpeg", "quality": quality}
        if full:
            m = self.call("Page.getLayoutMetrics")["cssContentSize"]
            params.update(captureBeyondViewport=True, clip={"x": 0, "y": 0, "width": m["width"], "height": m["height"], "scale": 1})
        data = self.call("Page.captureScreenshot", **params)["data"]
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "wb") as f:
            f.write(base64.b64decode(data))

    def pdf(self, path, **opts):
        data = self.call("Page.printToPDF", printBackground=True, preferCSSPageSize=True, **opts)["data"]
        with open(path, "wb") as f:
            f.write(base64.b64decode(data))

    def close(self):
        try:
            self.call("Browser.close")
        except Exception:
            pass
        self.proc.wait(timeout=10)
        shutil.rmtree(self.prof, ignore_errors=True)
