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

function sampleIds(samples) {
  const found = {};
  for (const key of ID_KEYS) {
    found[key] = new Set();
  }
  for (const sample of samples) {
    try {
      const obj = JSON.parse(sample);
      for (const key of ID_KEYS) {
        if (obj[key] !== undefined && obj[key] !== null) {
          found[key].add(String(obj[key]));
        }
      }
    } catch {
      // пример без JSON не участвует в связывании
    }
  }

  return found;
}

function idsOverlap(left, right) {
  for (const key of ['task_id', 'loop_story_id', 'user_id', 'friend_user_id']) {
    for (const id of left[key]) {
      if (right[key].has(id)) {
        return true;
      }
    }
  }

  return false;
}

function jobKind(message) {
  if (/токен недоступен/i.test(message)) return 'access';
  if (/удалён или не найден/i.test(message)) return 'expected';
  if (/автоприветств/i.test(message)) return 'welcome';
  if (/поздравлен|скидк/i.test(message)) return 'birthday';
  if (/истори/i.test(message)) return 'story';
  if (/изображен|визитк/i.test(message)) return 'image';
  if (/репост/i.test(message)) return 'repost';
  if (/товар/i.test(message)) return 'product';
  if (/комментар/i.test(message)) return 'comment';
  if (/лайк/i.test(message)) return 'like';
  if (/поста по офферу|создания поста/i.test(message)) return 'post';
  if (/синхрон/i.test(message)) return 'sync';
  if (/Сценарий не был выбран/.test(message)) return 'scenario';

  return 'job';
}

function extendedKind(channel, group) {
  const blob = `${group.message}\n${group.samples.join('\n')}`;
  if (channel === 'vk-images') return 'image';
  if (channel === 'vk-sync') return 'sync';
  if (channel === 'vkRepost') return 'repost';
  if (channel !== 'vk') return channel;
  if (/friend_request_welcome/.test(blob)) return 'welcome';
  if (/friend_user_id/.test(blob)) return 'birthday';
  if (/loop_story_id/.test(blob) || /error_message of type string/.test(blob)) return 'story';

  return 'vk';
}

function roleOf(kind) {
  if (kind === 'access') return 'access';
  if (kind === 'expected') return 'expected';

  return 'bug';
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
    if (level === 'INFO') {
      continue;
    }

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

  const errors = [...groups.values()].sort((a, b) => b.count - a.count);

  return { first, last, counts, errors };
}

const KIND_CHANNEL = {
  welcome: 'vk',
  birthday: 'vk',
  story: 'vk',
  post: 'vk',
  comment: 'vk',
  like: 'vk',
  product: 'vk',
  repost: 'vk',
  image: 'vk-images',
  sync: 'vk-sync',
};

export function buildReport(date, channels) {
  const order = ['job', 'vk', 'vk-images', 'vk-sync', 'vkRepost', 'stats', 'check_tokens', 'llm'];
  const files = {};
  const missing = [];
  const listed = {};

  for (const name of order) {
    const channel = channels[name];
    if (channel === undefined || channel.present === false) {
      missing.push(name);
      files[name] = { present: false, ...(channel?.error ? { error: channel.error } : {}) };
      continue;
    }
    files[name] = {
      present: true,
      INFO: channel.counts.INFO,
      WARNING: channel.counts.WARNING,
      ERROR: channel.counts.ERROR,
    };
    listed[name] = {
      present: true,
      path: channel.path,
      counts: channel.counts,
      groups: channel.errors,
    };
  }

  const pool = [];
  for (const name of ['vk', 'vk-images', 'vk-sync', 'vkRepost']) {
    const channel = channels[name];
    if (channel === undefined || channel.present === false) {
      continue;
    }
    for (const group of channel.errors) {
      pool.push({
        channel: name,
        kind: extendedKind(name, group),
        ids: sampleIds(group.samples),
        level: group.level,
        message: group.message,
        count: group.count,
        samples: group.samples,
      });
    }
  }

  const buckets = new Map();
  const consumed = new Set();
  for (const job of channels.job?.present ? channels.job.errors : []) {
    const kind = jobKind(job.message);
    const matched = [];
    pool.forEach((item, index) => {
      const sameKind = item.kind === kind && kind !== 'job' && kind !== 'vk';
      const sameIds = idsOverlap(sampleIds(job.samples), item.ids);
      if (roleOf(kind) === 'bug' && (sameKind || sameIds)) {
        matched.push(index);
      }
    });
    const key = `${roleOf(kind)}|${kind}|${matched.join(',')}`;
    let bucket = buckets.get(key);
    if (bucket === undefined) {
      bucket = { role: roleOf(kind), kind, matched, jobs: [] };
      buckets.set(key, bucket);
    }
    bucket.jobs.push(job);
    for (const index of matched) {
      consumed.add(index);
    }
  }

  const problems = [...buckets.values()]
    .sort((a, b) => (b.jobs.reduce((sum, job) => sum + job.count, 0) - a.jobs.reduce((sum, job) => sum + job.count, 0)))
    .map((bucket) => ({
      role: bucket.role,
      job_count: bucket.jobs.reduce((sum, job) => sum + job.count, 0),
      job: bucket.jobs.map((job) => ({ message: job.message, count: job.count, samples: job.samples })),
      extended_channel: bucket.matched.length > 0 ? pool[bucket.matched[0]].channel : (KIND_CHANNEL[bucket.kind] ?? null),
      extended: bucket.matched.map((index) => ({
        channel: pool[index].channel,
        level: pool[index].level,
        message: pool[index].message,
        count: pool[index].count,
        samples: pool[index].samples,
      })),
    }));

  const unmatched = pool
    .filter((_, index) => !consumed.has(index))
    .map((item) => ({
      channel: item.channel,
      level: item.level,
      message: item.message,
      count: item.count,
      samples: item.samples,
    }));

  return {
    date,
    timezone: 'Europe/Moscow',
    note: 'problems — предупреждения job уже с текстом исключения из парного vk-*. role access и expected в промпт кода не входят. missing_files — единственный список отсутствующих файлов. present true не означает, что файла нет. Группы INFO не передаются.',
    files,
    missing_files: missing,
    problems,
    unmatched_extended: unmatched,
    channels: listed,
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
      channels[name] = { present: false, path, ...(file.missing ? {} : { error: hideSecrets(file.error) }) };
      return;
    }
    channels[name] = { present: true, path, ...summarizeLog(file.body) };
  }));

  return buildReport(date, channels);
}

const TOOLS = [
  {
    name: 'summarize_prod_logs',
    description: 'Сводка продакшен-логов за сутки по Москве. problems содержит предупреждение job и текст исключения из парного канала vk, vk-images, vk-sync или vkRepost. missing_files — файлы, которых нет. Сырой журнал повторно не запрашивать.',
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
  const job = summarizeLog([
    '[2026-10-22 00:05:00] production.WARNING: Ошибка автоприветствия на заявку в друзья {"agent_id":11,"user_id":1}',
    '[2026-10-22 00:06:00] production.WARNING: Ошибка автоприветствия на заявку в друзья {"agent_id":11,"user_id":1}',
    '[2026-10-22 00:07:00] production.WARNING: Ошибка переопубликации loop-истории {"loop_story_id":5}',
    '[2026-10-22 00:08:00] production.INFO: Пост по офферу опубликован {"offer":"1"}',
  ].join('\n'));
  const vk = summarizeLog([
    '[2026-10-22 00:05:00] production.ERROR: Permission to perform this action is denied {"agent_id":11,"user_id":1,"context":"friend_request_welcome"}',
    '[2026-10-22 00:05:01] production.ERROR: Can\'t send messages to this user due to their privacy settings {"agent_id":11,"user_id":9,"context":"friend_request_welcome"}',
    '[2026-10-22 00:07:00] production.ERROR: Cannot assign null to property VK\\Exceptions\\VKApiException::$error_message of type string {"loop_story_id":5}',
    '[2026-10-22 00:08:00] production.INFO: Загрузка изображения {"offer":"1"}',
  ].join('\n'));
  const report = buildReport('2026-10-22', {
    job: { present: true, path: 'job-2026-10-22.log', ...job },
    vk: { present: true, path: 'vk/posts/error-2026-10-22.log', ...vk },
    'vk-images': { present: false, path: 'vk/images/error-2026-10-22.log' },
  });
  const welcome = report.problems.find((item) => item.job[0].message.includes('автоприветствия'));
  const story = report.problems.find((item) => item.job[0].message.includes('loop-истории'));
  const ok = job.counts.INFO === 1
    && job.counts.WARNING === 3
    && job.errors.length === 2
    && welcome?.extended.length === 2
    && welcome.extended_channel === 'vk'
    && story?.extended[0].message.includes('error_message')
    && report.missing_files.includes('vk-images')
    && report.channels.vk.groups.length === 3
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
