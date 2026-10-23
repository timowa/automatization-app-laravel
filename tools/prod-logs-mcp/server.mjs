import { spawn } from 'node:child_process';

const HOST = process.env.PROD_LOG_FTP_HOST ?? '';
const USER = process.env.PROD_LOG_FTP_USER ?? '';
const PASSWORD = process.env.PROD_LOG_FTP_PASSWORD ?? '';

const CHANNELS = [
  ['vk', 'vk/posts/error-{date}.log'],
  ['vk-images', 'vk/images/error-{date}.log'],
  ['vk-sync', 'vk/sync/error-{date}.log'],
  ['vkRepost', 'vk/reposts/error-{date}.log'],
];

const LINE = /^\[([^\]]+)\] \w+\.(INFO|WARNING|ERROR): (.*)$/;

function lastCompletedMoscowDate() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Moscow',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const get = (type) => parts.find((part) => part.type === type).value;
  const utc = Date.UTC(Number(get('year')), Number(get('month')) - 1, Number(get('day')));
  const yesterday = new Date(utc - 24 * 60 * 60 * 1000);

  return yesterday.toISOString().slice(0, 10);
}

function hideSecrets(text) {
  let safe = text;
  if (PASSWORD !== '') {
    safe = safe.split(PASSWORD).join('***');
  }
  if (USER !== '') {
    safe = safe.split(USER).join('***');
  }

  return safe;
}

function isMissingFile(code, stderr) {
  if (/425/.test(stderr)) {
    return false;
  }
  if (code === 78) {
    return true;
  }
  if (code === 9 && /denied you to change to the given directory/i.test(stderr)) {
    return true;
  }

  return /no such file|does not exist/i.test(stderr);
}

function curlOnce(ftpPath) {
  const url = `ftp://${HOST}/${ftpPath}`;

  return new Promise((resolve, reject) => {
    const child = spawn('curl', [
      '--config', '-',
      '--noproxy', '*',
      '--ipv4',
      '--disable-epsv',
      '--disable-eprt',
      '--ftp-pasv',
      '--silent',
      '--show-error',
      '--max-time', '90',
      url,
    ], { windowsHide: true });
    const out = [];
    const err = [];
    child.stdout.on('data', (chunk) => out.push(chunk));
    child.stderr.on('data', (chunk) => err.push(chunk));
    child.on('error', (error) => reject(error));
    child.stdin.end(`user = "${USER}:${PASSWORD}"\n`);
    child.on('close', (code) => {
      const stderr = hideSecrets(Buffer.concat(err).toString('utf8')).trim();
      if (code !== 0) {
        resolve({
          ok: false,
          missing: isMissingFile(code, stderr),
          error: stderr || `curl ${code}`,
        });
        return;
      }
      resolve({ ok: true, body: Buffer.concat(out).toString('utf8') });
    });
  });
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

async function curl(ftpPath) {
  let last;
  for (let attempt = 0; attempt < 6; attempt += 1) {
    last = await curlOnce(ftpPath);
    if (last.ok || last.missing) {
      return last;
    }
    const pause = /425/.test(last.error ?? '') ? 3000 * (attempt + 1) : 500 * (attempt + 1);
    await sleep(pause);
  }

  return last;
}

const ID_KEYS = ['task_id', 'loop_story_id', 'agent_id', 'user_id', 'friend_user_id', 'offer_id', 'context', 'error_code'];

function briefContext(context) {
  try {
    const obj = JSON.parse(context);
    const keep = {};
    for (const key of ID_KEYS) {
      if (obj[key] !== undefined && obj[key] !== null && String(obj[key]).length < 80) {
        keep[key] = obj[key];
      }
    }
    const text = JSON.stringify(keep);
    if (text !== '{}') {
      return text;
    }
  } catch {
    // контекст не JSON, ниже короткая обрезка
  }

  const cut = context.replace(/,"trace":.*/, '').replace(/\s+/g, ' ').trim();

  return cut.length > 160 ? `${cut.slice(0, 160)}…` : cut;
}

export function summarizeLog(text) {
  const counts = { INFO: 0, WARNING: 0, ERROR: 0 };
  const groups = new Map();
  let first = null;
  let last = null;

  for (const raw of text.split(/\r?\n/)) {
    const match = raw.match(LINE);
    if (match === null) {
      continue;
    }

    const [, at, level, rest] = match;
    first ??= at;
    last = at;
    counts[level] += 1;

    const brace = rest.indexOf(' {');
    const message = (brace === -1 ? rest : rest.slice(0, brace)).trim();
    const context = brace === -1 ? '' : rest.slice(brace + 1).trim();
    const key = `${level}|${message.replace(/\d+/g, '#')}`;
    let group = groups.get(key);
    if (group === undefined) {
      group = { level, message, count: 0, samples: [] };
      groups.set(key, group);
    }
    group.count += 1;
    if (context !== '' && group.samples.length < 2) {
      group.samples.push(briefContext(context));
    }
  }

  const sorted = [...groups.values()].sort((a, b) => b.count - a.count);

  return {
    first,
    last,
    counts,
    errors: sorted.filter((group) => group.level !== 'INFO'),
  };
}

export function buildReport(date, channels) {
  const order = ['vk', 'vk-images', 'vk-sync', 'vkRepost'];
  const files = {};
  const missing = [];
  const downloadErrors = [];
  const errors = [];

  for (const name of order) {
    const channel = channels[name];
    if (channel === undefined || channel.present === false) {
      if (channel?.error && channel.missing === false) {
        downloadErrors.push({ channel: name, error: channel.error });
        files[name] = { present: false, failed: true, error: channel.error };
      } else {
        missing.push(name);
        files[name] = { present: false };
      }
      continue;
    }
    files[name] = {
      present: true,
      WARNING: channel.counts.WARNING,
      ERROR: channel.counts.ERROR,
    };
    for (const group of channel.errors) {
      errors.push({
        channel: name,
        level: group.level,
        message: group.message,
        count: group.count,
        samples: group.samples,
      });
    }
  }

  errors.sort((a, b) => b.count - a.count);

  return {
    date,
    timezone: 'Europe/Moscow',
    note: 'Только журналы vk, vk-images, vk-sync и vkRepost. errors — их WARNING и ERROR, без обычных записей. missing_files — файла или каталога нет. download_errors — скачивание сорвалось, файл может существовать.',
    files,
    missing_files: missing,
    download_errors: downloadErrors,
    errors,
  };
}

async function summarizeDay(date) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    throw new Error('Дата нужна в виде YYYY-MM-DD');
  }
  if (HOST === '' || USER === '' || PASSWORD === '') {
    throw new Error('Не заданы PROD_LOG_FTP_HOST, PROD_LOG_FTP_USER или PROD_LOG_FTP_PASSWORD');
  }

  const channels = {};
  for (const [name, pattern] of CHANNELS) {
    const path = pattern.replace('{date}', date);
    const file = await curl(path);
    if (!file.ok) {
      channels[name] = {
        present: false,
        path,
        missing: file.missing,
        ...(file.missing ? {} : { error: hideSecrets(file.error) }),
      };
      continue;
    }
    channels[name] = { present: true, path, ...summarizeLog(file.body) };
  }

  return buildReport(date, channels);
}

const TOOLS = [
  {
    name: 'summarize_prod_logs',
    description: 'Ошибки журналов vk, vk-images, vk-sync и vkRepost за сутки по Москве. errors — WARNING и ERROR. missing_files — файла нет. download_errors — скачивание сорвалось. Сырой журнал повторно не запрашивать.',
    inputSchema: {
      type: 'object',
      properties: {
        date: {
          type: 'string',
          description: 'Дата YYYY-MM-DD по Москве. Если пусто — последние полностью закончившиеся сутки.',
        },
      },
    },
  },
];

function reply(id, result) {
  write({ jsonrpc: '2.0', id, result });
}

function fail(id, message) {
  write({ jsonrpc: '2.0', id, error: { code: -32603, message: hideSecrets(message) } });
}

let framing = null;

function write(payload) {
  const json = Buffer.from(JSON.stringify(payload));
  if (framing === 'line') {
    process.stdout.write(json);
    process.stdout.write('\n');
    return;
  }
  process.stdout.write(`Content-Length: ${json.length}\r\n\r\n`);
  process.stdout.write(json);
}

async function handle(message) {
  if (message.id === undefined) {
    return;
  }
  const { id, method, params } = message;

  if (method === 'initialize') {
    reply(id, {
      protocolVersion: params?.protocolVersion ?? '2024-11-05',
      capabilities: { tools: {} },
      serverInfo: { name: 'prod-logs', version: '1.0.0' },
    });
    return;
  }
  if (method === 'ping') {
    reply(id, {});
    return;
  }
  if (method === 'tools/list') {
    reply(id, { tools: TOOLS });
    return;
  }
  if (method === 'tools/call') {
    try {
      if (params?.name !== 'summarize_prod_logs') {
        throw new Error(`Неизвестный инструмент ${params?.name ?? ''}`);
      }
      const date = params.arguments?.date || lastCompletedMoscowDate();
      const summary = await summarizeDay(date);
      reply(id, { content: [{ type: 'text', text: JSON.stringify(summary) }] });
    } catch (error) {
      reply(id, { content: [{ type: 'text', text: hideSecrets(error.message) }], isError: true });
    }
    return;
  }

  fail(id, `Метод не поддерживается: ${method}`);
}

if (process.argv.includes('--self-test')) {
  const vk = summarizeLog([
    '[2026-10-22 00:05:00] production.ERROR: Permission to perform this action is denied {"context":"friend_request_welcome"}',
    '[2026-10-22 00:05:01] production.ERROR: Can\'t send messages to this user due to their privacy settings {"context":"friend_request_welcome"}',
    '[2026-10-22 00:08:00] production.INFO: Загрузка изображения {"offer":"1"}',
  ].join('\n'));
  const report = buildReport('2026-10-22', {
    vk: { present: true, path: 'vk/posts/error-2026-10-22.log', ...vk },
    'vk-images': { present: false, path: 'vk/images/error-2026-10-22.log', missing: true },
    'vk-sync': { present: false, path: 'vk/sync/error-2026-10-22.log', missing: false, error: '425' },
    vkRepost: { present: false, path: 'vk/reposts/error-2026-10-22.log', missing: true },
  });
  const ok = vk.counts.INFO === 1
    && vk.errors.length === 2
    && report.errors.length === 2
    && report.errors[0].channel === 'vk'
    && report.missing_files.includes('vk-images')
    && report.missing_files.includes('vkRepost')
    && report.download_errors.some((item) => item.channel === 'vk-sync')
    && !JSON.stringify(report).includes('Загрузка изображения');
  if (!ok) {
    process.stderr.write('self-test failed\n');
    process.exit(1);
  }
  process.stdout.write('ok\n');
} else {
  let buffer = Buffer.alloc(0);

  function dispatch(body) {
    handle(JSON.parse(body)).catch((error) => {
      process.stderr.write(`${hideSecrets(error.stack ?? error.message)}\n`);
    });
  }

  process.stdin.on('data', (chunk) => {
    buffer = Buffer.concat([buffer, chunk]);
    if (framing === null) {
      const start = buffer.toString('utf8').trimStart();
      if (start.startsWith('{')) {
        framing = 'line';
      } else if (/content-length:/i.test(start)) {
        framing = 'headers';
      } else {
        return;
      }
    }

    if (framing === 'line') {
      let text = buffer.toString('utf8');
      let nl = text.indexOf('\n');
      while (nl !== -1) {
        const line = text.slice(0, nl).trim();
        text = text.slice(nl + 1);
        if (line !== '') {
          dispatch(line);
        }
        nl = text.indexOf('\n');
      }
      buffer = Buffer.from(text);
      return;
    }

    for (;;) {
      const headerEnd = buffer.indexOf('\r\n\r\n');
      if (headerEnd === -1) {
        return;
      }
      const header = buffer.subarray(0, headerEnd).toString('utf8');
      const length = Number(header.match(/Content-Length:\s*(\d+)/i)?.[1] ?? 0);
      const start = headerEnd + 4;
      if (buffer.length < start + length) {
        return;
      }
      const body = buffer.subarray(start, start + length).toString('utf8');
      buffer = buffer.subarray(start + length);
      dispatch(body);
    }
  });
}
