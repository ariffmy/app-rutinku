const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const publicDir = path.join(root, 'public');
const phpCgi = process.argv[2] || 'php-cgi';
const mime = { '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json', '.webmanifest': 'application/manifest+json', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.ico': 'image/x-icon', '.html': 'text/html' };

const server = http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost:8080');
  const candidate = path.resolve(publicDir, `.${decodeURIComponent(url.pathname)}`);
  if (candidate.startsWith(publicDir + path.sep) && fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
    response.writeHead(200, { 'Content-Type': `${mime[path.extname(candidate).toLowerCase()] || 'application/octet-stream'}`, 'Cache-Control': 'no-cache' });
    fs.createReadStream(candidate).pipe(response);
    return;
  }

  const chunks = [];
  request.on('data', (chunk) => chunks.push(chunk));
  request.on('end', () => {
    const body = Buffer.concat(chunks);
    const forwardedHeaders = Object.fromEntries(Object.entries(request.headers).map(([key, value]) => [`HTTP_${key.toUpperCase().replaceAll('-', '_')}`, Array.isArray(value) ? value.join(', ') : value || '']));
    const child = spawn(phpCgi, [], {
      cwd: root,
      windowsHide: true,
      env: {
        ...process.env,
        ...forwardedHeaders,
        REDIRECT_STATUS: '200', GATEWAY_INTERFACE: 'CGI/1.1', SERVER_PROTOCOL: 'HTTP/1.1',
        REQUEST_METHOD: request.method, REQUEST_URI: request.url, QUERY_STRING: url.search.slice(1),
        SCRIPT_NAME: '/index.php', SCRIPT_FILENAME: path.join(publicDir, 'index.php'), DOCUMENT_ROOT: publicDir,
        SERVER_NAME: 'localhost', SERVER_PORT: '8080', REMOTE_ADDR: request.socket.remoteAddress || '127.0.0.1',
        CONTENT_TYPE: request.headers['content-type'] || '', CONTENT_LENGTH: String(body.length),
      },
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    const output = [];
    const errors = [];
    child.stdout.on('data', (chunk) => output.push(chunk));
    child.stderr.on('data', (chunk) => errors.push(chunk));
    child.on('close', () => {
      const raw = Buffer.concat(output);
      const separator = raw.indexOf('\r\n\r\n');
      if (separator < 0) {
        response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        response.end(Buffer.concat(errors).toString() || 'Respons PHP-CGI tidak sah.');
        return;
      }
      const headerLines = raw.subarray(0, separator).toString().split('\r\n');
      let status = 200;
      const headers = {};
      for (const line of headerLines) {
        const colon = line.indexOf(':');
        if (colon < 0) continue;
        const name = line.slice(0, colon).trim();
        const value = line.slice(colon + 1).trim();
        if (name.toLowerCase() === 'status') status = Number.parseInt(value, 10);
        else if (name.toLowerCase() !== 'x-powered-by') headers[name] = value;
      }
      response.writeHead(status, headers);
      response.end(raw.subarray(separator + 4));
    });
    child.stdin.end(body);
  });
});

server.listen(8080, '0.0.0.0', () => console.log('RutinKu: http://localhost:8080'));
