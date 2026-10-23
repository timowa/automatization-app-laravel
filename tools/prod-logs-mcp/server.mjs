import { spawn } from 'node:child_process';

const HOST = process.env.PROD_LOG_FTP_HOST ?? '';
const USER = process.env.PROD_LOG_FTP_USER ?? '';
const PASSWORD = process.env.PROD_LOG_FTP_PASSWORD ?? '';

const CHANNELS = [
  ['job', 'job-{date}.log'],
  ['vk', 'vk/posts/error-{date}.log'],
  ['vk-images', 'vk/images/error-{date}.log'],
  ['vk-sync', 'vk/sync/error-{date}.log'],
  ['check_tokens', 'vk/tokens/error-{date}.log'],
  ['stats', 'vk/stats/error-{date}.log'],
  ['vkRepost', 'vk/reposts/error-{date}.log'],
  ['llm', 'llm/error-{date}.log'],
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

function curl(ftpPath) {
  const url = `ftp://${HOST}/${ftpPath}`;

  return new Promise((resolve, reject) => {
    const child = spawn('curl', [
      '--config', '-',
      '--noproxy', '*',
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
        const missing = /550|78|9\b|denied you to change|No such file/i.test(stderr);
        resolve({ ok: false, missing, error: stderr || `curl ${code}` });
        return;
      }
      resolve({ ok: true, body: Buffer.concat(out).toString('utf8') });
    });
  });
}

function clip(text) {
  const cut = text.replace(/,"trace":.*/, '').replace(/\s+/g, ' ').trim();

  return cut.length > 220 ? `${cut.slice(0, 220)}…` : cut;
}

export function summarizeLog(text) {
  const counts = { INFO: 0, WARNING: 0, ERROR: 0 };
  const groups = new Map();
  let first = null;
  let last = null;
  const names = [];
  let readingNames = false;

  for (const raw of text.split(/\r?\n/)) {
    if (readingNames) {
      if (raw.startsWith('- ')) {
        names.push(raw.slice(2).trim());
        continue;
      }
      if (raw.trim() === '') {
        continue;
      }
      readingNames = false;
    }

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
    if (context !== '' && group.samples.length < 3 && (level !== 'INFO' || group.samples.length === 0)) {
      group.samples.push(clip(context));
    }
    if (message.startsWith('Невалидные токены у агентов')) {
      readingNames = true;
    }
  }

  const listed = [...groups.values()].sort((a, b) => b.count - a.count).slice(0, 40);

  return {
    lines: counts.INFO + counts.WARNING + counts.ERROR,
    first,
    last,
    counts,
    groups: listed,
    ...(names.length > 0 ? { invalid_token_names: names } : {}),
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
  await Promise.all(CHANNELS.map(async ([name, pattern]) => {
    const path = pattern.replace('{date}', date);
    const file = await curl(path);
    if (!file.ok) {
      channels[name] = { present: false, path, ...(file.missing ? {} : { error: file.error }) };
      return;
    }
    channels[name] = { present: true, path, bytes: Buffer.byteLength(file.body), ...summarizeLog(file.body) };
  }));

  return {
    date,
    timezone: 'Europe/Moscow',
    note: 'laravel.log и worker.log не скачиваются: это не дневные файлы. Дневные каналы уже сгруппированы, сырые журналы читать не нужно.',
    channels,
  };
}

const TOOLS = [
  {
    name: 'summarize_prod_logs',
    description: 'Сводка продакшен-логов за сутки по Москве. Один вызов вместо скачивания файлов. По каждому каналу: есть ли файл, число INFO, WARNING и ERROR, группы одинаковых сообщений и до трёх коротких примеров. Сырой журнал повторно не запрашивать.',
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

function write(payload) {
  const json = Buffer.from(JSON.stringify(payload));
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
  const sample = [
    '[2026-10-22 00:00:14] production.INFO: Невалидные токены у агентов: ',
    '',
    '- Иванов Иван',
    '[2026-10-22 00:05:00] production.WARNING: Ошибка автоприветствия {"agent_id":11,"user_id":1}',
    '[2026-10-22 00:10:00] production.WARNING: Ошибка автоприветствия {"agent_id":11,"user_id":1}',
  ].join('\n');
  const summary = summarizeLog(sample);
  if (summary.counts.WARNING !== 2 || summary.invalid_token_names?.[0] !== 'Иванов Иван') {
    process.stderr.write('self-test failed\n');
    process.exit(1);
  }
  process.stdout.write('ok\n');
} else {
  let buffer = Buffer.alloc(0);
  process.stdin.on('data', (chunk) => {
    buffer = Buffer.concat([buffer, chunk]);
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
      handle(JSON.parse(body)).catch((error) => {
        process.stderr.write(`${hideSecrets(error.stack ?? error.message)}\n`);
      });
    }
  });
}
